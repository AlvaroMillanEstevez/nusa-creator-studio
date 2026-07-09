<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import AudioAssetPlayer from '@/components/AudioAssetPlayer.vue'
import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import {
  generateAiVoiceover,
  getCreatorProject,
  getGeneration,
  getProjectActions,
} from '@/services/projects'
import { useAuthStore } from '@/stores/auth'
import {
  campaignLanguageOptions,
  generationOutput,
  isAiVoiceoverResult,
  isCampaignGenerationResult,
  isProductToVideoResult,
  voiceoverPaceOptions,
  voiceoverStyleOptions,
  voiceoverToneOptions,
  type AiVoiceoverGeneratedAudio,
  type CreatorGeneration,
  type CreatorProject,
  type GenerateAiVoiceoverPayload,
  type ProjectAction,
  type ProjectActionRecommendedSource,
} from '@/types/project'

const VOICEOVER_MAX_CHARACTERS = 1000
const VOICEOVER_POLL_INTERVAL_MS = 2_000

type SourceMode = 'recommended' | 'manual'
type VoiceoverState = 'idle' | 'submitting' | 'processing' | 'completed' | 'failed'

type VoiceoverSource = ProjectActionRecommendedSource & { fullText?: string }

const { t } = useTranslations()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const querySourceGeneration = ref<CreatorGeneration | null>(null)
const generatedGeneration = ref<CreatorGeneration | null>(null)
const isLoading = ref(true)
const loadError = ref('')
const actionError = ref('')
const voiceoverState = ref<VoiceoverState>('idle')
const sourceMode = ref<SourceMode>('recommended')
const selectedSourceId = ref<number | null>(null)
const manualSourceText = ref('')
const language = ref('english')
const tone = ref('natural')
const pace = ref('medium')
const style = ref('natural_creator')
const notes = ref('')
let voiceoverPollTimer: ReturnType<typeof setTimeout> | null = null

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const sourceGenerationId = computed(() => {
  const value = Array.isArray(route.query.source_generation_id)
    ? route.query.source_generation_id[0]
    : route.query.source_generation_id

  if (typeof value !== 'string') {
    return null
  }

  const parsed = Number.parseInt(value, 10)

  return Number.isFinite(parsed) ? parsed : null
})
const safeReturnTo = computed(() => {
  const value = Array.isArray(route.query.return_to) ? route.query.return_to[0] : route.query.return_to

  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : null
})
const workspaceRoute = computed(() => `/projects/${projectId.value}`)
const backRoute = computed(() => safeReturnTo.value ?? workspaceRoute.value)
const voiceoverAction = computed(() =>
  actions.value.find((action) => action.key === 'generate_voiceover_from_campaign') ??
  actions.value.find((action) => action.key === 'generate_voiceover_from_script') ??
  null,
)
const hasInternalOrUnlimitedAccess = computed(() => {
  const user = auth.user

  return user?.is_unlimited === true ||
    user?.role === 'admin' ||
    user?.role === 'internal_creator' ||
    user?.plan_key === 'internal_unlimited'
})
const isUpgradeAction = computed(() =>
  voiceoverAction.value?.upgrade_required === true || voiceoverAction.value?.action_mode === 'upgrade',
)
const isUnavailableAction = computed(() =>
  voiceoverAction.value?.status === 'coming_soon' ||
  voiceoverAction.value?.status === 'unavailable' ||
  voiceoverAction.value?.action_mode === 'coming_soon',
)
const querySourceOption = computed(() => {
  if (querySourceGeneration.value === null) {
    return null
  }

  return sourceFromGeneration(querySourceGeneration.value)
})
const activeSourceOptions = computed<VoiceoverSource[]>(() => {
  const sources = [...(voiceoverAction.value?.recommended_sources ?? [])] as VoiceoverSource[]
  const querySource = querySourceOption.value

  if (querySource !== null && !sources.some((source) => source.generation_id === querySource.generation_id)) {
    sources.unshift(querySource)
  }

  return sources
})
const selectedSource = computed(() => {
  if (selectedSourceId.value === null) {
    return null
  }

  return activeSourceOptions.value.find((source) => source.generation_id === selectedSourceId.value) ?? null
})
const selectedSourcePreview = computed(() => {
  if (sourceMode.value === 'manual') {
    return manualSourceText.value.trim()
  }

  return selectedSource.value?.fullText ?? selectedSource.value?.excerpt ?? ''
})
const sourceCharacterCount = computed(() => selectedSourcePreview.value.length)
const validationError = computed(() => {
  if (sourceMode.value === 'recommended' && selectedSource.value !== null) {
    return ''
  }

  if (sourceMode.value === 'manual' && manualSourceText.value.trim() !== '') {
    if (sourceCharacterCount.value > VOICEOVER_MAX_CHARACTERS) {
      return t('projectActions.sourceTooLong', { count: VOICEOVER_MAX_CHARACTERS })
    }

    return ''
  }

  return t('voiceoverAction.selectSourceOrWriteCustomText')
})
const costLabel = computed(() => {
  const action = voiceoverAction.value

  if (action === null || action.credit_cost === null) {
    return t('tools.creditCostComingSoon')
  }

  if (hasInternalOrUnlimitedAccess.value && action.user_has_access !== false) {
    return t('projectActions.noCreditCharge')
  }

  if (action.credit_cost === 0) {
    return t('projectActions.noCreditCharge')
  }

  return action.credit_cost === 1
    ? t('projectActions.oneCredit')
    : t('projectActions.manyCredits', { credits: action.credit_cost })
})
const serviceTierLabel = computed(() => {
  const tier = voiceoverAction.value?.service_tier

  if (tier === 'premium') {
    return t('projectActions.premiumWorkflow')
  }

  if (tier === 'advanced') {
    return t('projectActions.advancedWorkflow')
  }

  if (tier === 'basic') {
    return t('projectActions.basicWorkflow')
  }

  return tier ?? t('tools.serviceTier')
})
const generatedOutput = computed(() =>
  generatedGeneration.value === null ? null : generationOutput(generatedGeneration.value),
)
const generatedVoiceoverResult = computed(() =>
  generatedGeneration.value?.status === 'completed' && isAiVoiceoverResult(generatedOutput.value)
    ? generatedOutput.value
    : null,
)
const generatedAudio = computed<AiVoiceoverGeneratedAudio | null>(() =>
  generatedVoiceoverResult.value?.audio?.status === 'generated'
    ? generatedVoiceoverResult.value.audio
    : null,
)
const audioTitle = computed(() =>
  generatedAudio.value?.media_asset_name ?? t('voiceover.generatedAudio'),
)

function cleanText(value: string | null | undefined): string | null {
  if (typeof value !== 'string') {
    return null
  }

  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

function sourceTitle(generation: CreatorGeneration): string {
  if (generation.job_type === 'generate_product_video_script') {
    return t('voiceoverAction.productVideoSource')
  }

  return t('voiceoverAction.campaignSource')
}

function sourceFromGeneration(generation: CreatorGeneration): VoiceoverSource | null {
  if (generation.status !== 'completed') {
    return null
  }

  const output = generationOutput(generation)

  if (generation.job_type === 'generate_affiliate_campaign' && isCampaignGenerationResult(output)) {
    const sceneText = output.main_script?.scenes?.map((scene) => scene.voiceover).filter(Boolean).join('\n') ??
      output.script?.scenes?.map((scene) => scene.voiceover).filter(Boolean).join('\n')
    const text = cleanText(output.voiceover_ready_script) ??
      cleanText(output.main_script?.voiceover_script) ??
      cleanText(output.script?.voiceover_script) ??
      cleanText(sceneText)

    return text === null
      ? null
      : {
          generation_id: generation.id,
          job_type: generation.job_type,
          title: sourceTitle(generation),
          created_at: generation.created_at,
          excerpt: text,
          fullText: text,
        }
  }

  if (generation.job_type === 'generate_product_video_script' && isProductToVideoResult(output)) {
    const text = cleanText(output.storyboard.map((scene) => scene.voiceover).filter(Boolean).join('\n'))

    return text === null
      ? null
      : {
          generation_id: generation.id,
          job_type: generation.job_type,
          title: sourceTitle(generation),
          created_at: generation.created_at,
          excerpt: text,
          fullText: text,
        }
  }

  return null
}

function initializeSourceSelection(): void {
  const preferred = sourceGenerationId.value === null
    ? activeSourceOptions.value[0]
    : activeSourceOptions.value.find((source) => source.generation_id === sourceGenerationId.value) ?? activeSourceOptions.value[0]

  if (preferred) {
    sourceMode.value = 'recommended'
    selectedSourceId.value = preferred.generation_id
    return
  }

  sourceMode.value = 'manual'
  selectedSourceId.value = null
}

function isTerminalVoiceover(generation: CreatorGeneration): boolean {
  return generation.status === 'completed' || generation.status === 'failed'
}

function stopVoiceoverPolling(): void {
  if (voiceoverPollTimer !== null) {
    clearTimeout(voiceoverPollTimer)
    voiceoverPollTimer = null
  }
}

function scheduleVoiceoverPoll(generationId: number): void {
  if (voiceoverPollTimer !== null) {
    return
  }

  voiceoverPollTimer = setTimeout(() => {
    voiceoverPollTimer = null
    void pollVoiceover(generationId)
  }, VOICEOVER_POLL_INTERVAL_MS)
}

async function pollVoiceover(generationId: number): Promise<void> {
  try {
    const updated = await getGeneration(generationId)
    generatedGeneration.value = updated

    if (isTerminalVoiceover(updated)) {
      voiceoverState.value = updated.status === 'completed' ? 'completed' : 'failed'
      return
    }

    voiceoverState.value = 'processing'
    scheduleVoiceoverPoll(generationId)
  } catch {
    voiceoverState.value = 'failed'
    actionError.value = t('campaignResult.voiceoverError')
  }
}

async function loadVoiceoverAction(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      getProjectActions(projectId.value),
    ])

    project.value = loadedProject
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []

    if (sourceGenerationId.value !== null) {
      try {
        const sourceGeneration = await getGeneration(sourceGenerationId.value)

        if (sourceGeneration.project_id === loadedProject.id) {
          querySourceGeneration.value = sourceGeneration
        }
      } catch {
        querySourceGeneration.value = null
      }
    }

    initializeSourceSelection()
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : t('voiceoverAction.loadError')
  } finally {
    isLoading.value = false
  }
}

async function submitVoiceover(): Promise<void> {
  actionError.value = ''

  if (isUpgradeAction.value) {
    await router.push(voiceoverAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (validationError.value || isUnavailableAction.value || voiceoverState.value === 'submitting' || voiceoverState.value === 'processing') {
    return
  }

  const payload: GenerateAiVoiceoverPayload = {
    language: language.value,
    tone: tone.value,
    pace: pace.value,
    style: style.value,
  }

  if (notes.value.trim()) {
    payload.notes = notes.value.trim()
  }

  if (sourceMode.value === 'recommended' && selectedSource.value !== null) {
    payload.source_generation_id = selectedSource.value.generation_id
  } else {
    payload.source_text = manualSourceText.value.trim()
  }

  voiceoverState.value = 'submitting'
  stopVoiceoverPolling()

  try {
    const response = await generateAiVoiceover(projectId.value, payload)
    generatedGeneration.value = response.data

    if (isTerminalVoiceover(response.data)) {
      voiceoverState.value = response.data.status === 'completed' ? 'completed' : 'failed'
    } else {
      voiceoverState.value = 'processing'
      scheduleVoiceoverPoll(response.data.id)
    }
  } catch (error: unknown) {
    voiceoverState.value = 'failed'

    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      actionError.value = t('projectActions.insufficientCredits')
    } else {
      actionError.value = error instanceof ApiError ? error.message : t('campaignResult.voiceoverError')
    }
  }
}

function generateAnotherVoiceover(): void {
  generatedGeneration.value = null
  voiceoverState.value = 'idle'
  actionError.value = ''
}

onMounted(loadVoiceoverAction)
onBeforeUnmount(stopVoiceoverPolling)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-4xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-4 w-32 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-8 h-80 animate-pulse rounded-[2rem] bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">{{ t('voiceoverAction.loading') }}</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">{{ t('voiceoverAction.unavailable') }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ t('voiceoverAction.needsAnotherTry') }}</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button
          type="button"
          class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300"
          @click="loadVoiceoverAction"
        >
          {{ t('common.tryAgain') }}
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink
            :to="backRoute"
            class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          >
            ← {{ safeReturnTo ? t('voiceoverAction.backToCampaignResult') : t('projectActions.backToWorkspace') }}
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
            {{ t('projectActions.createVoiceover') }}
          </h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            {{ t('voiceoverAction.subtitle') }}
          </p>
        </header>

        <section
          v-if="generatedVoiceoverResult"
          class="nusa-card-premium mt-8 rounded-[2rem] p-5"
          data-testid="voiceover-action-success"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('generation.completed') }}
          </p>
          <h2 class="mt-3 text-2xl font-bold">{{ t('voiceoverAction.audioAddedToProjectAssets') }}</h2>
          <p class="mt-3 text-sm leading-6 text-emerald-100/80">
            {{ audioTitle }}
          </p>
          <AudioAssetPlayer
            v-if="generatedAudio?.stream_url"
            compact
            :stream-url="generatedAudio.stream_url"
            :download-url="generatedAudio.download_url"
            :title="audioTitle"
            class="mt-5"
          />
          <p v-else class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/50 p-3 text-sm leading-6 text-neutral-400">
            {{ t('voiceoverAction.audioAvailableFromAssets') }}
          </p>
          <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <RouterLink
              :to="workspaceRoute"
              class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
            >
              {{ t('projectActions.backToWorkspace') }}
            </RouterLink>
            <RouterLink
              v-if="safeReturnTo"
              :to="safeReturnTo"
              class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
            >
              {{ t('voiceoverAction.backToCampaignResult') }}
            </RouterLink>
            <button
              type="button"
              class="min-h-11 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-neutral-500 hover:bg-neutral-900"
              @click="generateAnotherVoiceover"
            >
              {{ t('voiceoverAction.generateAnotherVoiceover') }}
            </button>
          </div>
        </section>

        <section v-else class="nusa-card-premium mt-8 rounded-[2rem] p-5">
          <div class="grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
            <div class="space-y-6">
              <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-neutral-100">
                  {{ t('projectActions.selectSource') }}
                </legend>
                <p class="text-xs leading-5 text-neutral-500">
                  {{ t('projectActions.chooseSavedGenerationOrCustomText') }}
                </p>

                <label
                  v-if="activeSourceOptions.length"
                  class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300"
                >
                  <input
                    v-model="sourceMode"
                    type="radio"
                    value="recommended"
                    class="mt-1 accent-emerald-400"
                  >
                  <span>
                    <span class="block font-semibold text-neutral-100">{{ t('voiceoverAction.useSavedGeneration') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('projectActions.selectedSourceHelp') }}</span>
                  </span>
                </label>

                <select
                  v-if="sourceMode === 'recommended' && activeSourceOptions.length"
                  v-model.number="selectedSourceId"
                  class="nusa-select"
                  data-testid="voiceover-source-select"
                >
                  <option
                    v-for="source in activeSourceOptions"
                    :key="source.generation_id"
                    :value="source.generation_id"
                  >
                    {{ source.title }} · #{{ source.generation_id }}
                  </option>
                </select>

                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input
                    v-model="sourceMode"
                    type="radio"
                    value="manual"
                    class="mt-1 accent-emerald-400"
                    data-testid="voiceover-manual-mode"
                  >
                  <span>
                    <span class="block font-semibold text-neutral-100">{{ t('projectActions.manualSourceText') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('projectActions.noSavedSource') }}</span>
                  </span>
                </label>

                <textarea
                  v-if="sourceMode === 'manual'"
                  v-model="manualSourceText"
                  class="nusa-textarea min-h-40"
                  :placeholder="t('projectActions.sourceTextPlaceholder')"
                  data-testid="voiceover-source-text"
                />
              </fieldset>

              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="nusa-label">{{ t('projectActions.language') }}</span>
                  <select v-model="language" class="nusa-select mt-2">
                    <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">{{ t('projectActions.voice') }}</span>
                  <select class="nusa-select mt-2" disabled>
                    <option>{{ t('projectActions.defaultVoice') }}</option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">{{ t('projectActions.tone') }}</span>
                  <select v-model="tone" class="nusa-select mt-2">
                    <option v-for="option in voiceoverToneOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">{{ t('projectActions.pace') }}</span>
                  <select v-model="pace" class="nusa-select mt-2">
                    <option v-for="option in voiceoverPaceOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block sm:col-span-2">
                  <span class="nusa-label">{{ t('projectActions.style') }}</span>
                  <select v-model="style" class="nusa-select mt-2">
                    <option v-for="option in voiceoverStyleOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block sm:col-span-2">
                  <span class="nusa-label">{{ t('projectActions.optionalNotes') }}</span>
                  <textarea v-model="notes" class="nusa-textarea mt-2 min-h-24" />
                </label>
              </div>
            </div>

            <aside class="space-y-3">
              <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                  {{ t('voiceoverAction.costAndAccess') }}
                </p>
                <p class="mt-3 text-sm font-semibold text-neutral-100">{{ serviceTierLabel }}</p>
                <p class="mt-2 text-sm text-emerald-300">{{ costLabel }}</p>
                <p v-if="isUpgradeAction" class="mt-3 text-xs leading-5 text-amber-200">
                  {{ voiceoverAction?.locked_reason ?? t('projectActions.upgradeRequired') }}
                </p>
              </div>

              <p class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                {{ t('projectActions.characterCount') }}: {{ sourceCharacterCount }} / {{ VOICEOVER_MAX_CHARACTERS }}
              </p>

              <p v-if="validationError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/40 p-3 text-sm leading-6 text-rose-100">
                {{ validationError }}
              </p>

              <p v-if="actionError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/40 p-3 text-sm leading-6 text-rose-100">
                {{ actionError }}
              </p>

              <p v-if="voiceoverState === 'processing'" class="rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-sm leading-6 text-sky-100">
                {{ t('campaignResult.voiceoverQueued') }}
              </p>

              <button
                type="button"
                :disabled="voiceoverState === 'submitting' || voiceoverState === 'processing' || (!isUpgradeAction && (!!validationError || isUnavailableAction))"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-white px-5 text-sm font-black text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400"
                data-testid="voiceover-action-submit"
                @click="submitVoiceover"
              >
                <span
                  v-if="voiceoverState === 'submitting' || voiceoverState === 'processing'"
                  class="size-4 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
                  aria-hidden="true"
                />
                {{ isUpgradeAction ? t('projectActions.upgradeSubscription') : t('projectActions.generateVoiceover') }}
              </button>
            </aside>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>