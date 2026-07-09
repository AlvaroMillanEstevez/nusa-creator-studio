<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import AudioAssetPlayer from '@/components/AudioAssetPlayer.vue'
import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateAiVoiceover, getGeneration } from '@/services/projects'
import { useAuthStore } from '@/stores/auth'
import type {
  AiVoiceoverGeneratedAudio,
  CampaignGenerationResult,
  CampaignHook,
  CampaignMainScript,
  CampaignScriptScene,
  CreatorGeneration,
  GenerateAiVoiceoverPayload,
  ProjectAction,
} from '@/types/project'
import {
  generationOutput,
  isAiVoiceoverResult,
} from '@/types/project'

const VOICEOVER_MAX_CHARACTERS = 1000
const VOICEOVER_POLL_INTERVAL_MS = 2_000

type VoiceoverState = 'idle' | 'submitting' | 'processing' | 'completed' | 'failed'

const props = defineProps<{
  result: CampaignGenerationResult
  projectId?: number | string | null
  generationId?: number | null
  voiceoverAction?: ProjectAction | null
}>()

const emit = defineEmits<{
  voiceoverGenerated: [generation: CreatorGeneration]
}>()

const { t } = useTranslations()
const router = useRouter()
const auth = useAuthStore()
const copyStatus = ref<'idle' | 'copied' | 'unavailable'>('idle')
const narrationText = ref('')
const voiceoverState = ref<VoiceoverState>('idle')
const voiceoverError = ref('')
const generatedVoiceover = ref<CreatorGeneration | null>(null)
let voiceoverPollTimer: ReturnType<typeof setTimeout> | null = null

const campaignTitle = computed(
  () => props.result.campaign_title ?? props.result.main_script?.title ?? props.result.script?.title ?? 'Campaign result',
)
const hooks = computed<CampaignHook[]>(() =>
  props.result.hooks.map((hook, index) => {
    if (typeof hook === 'string') {
      return {
        label: `Hook ${index + 1}`,
        text: hook,
        intent: '',
      }
    }

    return hook
  }),
)
const script = computed<CampaignMainScript>(
  () =>
    props.result.main_script ??
    props.result.script ?? {
      duration_seconds: 30,
      voiceover_script: '',
      on_screen_text: [],
      scenes: [] as CampaignScriptScene[],
    },
)
const scenes = computed(() => script.value.scenes ?? [])
const captions = computed(() => props.result.captions ?? (props.result.caption ? [props.result.caption] : []))
const ctaOptions = computed(() => props.result.cta_options ?? (props.result.cta ? [props.result.cta] : []))
const usageNotes = computed(() => props.result.usage_notes ?? props.result.notes ?? [])
const complianceNotes = computed(() => props.result.compliance_notes ?? [])
const voiceoverScript = computed(() => {
  if (props.result.voiceover_ready_script) {
    return props.result.voiceover_ready_script
  }

  if (script.value.voiceover_script) {
    return script.value.voiceover_script
  }

  return scenes.value.map((scene) => scene.voiceover).filter(Boolean).join('\n')
})
const providerMetadata = computed(() => props.result.provider_metadata ?? null)
const hasProviderMetadata = computed(
  () => providerMetadata.value !== null && Object.keys(providerMetadata.value).length > 0,
)
const narrationCharacterCount = computed(() => narrationText.value.length)
const narrationTrimmed = computed(() => narrationText.value.trim())
const narrationValidationError = computed(() => {
  if (!narrationTrimmed.value) {
    return t('campaignResult.narrationRequired')
  }

  if (narrationCharacterCount.value > VOICEOVER_MAX_CHARACTERS) {
    return t('campaignResult.narrationTooLong', { count: VOICEOVER_MAX_CHARACTERS })
  }

  return ''
})
const hasInternalOrUnlimitedAccess = computed(() => {
  const user = auth.user

  return user?.is_unlimited === true ||
    user?.role === 'admin' ||
    user?.role === 'internal_creator' ||
    user?.plan_key === 'internal_unlimited'
})
const isVoiceoverUpgrade = computed(() =>
  props.voiceoverAction?.upgrade_required === true || props.voiceoverAction?.action_mode === 'upgrade',
)
const isVoiceoverUnavailable = computed(() =>
  props.voiceoverAction?.status === 'coming_soon' ||
  props.voiceoverAction?.status === 'unavailable' ||
  props.voiceoverAction?.action_mode === 'coming_soon',
)
const canShowVoiceoverCta = computed(() => props.projectId !== undefined && props.projectId !== null)
const voiceoverCostLabel = computed(() => {
  const action = props.voiceoverAction

  if (!action || action.credit_cost === null) {
    return ''
  }

  if (hasInternalOrUnlimitedAccess.value && action.user_has_access !== false) {
    return t('campaignResult.noCreditCharge')
  }

  if (action.credit_cost === 0) {
    return t('campaignResult.noCreditCharge')
  }

  return t('campaignResult.costsCredits', { credits: action.credit_cost })
})
const generatedVoiceoverResult = computed(() => {
  if (generatedVoiceover.value?.status !== 'completed') {
    return null
  }

  const output = generationOutput(generatedVoiceover.value)

  return isAiVoiceoverResult(output) ? output : null
})
const generatedAudio = computed<AiVoiceoverGeneratedAudio | null>(() =>
  generatedVoiceoverResult.value?.audio ?? null,
)
const voiceoverPreviewTitle = computed(() =>
  generatedAudio.value?.media_asset_name ?? t('voiceover.generatedAudio'),
)

function sceneTime(scene: CampaignScriptScene): string {
  return scene.time_range ?? scene.timestamp ?? ''
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
    generatedVoiceover.value = updated
    emit('voiceoverGenerated', updated)

    if (isTerminalVoiceover(updated)) {
      voiceoverState.value = updated.status === 'completed' ? 'completed' : 'failed'
      return
    }

    voiceoverState.value = 'processing'
    scheduleVoiceoverPoll(generationId)
  } catch {
    voiceoverState.value = 'failed'
    voiceoverError.value = t('campaignResult.voiceoverError')
  }
}

async function copyVoiceoverScript(): Promise<void> {
  if (!narrationTrimmed.value) {
    return
  }

  if (!navigator.clipboard?.writeText) {
    copyStatus.value = 'unavailable'
    return
  }

  try {
    await navigator.clipboard.writeText(narrationTrimmed.value)
    copyStatus.value = 'copied'
  } catch {
    copyStatus.value = 'unavailable'
  }
}

async function generateVoiceoverFromNarration(): Promise<void> {
  voiceoverError.value = ''

  if (isVoiceoverUpgrade.value) {
    await router.push(props.voiceoverAction?.primary_cta?.route ?? '/pricing')
    return
  }

  if (!props.projectId || isVoiceoverUnavailable.value || narrationValidationError.value) {
    return
  }

  const payload: GenerateAiVoiceoverPayload = {
    source_text: narrationTrimmed.value,
  }

  if (props.generationId !== undefined && props.generationId !== null) {
    payload.source_generation_id = props.generationId
  }

  voiceoverState.value = 'submitting'
  stopVoiceoverPolling()

  try {
    const response = await generateAiVoiceover(props.projectId, payload)
    generatedVoiceover.value = response.data
    emit('voiceoverGenerated', response.data)

    if (isTerminalVoiceover(response.data)) {
      voiceoverState.value = response.data.status === 'completed' ? 'completed' : 'failed'
    } else {
      voiceoverState.value = 'processing'
      scheduleVoiceoverPoll(response.data.id)
    }
  } catch (error: unknown) {
    voiceoverState.value = 'failed'

    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      voiceoverError.value = t('projectActions.insufficientCredits')
    } else {
      voiceoverError.value = error instanceof ApiError ? error.message : t('campaignResult.voiceoverError')
    }
  }
}

watch(voiceoverScript, (next) => {
  narrationText.value = next
}, { immediate: true })

onBeforeUnmount(() => {
  stopVoiceoverPolling()
})
</script>

<template>
  <div class="space-y-5">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.campaignPackage') }}
      </p>
      <h2 class="mt-2 text-2xl font-bold leading-tight">{{ campaignTitle }}</h2>
      <div class="mt-5 space-y-4 text-sm leading-6 text-neutral-300">
        <p v-if="result.product_angle">
          <span class="font-semibold text-neutral-100">{{ t('campaignResult.productAngle') }}:</span>
          {{ result.product_angle }}
        </p>
        <p v-if="result.target_audience_summary">
          <span class="font-semibold text-neutral-100">{{ t('campaignResult.audience') }}:</span>
          {{ result.target_audience_summary }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <h2 class="text-2xl font-bold">{{ t('campaignResult.hooks') }}</h2>
      <ol class="mt-5 space-y-3">
        <li
          v-for="(hook, index) in hooks"
          :key="`${hook.text}-${index}`"
          class="rounded-2xl bg-neutral-950/70 p-4 text-sm leading-6 text-neutral-200"
        >
          <div class="flex gap-3">
            <span class="font-bold text-emerald-400">{{ index + 1 }}</span>
            <div class="min-w-0 flex-1">
              <p class="break-words">{{ hook.text }}</p>
              <p v-if="hook.intent" class="mt-2 text-xs leading-5 text-neutral-500">
                {{ hook.intent }}
              </p>
            </div>
          </div>
        </li>
      </ol>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('campaignResult.tiktokScript') }}
          </p>
          <h2 class="mt-2 text-xl font-bold">{{ campaignTitle }}</h2>
        </div>
        <span class="shrink-0 text-sm text-neutral-400"> {{ script.duration_seconds }}s </span>
      </div>

      <details v-if="voiceoverScript" class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-neutral-100">
          {{ t('campaignResult.narrationVoice') }}
        </summary>
        <div class="mt-4 space-y-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <p class="text-xs leading-5 text-neutral-500">
              {{ t('campaignResult.narrationHelper') }}
            </p>
            <button
              type="button"
              class="shrink-0 rounded-full border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-xs font-semibold text-emerald-200 transition hover:border-emerald-500 hover:bg-emerald-900/60"
              @click="copyVoiceoverScript"
            >
              {{ copyStatus === 'copied' ? t('campaignResult.copied') : t('campaignResult.copyScript') }}
            </button>
          </div>

          <label class="block">
            <span class="nusa-label">{{ t('campaignResult.editNarrationBeforeVoiceover') }}</span>
            <textarea
              v-model="narrationText"
              class="nusa-textarea mt-2 min-h-40"
              data-testid="narration-voice-textarea"
            />
          </label>

          <div class="flex flex-col gap-3 text-xs text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
            <p>
              {{ t('projectActions.characterCount') }}:
              {{ narrationCharacterCount }} / {{ VOICEOVER_MAX_CHARACTERS }}
            </p>
            <p v-if="voiceoverCostLabel" class="font-semibold text-emerald-300">
              {{ voiceoverCostLabel }}
            </p>
          </div>

          <p v-if="narrationValidationError" class="text-xs text-amber-300">
            {{ narrationValidationError }}
          </p>

          <div
            v-if="generatedAudio"
            class="rounded-2xl border border-emerald-900/60 bg-emerald-950/20 p-3"
            data-testid="campaign-voiceover-preview"
          >
            <p class="mb-3 text-xs font-semibold text-emerald-100">
              {{ t('campaignResult.audioAddedToProjectAssets') }}: {{ voiceoverPreviewTitle }}
            </p>
            <AudioAssetPlayer
              compact
              :stream-url="generatedAudio.stream_url"
              :download-url="generatedAudio.download_url"
              :title="voiceoverPreviewTitle"
            />
          </div>

          <p v-else-if="voiceoverState === 'processing'" class="rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-xs leading-5 text-sky-100">
            {{ t('campaignResult.voiceoverQueued') }}
          </p>

          <p v-if="voiceoverError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-100">
            {{ voiceoverError }}
          </p>
          <p v-if="copyStatus === 'unavailable'" class="text-xs text-amber-300">
            {{ t('campaignResult.copyUnavailable') }}
          </p>

          <button
            v-if="canShowVoiceoverCta"
            type="button"
            :disabled="voiceoverState === 'submitting' || voiceoverState === 'processing' || (!isVoiceoverUpgrade && !!narrationValidationError) || isVoiceoverUnavailable"
            class="flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 sm:w-auto"
            data-testid="generate-narration-voiceover"
            @click="generateVoiceoverFromNarration"
          >
            <span
              v-if="voiceoverState === 'submitting' || voiceoverState === 'processing'"
              class="size-4 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
              aria-hidden="true"
            />
            {{ isVoiceoverUpgrade ? t('campaignResult.upgradeSubscription') : voiceoverState === 'submitting' || voiceoverState === 'processing' ? t('campaignResult.voiceoverGenerating') : t('campaignResult.generateAiVoiceover') }}
          </button>
        </div>
      </details>

      <div class="mt-6 space-y-4">
        <article
          v-for="(scene, index) in scenes"
          :key="`${sceneTime(scene)}-${index}`"
          class="nusa-card-soft rounded-2xl p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-white">
              {{ t('campaignResult.scene', { number: index + 1 }) }}
            </p>
            <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
              {{ sceneTime(scene) }}
            </span>
          </div>
          <dl class="mt-4 space-y-4 text-sm">
            <div>
              <dt class="font-semibold text-neutral-500">{{ t('campaignResult.voiceover') }}</dt>
              <dd class="mt-1 break-words leading-6 text-neutral-200">{{ scene.voiceover }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">
                {{ t('campaignResult.visualDirection') }}
              </dt>
              <dd class="mt-1 break-words leading-6 text-neutral-300">
                {{ scene.visual_direction }}
              </dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">{{ t('campaignResult.onScreenText') }}</dt>
              <dd class="mt-1 break-words font-medium text-emerald-300">
                {{ scene.on_screen_text }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.captions') }}
      </p>
      <div class="mt-3 space-y-3">
        <p
          v-for="caption in captions"
          :key="caption"
          class="whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200"
        >
          {{ caption }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.hashtags') }}
      </p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span
          v-for="hashtag in result.hashtags"
          :key="hashtag"
          class="break-all rounded-full border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs font-medium text-neutral-300"
        >
          {{ hashtag }}
        </span>
      </div>
    </section>

    <section class="rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.ctaOptions') }}
      </p>
      <ul class="mt-3 space-y-3">
        <li
          v-for="cta in ctaOptions"
          :key="cta"
          class="break-words text-base font-semibold leading-7 text-white"
        >
          {{ cta }}
        </li>
      </ul>
    </section>

    <section v-if="complianceNotes.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-300">
        {{ t('campaignResult.complianceNotes') }}
      </p>
      <ul class="mt-4 space-y-3">
        <li
          v-for="note in complianceNotes"
          :key="note"
          class="flex gap-3 text-sm leading-6 text-neutral-300"
        >
          <span class="text-amber-300" aria-hidden="true">!</span>
          <span class="break-words">{{ note }}</span>
        </li>
      </ul>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.practicalNotes') }}
      </p>
      <ul class="mt-4 space-y-3">
        <li
          v-for="note in usageNotes"
          :key="note"
          class="flex gap-3 text-sm leading-6 text-neutral-300"
        >
          <span class="text-emerald-400" aria-hidden="true">&#10003;</span>
          <span class="break-words">{{ note }}</span>
        </li>
      </ul>
    </section>

    <section v-if="hasProviderMetadata" class="nusa-card-soft rounded-3xl p-4 text-xs text-neutral-500">
      <p class="font-semibold uppercase tracking-[0.16em] text-neutral-400">
        {{ t('campaignResult.providerDetails') }}
      </p>
      <p class="mt-2">
        {{ providerMetadata?.provider ?? 'mock' }}<span v-if="providerMetadata?.model"> · {{ providerMetadata.model }}</span>
      </p>
      <p
        v-if="
          providerMetadata?.estimated_cost_usd !== undefined &&
          providerMetadata.estimated_cost_usd !== null
        "
        class="mt-1"
      >
        {{ t('campaignResult.estimatedProviderCost') }}: ${{ providerMetadata.estimated_cost_usd }}
      </p>
    </section>
  </div>
</template>