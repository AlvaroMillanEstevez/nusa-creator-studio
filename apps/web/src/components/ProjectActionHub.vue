<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateAiVoiceover, getProjectActions } from '@/services/projects'
import {
  generationOutput,
  isCampaignGenerationResult,
  isProductToVideoResult,
  type AiVoiceoverGenerationResponse,
  type CreatorGeneration,
  type ProjectAction,
  type ProjectActionKey,
  type ProjectActionStatus,
} from '@/types/project'

const props = defineProps<{
  projectId: number
  selectedGeneration: CreatorGeneration | null
  refreshKey?: string | number
  isGenerating?: boolean
}>()

const emit = defineEmits<{
  generated: [response: AiVoiceoverGenerationResponse]
}>()

const { t } = useTranslations()
const actions = ref<ProjectAction[]>([])
const isLoading = ref(false)
const isCreatingVoiceover = ref(false)
const loadError = ref('')
const actionError = ref('')

const projectActionKeys: ProjectActionKey[] = [
  'generate_campaign',
  'start_fashion_try_on',
  'start_motion_video',
]
const resultActionKeys: ProjectActionKey[] = [
  'generate_voiceover_from_script',
  'generate_voiceover_from_campaign',
  'create_video_ad',
  'start_video_ad',
  'avatar_presenter',
]

const projectActions = computed(() =>
  actions.value.filter((action) => projectActionKeys.includes(action.key)),
)
const resultActions = computed(() =>
  actions.value.filter((action) => resultActionKeys.includes(action.key)),
)

const selectedVoiceoverSource = computed(() => {
  const selected = props.selectedGeneration

  if (selected === null || selected.status !== 'completed') {
    return null
  }

  const output = generationOutput(selected)

  if (selected.job_type === 'generate_affiliate_campaign' && isCampaignGenerationResult(output)) {
    const text =
      cleanText(output.voiceover_ready_script) ??
      cleanText(output.main_script?.voiceover_script) ??
      cleanText(output.script?.voiceover_script) ??
      output.main_script?.scenes?.map((scene) => scene.voiceover).filter(Boolean).join('\n') ??
      output.script?.scenes?.map((scene) => scene.voiceover).filter(Boolean).join('\n')

    return cleanText(text) === null
      ? null
      : {
          generationId: selected.id,
          text: cleanText(text) ?? '',
        }
  }

  if (selected.job_type === 'generate_product_video_script' && isProductToVideoResult(output)) {
    const text = output.storyboard.map((scene) => scene.voiceover).filter(Boolean).join('\n')

    return cleanText(text) === null
      ? null
      : {
          generationId: selected.id,
          text: cleanText(text) ?? '',
        }
  }

  return null
})

const canCreateVoiceoverFromSelected = computed(
  () => selectedVoiceoverSource.value !== null && !props.isGenerating && !isCreatingVoiceover.value,
)

function cleanText(value: string | null | undefined): string | null {
  if (typeof value !== 'string') {
    return null
  }

  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

function translatedTitle(action: ProjectAction): string {
  return t('projectActions.actions.' + action.key + '.title')
}

function translatedDescription(action: ProjectAction): string {
  return t('projectActions.actions.' + action.key + '.description')
}

function statusLabel(status: ProjectActionStatus): string {
  return t('projectActions.status.' + status)
}

function statusClasses(status: ProjectActionStatus): string {
  if (status === 'available') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'configure' || status === 'needs_assets' || status === 'needs_source') {
    return 'border-amber-800 bg-amber-950 text-amber-300'
  }

  if (status === 'coming_soon') {
    return 'border-violet-900 bg-violet-950 text-violet-300'
  }

  return 'border-neutral-700 bg-neutral-900 text-neutral-400'
}

function isVoiceoverAction(action: ProjectAction): boolean {
  return (
    action.key === 'generate_voiceover_from_script' ||
    action.key === 'generate_voiceover_from_campaign'
  )
}

function actionHelp(action: ProjectAction): string {
  if (action.locked_reason) {
    return action.locked_reason
  }

  if (isVoiceoverAction(action) && selectedVoiceoverSource.value === null) {
    return t('projectActions.selectScriptFirst')
  }

  return action.reason ?? translatedDescription(action)
}

function actionButtonLabel(action: ProjectAction): string {
  if (isVoiceoverAction(action)) {
    return isCreatingVoiceover.value
      ? t('projectActions.creatingVoiceover')
      : t('projectActions.createVoiceoverFromScript')
  }

  if (action.primary_cta?.label) {
    return action.primary_cta.label
  }

  if (action.status === 'available') {
    return t('projectActions.useProjectContext')
  }

  return statusLabel(action.status)
}

function isActionImplemented(action: ProjectAction): boolean {
  return isVoiceoverAction(action)
}

function isActionEnabled(action: ProjectAction): boolean {
  if (!isActionImplemented(action)) {
    return false
  }

  return action.status === 'available' && canCreateVoiceoverFromSelected.value
}

async function loadActions(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const response = await getProjectActions(props.projectId)
    actions.value = Array.isArray(response.data) ? response.data : []
  } catch (error: unknown) {
    loadError.value =
      error instanceof ApiError
        ? error.message
        : t('projectActions.loadError')
  } finally {
    isLoading.value = false
  }
}

async function createVoiceoverFromSelected(): Promise<void> {
  const source = selectedVoiceoverSource.value

  if (source === null) {
    return
  }

  isCreatingVoiceover.value = true
  actionError.value = ''

  try {
    const response = await generateAiVoiceover(props.projectId, {
      source_generation_id: source.generationId,
      source_text: source.text,
    })
    emit('generated', response)
    await loadActions()
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      actionError.value = t('projectActions.insufficientCredits')
    } else {
      actionError.value =
        error instanceof ApiError
          ? error.message
          : t('projectActions.actionError')
    }
  } finally {
    isCreatingVoiceover.value = false
  }
}

function handleAction(action: ProjectAction): void {
  if (isVoiceoverAction(action)) {
    void createVoiceoverFromSelected()
  }
}

watch(
  () => [props.projectId, props.refreshKey],
  () => {
    void loadActions()
  },
  { immediate: true },
)
</script>

<template>
  <section class="mt-8 space-y-5" data-testid="project-action-hub">
    <div class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('projectActions.kicker') }}
      </p>
      <h2 class="mt-2 text-2xl font-bold">{{ t('projectActions.title') }}</h2>
      <p class="mt-3 text-sm leading-6 text-neutral-400">
        {{ t('projectActions.body') }}
      </p>

      <div v-if="isLoading" class="mt-5 space-y-3" aria-live="polite">
        <div class="h-20 animate-pulse rounded-2xl bg-neutral-900" />
        <div class="h-20 animate-pulse rounded-2xl bg-neutral-900" />
      </div>

      <div
        v-else-if="loadError"
        role="alert"
        class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm leading-6 text-rose-100"
      >
        {{ loadError }}
        <button
          type="button"
          class="mt-3 block rounded-xl bg-white px-4 py-2 text-xs font-semibold text-neutral-950"
          @click="loadActions"
        >
          {{ t('common.tryAgain') }}
        </button>
      </div>

      <div v-else class="mt-6 space-y-6">
        <div>
          <h3 class="text-sm font-semibold text-neutral-100">
            {{ t('projectActions.continueProject') }}
          </h3>
          <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <article
              v-for="action in projectActions"
              :key="action.key"
              class="nusa-card-soft rounded-2xl p-4"
              :data-testid="'project-action-' + action.key"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <h4 class="break-words text-sm font-semibold text-white">
                    {{ translatedTitle(action) }}
                  </h4>
                  <p class="mt-2 text-xs leading-5 text-neutral-500">
                    {{ translatedDescription(action) }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                  :class="statusClasses(action.status)"
                >
                  {{ statusLabel(action.status) }}
                </span>
              </div>
              <p class="mt-3 text-xs leading-5 text-neutral-400">
                {{ actionHelp(action) }}
              </p>
            </article>
          </div>
        </div>

        <div>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 class="text-sm font-semibold text-neutral-100">
                {{ t('projectActions.continueResult') }}
              </h3>
              <p class="mt-1 text-xs leading-5 text-neutral-500">
                {{ t('projectActions.cleanContentRule') }}
              </p>
            </div>
          </div>

          <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <article
              v-for="action in resultActions"
              :key="action.key"
              class="rounded-2xl border p-4"
              :class="isActionEnabled(action) ? 'border-emerald-800 bg-emerald-950/20' : 'border-neutral-800 bg-neutral-950/60'"
              :data-testid="'project-action-' + action.key"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <h4 class="break-words text-sm font-semibold text-white">
                    {{ translatedTitle(action) }}
                  </h4>
                  <p class="mt-2 text-xs leading-5 text-neutral-500">
                    {{ translatedDescription(action) }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                  :class="statusClasses(action.status)"
                >
                  {{ statusLabel(action.status) }}
                </span>
              </div>

              <p class="mt-3 text-xs leading-5 text-neutral-400">
                {{ actionHelp(action) }}
              </p>

              <button
                type="button"
                class="mt-4 min-h-11 w-full rounded-2xl px-4 text-sm font-semibold transition"
                :class="isActionEnabled(action) ? 'bg-white text-neutral-950 hover:bg-emerald-300' : 'cursor-not-allowed border border-neutral-800 text-neutral-500'"
                :disabled="!isActionEnabled(action)"
                @click="handleAction(action)"
              >
                {{ actionButtonLabel(action) }}
              </button>
            </article>
          </div>
        </div>
      </div>

      <p
        v-if="actionError"
        role="alert"
        class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm leading-6 text-rose-100"
      >
        {{ actionError }}
      </p>
    </div>
  </section>
</template>