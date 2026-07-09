<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateAiVoiceover, generateCampaignProject, getProjectActions } from '@/services/projects'
import { useAuthStore } from '@/stores/auth'
import {
  campaignLanguageOptions,
  generationOutput,
  isAutoVideoEditorResult,
  isCampaignGenerationResult,
  isProductToVideoResult,
  isVideoToCampaignResult,
  voiceoverPaceOptions,
  voiceoverStyleOptions,
  voiceoverToneOptions,
  type AiVoiceoverGenerationResponse,
  type CampaignGenerationResponse,
  type CreatorGeneration,
  type GenerateAiVoiceoverPayload,
  type ProjectAction,
  type ProjectActionKey,
  type ProjectActionRecommendedSource,
} from '@/types/project'

const VOICEOVER_MAX_CHARACTERS = 1000

type ProjectActionGenerationResponse = AiVoiceoverGenerationResponse | CampaignGenerationResponse

type VoiceoverSourceMode = 'recommended' | 'manual'

interface VoiceoverFormState {
  sourceMode: VoiceoverSourceMode
  selectedSourceId: number | null
  manualSourceText: string
  language: string
  tone: string
  pace: string
  style: string
  notes: string
}

const props = defineProps<{
  projectId: number
  selectedGeneration: CreatorGeneration | null
  latestCampaignGeneration?: CreatorGeneration | null
  latestVoiceoverGeneration?: CreatorGeneration | null
  campaignGenerationCount?: number
  refreshKey?: string | number
  isGenerating?: boolean
}>()

const emit = defineEmits<{
  generated: [response: ProjectActionGenerationResponse]
}>()

const { t } = useTranslations()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const actions = ref<ProjectAction[]>([])
const activeAction = ref<ProjectAction | null>(null)
const isLoading = ref(false)
const isSubmitting = ref(false)
const loadError = ref('')
const actionError = ref('')
const voiceoverForm = ref<VoiceoverFormState>({
  sourceMode: 'recommended',
  selectedSourceId: null,
  manualSourceText: '',
  language: 'english',
  tone: 'natural',
  pace: 'medium',
  style: 'natural_creator',
  notes: '',
})

const projectActionKeys: ProjectActionKey[] = [
  'generate_campaign',
  'start_fashion_try_on',
  'start_motion_video',
  'generate_video_to_campaign',
  'generate_auto_video_edit',
]
const resultActionKeys: ProjectActionKey[] = [
  'generate_voiceover_from_script',
  'generate_voiceover_from_campaign',
  'create_video_ad',
  'start_video_ad',
  'avatar_presenter',
]


const campaignGenerationForView = computed(() => {
  if (
    props.latestCampaignGeneration?.job_type === 'generate_affiliate_campaign' &&
    props.latestCampaignGeneration.status === 'completed'
  ) {
    return props.latestCampaignGeneration
  }

  if (
    props.selectedGeneration?.job_type === 'generate_affiliate_campaign' &&
    props.selectedGeneration.status === 'completed'
  ) {
    return props.selectedGeneration
  }

  return null
})
const campaignResultRoute = computed(() => {
  const generation = campaignGenerationForView.value

  return generation === null
    ? null
    : `/projects/${String(props.projectId)}/generations/${String(generation.id)}`
})
const voiceoverGenerationForView = computed(() => {
  if (
    props.latestVoiceoverGeneration?.job_type === 'generate_ai_voiceover' &&
    props.latestVoiceoverGeneration.status === 'completed'
  ) {
    return props.latestVoiceoverGeneration
  }

  if (
    props.selectedGeneration?.job_type === 'generate_ai_voiceover' &&
    props.selectedGeneration.status === 'completed'
  ) {
    return props.selectedGeneration
  }

  return null
})
const voiceoverResultRoute = computed(() => {
  const generation = voiceoverGenerationForView.value

  return generation === null
    ? null
    : `/projects/${String(props.projectId)}/generations/${String(generation.id)}`
})
const projectActions = computed(() =>
  actions.value.filter((action) => projectActionKeys.includes(action.key)),
)
const resultActions = computed(() =>
  actions.value.filter((action) => resultActionKeys.includes(action.key)),
)
const actionSections = computed(() => [
  {
    key: 'project',
    title: t('projectActions.continueProject'),
    description: t('projectActions.continueFromProjectContext'),
    actions: projectActions.value,
  },
  {
    key: 'result',
    title: t('projectActions.continueResult'),
    description: t('projectActions.cleanContentRule'),
    actions: resultActions.value,
  },
].filter((section) => section.actions.length > 0))

const hasInternalOrUnlimitedAccess = computed(() => {
  const user = auth.user

  return user?.is_unlimited === true ||
    user?.role === 'admin' ||
    user?.role === 'internal_creator' ||
    user?.plan_key === 'internal_unlimited'
})

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
          generation_id: selected.id,
          job_type: selected.job_type,
          title: t('projectActions.useSelectedGeneration'),
          created_at: selected.created_at,
          excerpt: cleanText(text) ?? '',
          fullText: cleanText(text) ?? '',
        }
  }

  if (selected.job_type === 'generate_product_video_script' && isProductToVideoResult(output)) {
    const text = output.storyboard.map((scene) => scene.voiceover).filter(Boolean).join('\n')

    return cleanText(text) === null
      ? null
      : {
          generation_id: selected.id,
          job_type: selected.job_type,
          title: t('projectActions.useSelectedGeneration'),
          created_at: selected.created_at,
          excerpt: cleanText(text) ?? '',
          fullText: cleanText(text) ?? '',
        }
  }


  if (selected.job_type === 'generate_video_to_campaign' && isVideoToCampaignResult(output)) {
    const text = cleanText(output.narration_voice_script)

    return text === null
      ? null
      : {
          generation_id: selected.id,
          job_type: selected.job_type,
          title: t('projectActions.useSelectedGeneration'),
          created_at: selected.created_at,
          excerpt: text,
          fullText: text,
        }
  }

  if (selected.job_type === 'generate_auto_video_edit' && isAutoVideoEditorResult(output)) {
    const text = cleanText(output.voiceover_script) ?? cleanText(output.audio_mix_plan.mix_notes.join('\n'))

    return text === null
      ? null
      : {
          generation_id: selected.id,
          job_type: selected.job_type,
          title: t('projectActions.useSelectedGeneration'),
          created_at: selected.created_at,
          excerpt: text,
          fullText: text,
        }
  }

  return null
})

const activeSourceOptions = computed(() => {
  if (activeAction.value === null || !isVoiceoverAction(activeAction.value)) {
    return []
  }

  return sourceOptionsFor(activeAction.value)
})
const selectedSourcePreview = computed(() => {
  if (voiceoverForm.value.sourceMode === 'manual') {
    return voiceoverForm.value.manualSourceText.trim()
  }

  const selectedId = voiceoverForm.value.selectedSourceId

  if (selectedId === null) {
    return ''
  }

  if (selectedVoiceoverSource.value?.generation_id === selectedId) {
    return selectedVoiceoverSource.value.fullText
  }

  return activeSourceOptions.value.find((source) => source.generation_id === selectedId)?.excerpt ?? ''
})

const voiceoverCharacterCount = computed(() => selectedSourcePreview.value.length)
const estimatedDurationSeconds = computed(() => {
  const text = selectedSourcePreview.value.trim()

  if (!text) {
    return null
  }

  const words = text.split(/\s+/).filter(Boolean).length

  return Math.max(5, Math.round(words / 2.4))
})
const voiceoverValidationError = computed(() => {
  if (activeAction.value === null || !isVoiceoverAction(activeAction.value)) {
    return ''
  }

  if (voiceoverForm.value.sourceMode === 'recommended' && voiceoverForm.value.selectedSourceId !== null) {
    return ''
  }

  if (voiceoverForm.value.manualSourceText.trim() === '') {
    return t('projectActions.sourceRequired')
  }

  if (voiceoverCharacterCount.value > VOICEOVER_MAX_CHARACTERS) {
    return t('projectActions.sourceTooLong', { count: VOICEOVER_MAX_CHARACTERS })
  }

  return ''
})

function cleanText(value: string | null | undefined): string | null {
  if (typeof value !== 'string') {
    return null
  }

  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

function translatedTitle(action: ProjectAction): string {
  const key = 'projectActions.actions.' + action.key + '.title'
  const translated = t(key)

  return translated === key ? action.title : translated
}

function translatedDescription(action: ProjectAction): string {
  const key = 'projectActions.actions.' + action.key + '.description'
  const translated = t(key)

  return translated === key ? action.description : translated
}

function launcherTitle(action: ProjectAction): string {
  if (isVoiceoverAction(action)) {
    return t('projectActions.createVoiceover')
  }

  return translatedTitle(action)
}

function isVoiceoverAction(action: ProjectAction): boolean {
  return (
    action.key === 'generate_voiceover_from_script' ||
    action.key === 'generate_voiceover_from_campaign'
  )
}

function isCampaignAction(action: ProjectAction): boolean {
  return action.key === 'generate_campaign'
}

function isTryOnAction(action: ProjectAction): boolean {
  return action.key === 'start_fashion_try_on'
}

function isMotionAction(action: ProjectAction): boolean {
  return action.key === 'start_motion_video'
}

function isVideoToCampaignAction(action: ProjectAction): boolean {
  return action.key === 'generate_video_to_campaign'
}

function isAutoVideoEditorAction(action: ProjectAction): boolean {
  return action.key === 'generate_auto_video_edit'
}

function isComingSoon(action: ProjectAction): boolean {
  return action.status === 'coming_soon' || action.action_mode === 'coming_soon'
}

function isUpgradeAction(action: ProjectAction): boolean {
  return action.upgrade_required === true || action.action_mode === 'upgrade'
}

function isUnavailableAction(action: ProjectAction): boolean {
  return action.status === 'unavailable' && !isUpgradeAction(action)
}

function serviceTierLabel(action: ProjectAction): string {
  if (action.service_tier === 'basic') {
    return t('projectActions.basicWorkflow')
  }

  if (action.service_tier === 'advanced') {
    return t('projectActions.advancedWorkflow')
  }

  if (action.service_tier === 'premium') {
    return t('projectActions.premiumWorkflow')
  }

  return action.service_tier ?? t('tools.serviceTier')
}

function serviceTierClasses(action: ProjectAction): string {
  if (action.service_tier === 'premium') {
    return 'border-violet-800 bg-violet-950/70 text-violet-200'
  }

  if (action.service_tier === 'advanced') {
    return 'border-cyan-800 bg-cyan-950/70 text-cyan-200'
  }

  return 'border-emerald-800 bg-emerald-950/70 text-emerald-200'
}

function creditCostBadge(action: ProjectAction): string {
  if (hasInternalOrUnlimitedAccess.value && action.credit_cost !== null && action.user_has_access !== false) {
    return t('projectActions.noCreditCharge')
  }

  if (action.credit_cost === null) {
    return t('tools.creditCostComingSoon')
  }

  if (action.credit_cost === 0) {
    return t('projectActions.noCreditCharge')
  }

  return action.credit_cost === 1
    ? t('projectActions.oneCredit')
    : t('projectActions.manyCredits', { credits: action.credit_cost })
}

function actionCostSentence(action: ProjectAction): string {
  if (hasInternalOrUnlimitedAccess.value && action.credit_cost !== null && action.user_has_access !== false) {
    return t('projectActions.noCreditCharge')
  }

  if (action.credit_cost === null) {
    return t('tools.creditCostComingSoon')
  }

  if (action.credit_cost === 0) {
    return t('projectActions.noCreditCharge')
  }

  return t('projectActions.actionCosts', { credits: action.credit_cost })
}
function accessBadgeLabel(action: ProjectAction): string {
  if (isComingSoon(action)) {
    return t('projectActions.status.coming_soon')
  }

  if (isUpgradeAction(action)) {
    return t('projectActions.upgradeRequired')
  }

  if (action.user_has_access === true && hasInternalOrUnlimitedAccess.value) {
    return auth.user?.is_unlimited === true
      ? t('projectActions.unlimitedAccess')
      : t('projectActions.internalAccess')
  }

  if (action.user_has_access === true) {
    return t('projectActions.includedInPlan')
  }

  return t('projectActions.status.unavailable')
}

function accessBadgeClasses(action: ProjectAction): string {
  if (isComingSoon(action)) {
    return 'border-violet-800 bg-violet-950/70 text-violet-200'
  }

  if (isUpgradeAction(action)) {
    return 'border-amber-800 bg-amber-950/70 text-amber-200'
  }

  if (action.user_has_access === true) {
    return 'border-emerald-800 bg-emerald-950/70 text-emerald-200'
  }

  return 'border-neutral-700 bg-neutral-900 text-neutral-400'
}

function cardNote(action: ProjectAction): string | null {
  if (isUpgradeAction(action)) {
    return action.locked_reason ?? t('projectActions.upgradeRequired')
  }

  if (isComingSoon(action)) {
    return t('projectActions.comingSoonBody')
  }

  if (isUnavailableAction(action)) {
    return action.locked_reason ?? t('projectActions.unavailableBody')
  }

  if ((isVideoToCampaignAction(action) || isAutoVideoEditorAction(action)) && action.reason) {
    return action.reason
  }

  if (action.status === 'configure') {
    return t('projectActions.setupRequired')
  }

  return null
}

function actionButtonLabel(action: ProjectAction): string {
  if (isUpgradeAction(action)) {
    return t('projectActions.upgradeSubscription')
  }

  if (isComingSoon(action)) {
    return t('projectActions.status.coming_soon')
  }

  if (isVoiceoverAction(action) && action.primary_cta?.label?.includes('another')) {
    return t('projectActions.createAnotherVoiceover')
  }

  if (isVoiceoverAction(action)) {
    return t('projectActions.createVoiceover')
  }

  if (isCampaignAction(action) && campaignGenerationForView.value !== null) {
    return t('projectActions.generateAnotherAffiliateCampaign')
  }

  if (isCampaignAction(action) && action.primary_cta?.label?.includes('another')) {
    return t('projectActions.generateAnotherCampaignVersion')
  }

  if (isTryOnAction(action)) {

    return t('projectActions.setupTryOn')
  }

  if (isMotionAction(action)) {
    return t('projectActions.configureMotionVideo')
  }

  if (isVideoToCampaignAction(action)) {
    return t('projectActions.generateVideoCampaign')
  }

  if (isAutoVideoEditorAction(action)) {
    return t('projectActions.configureAutoVideoEditor')
  }

  return action.primary_cta?.label ?? t('projectActions.configureAction')
}

function isActionButtonDisabled(action: ProjectAction): boolean {
  return isSubmitting.value || props.isGenerating === true || isComingSoon(action) || isUnavailableAction(action)
}

function isActionSubmitting(action: ProjectAction): boolean {
  return isSubmitting.value && activeAction.value?.key === action.key
}


function videoToCampaignActionRoute() {
  return {
    name: 'project-video-to-campaign-action',
    params: { projectId: String(props.projectId) },
  }
}

function autoVideoEditorActionRoute() {
  return {
    name: 'project-auto-video-editor-action',
    params: { projectId: String(props.projectId) },
  }
}

function voiceoverActionRoute(action: ProjectAction) {
  const query: Record<string, string> = {}
  const sourceId = selectedVoiceoverSource.value?.generation_id ?? action.source_generation_id

  if (sourceId !== null && sourceId !== undefined) {
    query.source_generation_id = String(sourceId)
  }

  if (route.name === 'generation-result') {
    query.return_to = route.fullPath
  }

  return {
    name: 'project-voiceover-action',
    params: { projectId: String(props.projectId) },
    query,
  }
}

function sourceOptionsFor(action: ProjectAction): Array<ProjectActionRecommendedSource & { fullText?: string }> {
  const sources = [...(action.recommended_sources ?? [])]
  const selected = selectedVoiceoverSource.value

  if (selected && !sources.some((source) => source.generation_id === selected.generation_id)) {
    sources.unshift(selected)
  }

  return sources
}

function openLauncher(action: ProjectAction): void {
  activeAction.value = action
  actionError.value = ''

  if (isVoiceoverAction(action)) {
    const sources = sourceOptionsFor(action)
    const preferred = selectedVoiceoverSource.value
      ? sources.find((source) => source.generation_id === selectedVoiceoverSource.value?.generation_id)
      : sources[0]

    voiceoverForm.value = {
      sourceMode: preferred ? 'recommended' : 'manual',
      selectedSourceId: preferred?.generation_id ?? null,
      manualSourceText: '',
      language: 'english',
      tone: 'natural',
      pace: 'medium',
      style: 'natural_creator',
      notes: '',
    }
  }
}

function closeLauncher(): void {
  activeAction.value = null
  actionError.value = ''
}

function scrollToProjectAssets(): void {
  const element = document.getElementById('project-assets')

  if (typeof element?.scrollIntoView === 'function') {
    element.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

async function handleAction(action: ProjectAction): Promise<void> {
  actionError.value = ''

  if (isUpgradeAction(action)) {
    await router.push(action.primary_cta?.route ?? '/pricing')
    return
  }

  if (isComingSoon(action) || isUnavailableAction(action)) {
    return
  }

  if (isVoiceoverAction(action)) {
    await router.push(voiceoverActionRoute(action))
    return
  }

  if (isVideoToCampaignAction(action)) {
    await router.push(videoToCampaignActionRoute())
    return
  }

  if (isAutoVideoEditorAction(action)) {
    await router.push(autoVideoEditorActionRoute())
    return
  }

  if (isCampaignAction(action) && action.status === 'available' && action.action_mode !== 'configure') {
    await submitCampaign(action)
    return
  }

  openLauncher(action)
}

async function submitCampaign(action: ProjectAction): Promise<void> {
  activeAction.value = action
  isSubmitting.value = true
  actionError.value = ''

  try {
    const response = await generateCampaignProject(props.projectId)
    emit('generated', response)
    await loadActions()
    closeLauncher()
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      actionError.value = t('projectActions.insufficientCredits')
    } else {
      actionError.value = error instanceof ApiError ? error.message : t('projectActions.actionError')
    }
  } finally {
    isSubmitting.value = false
  }
}
async function submitVoiceover(): Promise<void> {
  const action = activeAction.value

  if (action === null || !isVoiceoverAction(action) || voiceoverValidationError.value) {
    return
  }

  const payload: GenerateAiVoiceoverPayload = {
    language: voiceoverForm.value.language,
    tone: voiceoverForm.value.tone,
    pace: voiceoverForm.value.pace,
    style: voiceoverForm.value.style,
  }
  const notes = voiceoverForm.value.notes.trim()

  if (notes) {
    payload.notes = notes
  }

  if (voiceoverForm.value.sourceMode === 'recommended' && voiceoverForm.value.selectedSourceId !== null) {
    payload.source_generation_id = voiceoverForm.value.selectedSourceId

    if (selectedVoiceoverSource.value?.generation_id === voiceoverForm.value.selectedSourceId) {
      payload.source_text = selectedVoiceoverSource.value.fullText
    }
  } else {
    payload.source_text = voiceoverForm.value.manualSourceText.trim()
  }

  isSubmitting.value = true
  actionError.value = ''

  try {
    const response = await generateAiVoiceover(props.projectId, payload)
    emit('generated', response)
    await loadActions()
    closeLauncher()
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      actionError.value = t('projectActions.insufficientCredits')
    } else {
      actionError.value = error instanceof ApiError ? error.message : t('projectActions.actionError')
    }
  } finally {
    isSubmitting.value = false
  }
}

async function loadActions(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const response = await getProjectActions(props.projectId)
    actions.value = Array.isArray(response.data) ? response.data : []
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : t('projectActions.loadError')
  } finally {
    isLoading.value = false
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
        <div class="h-28 animate-pulse rounded-2xl bg-neutral-900" />
        <div class="h-28 animate-pulse rounded-2xl bg-neutral-900" />
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

      <div v-else class="mt-6 space-y-7">
        <section v-for="section in actionSections" :key="section.key">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 class="text-sm font-semibold text-neutral-100">
                {{ section.title }}
              </h3>
              <p class="mt-1 text-xs leading-5 text-neutral-500">
                {{ section.description }}
              </p>
            </div>
          </div>

          <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <article
              v-for="action in section.actions"
              :key="action.key"
              class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4 transition"
              :class="isUpgradeAction(action) ? 'border-amber-900/70' : action.user_has_access === true && !isComingSoon(action) ? 'border-emerald-900/60' : 'border-neutral-800'"
              :data-testid="'project-action-' + action.key"
            >
              <div class="flex flex-col gap-3">
                <div class="min-w-0">
                  <h4 class="break-words text-sm font-semibold text-white">
                    {{ translatedTitle(action) }}
                  </h4>
                  <p class="mt-2 text-xs leading-5 text-neutral-500">
                    {{ translatedDescription(action) }}
                  </p>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="serviceTierClasses(action)"
                  >
                    {{ serviceTierLabel(action) }}
                  </span>
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ creditCostBadge(action) }}
                  </span>
                  <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="accessBadgeClasses(action)"
                  >
                    {{ accessBadgeLabel(action) }}
                  </span>
                </div>
              </div>

              <p v-if="cardNote(action)" class="mt-3 text-xs leading-5 text-neutral-400">
                {{ cardNote(action) }}
              </p>

              <RouterLink
                v-if="isCampaignAction(action) && campaignResultRoute"
                :to="campaignResultRoute"
                class="mt-4 flex min-h-11 w-full items-center justify-center rounded-2xl border border-emerald-800 bg-emerald-950/40 px-4 text-sm font-semibold text-emerald-100 transition hover:border-emerald-500 hover:bg-emerald-900/50"
                data-testid="view-generated-campaign"
              >
                {{ (campaignGenerationCount ?? 0) > 1 ? t('projectActions.viewLatestCampaign') : t('projectActions.viewGeneratedCampaign') }}
              </RouterLink>

              <RouterLink
                v-if="isVoiceoverAction(action) && voiceoverResultRoute"
                :to="voiceoverResultRoute"
                class="mt-4 flex min-h-11 w-full items-center justify-center rounded-2xl border border-sky-800 bg-sky-950/40 px-4 text-sm font-semibold text-sky-100 transition hover:border-sky-500 hover:bg-sky-900/50"
                data-testid="view-latest-voiceover"
              >
                {{ t('projectActions.viewLatestVoiceover') }}
              </RouterLink>

              <button
                type="button"
                class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold transition"
                :class="isActionButtonDisabled(action) ? 'cursor-not-allowed border border-neutral-800 text-neutral-500' : isUpgradeAction(action) ? 'border border-amber-700 bg-amber-950/40 text-amber-100 hover:bg-amber-900/50' : 'bg-white text-neutral-950 hover:bg-emerald-300'"
                :disabled="isActionButtonDisabled(action)"
                @click="handleAction(action)"
              >
                <span
                  v-if="isActionSubmitting(action)"
                  class="size-4 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
                  aria-hidden="true"
                />
                {{ isActionSubmitting(action) ? t('projectActions.generating') : actionButtonLabel(action) }}
              </button>
            </article>
          </div>
        </section>
      </div>
      <div
        v-if="activeAction"
        class="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/80 px-0 pt-8 backdrop-blur-sm sm:items-center sm:px-6 sm:py-8"
        data-testid="project-action-launcher"
        role="dialog"
        aria-modal="true"
      >
        <button
          type="button"
          class="absolute inset-0 cursor-default"
          :aria-label="t('projectActions.closeLauncher')"
          @click="closeLauncher"
        />
        <div class="relative max-h-[92vh] w-full overflow-y-auto rounded-t-[2rem] border border-emerald-900/60 bg-neutral-950 p-4 shadow-2xl shadow-emerald-950/40 sm:max-w-3xl sm:rounded-[2rem] sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('projectActions.actionLauncher') }}
            </p>
            <h3 class="mt-2 text-xl font-semibold text-white">
              {{ launcherTitle(activeAction) }}
            </h3>
            <div class="mt-3 flex flex-wrap gap-2">
              <span
                class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                :class="serviceTierClasses(activeAction)"
              >
                {{ serviceTierLabel(activeAction) }}
              </span>
              <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                {{ creditCostBadge(activeAction) }}
              </span>
            </div>
          </div>
          <button
            type="button"
            class="min-h-10 rounded-xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-300 transition hover:bg-neutral-900"
            @click="closeLauncher"
          >
            {{ t('projectActions.close') }}
          </button>
        </div>

        <p class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">
          {{ actionCostSentence(activeAction) }}
        </p>

        <template v-if="isCampaignAction(activeAction)">
          <div class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
            <p>{{ t('projectActions.campaignLauncherBody') }}</p>
            <ul v-if="activeAction.requirements?.length" class="space-y-2">
              <li
                v-for="requirement in activeAction.requirements"
                :key="requirement.key"
                class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3"
              >
                <span class="font-semibold text-neutral-100">{{ requirement.label }}:</span>
                {{ requirement.satisfied ? t('common.completed') : requirement.help_text }}
              </li>
            </ul>
          </div>
        </template>

        <template v-else-if="isVoiceoverAction(activeAction)">
          <div class="mt-5 space-y-4">
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
                  v-model="voiceoverForm.sourceMode"
                  type="radio"
                  value="recommended"
                  class="mt-1 accent-emerald-400"
                >
                <span>
                  <span class="block font-semibold text-neutral-100">{{ t('projectActions.useSelectedGeneration') }}</span>
                  <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('projectActions.selectedSourceHelp') }}</span>
                </span>
              </label>

              <select
                v-if="voiceoverForm.sourceMode === 'recommended' && activeSourceOptions.length"
                v-model.number="voiceoverForm.selectedSourceId"
                class="nusa-select"
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
                  v-model="voiceoverForm.sourceMode"
                  type="radio"
                  value="manual"
                  class="mt-1 accent-emerald-400"
                >
                <span>
                  <span class="block font-semibold text-neutral-100">{{ t('projectActions.manualSourceText') }}</span>
                  <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('projectActions.noSavedSource') }}</span>
                </span>
              </label>

              <textarea
                v-if="voiceoverForm.sourceMode === 'manual'"
                v-model="voiceoverForm.manualSourceText"
                class="nusa-textarea min-h-36"
                :placeholder="t('projectActions.sourceTextPlaceholder')"
              />
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="nusa-label">{{ t('projectActions.language') }}</span>
                <select v-model="voiceoverForm.language" class="nusa-select mt-2">
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
                <select v-model="voiceoverForm.tone" class="nusa-select mt-2">
                  <option v-for="option in voiceoverToneOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="nusa-label">{{ t('projectActions.pace') }}</span>
                <select v-model="voiceoverForm.pace" class="nusa-select mt-2">
                  <option v-for="option in voiceoverPaceOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <label class="block sm:col-span-2">
                <span class="nusa-label">{{ t('projectActions.style') }}</span>
                <select v-model="voiceoverForm.style" class="nusa-select mt-2">
                  <option v-for="option in voiceoverStyleOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block sm:col-span-2">
                <span class="nusa-label">{{ t('projectActions.optionalNotes') }}</span>
                <textarea v-model="voiceoverForm.notes" class="nusa-textarea mt-2 min-h-24" />
              </label>
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
              <p class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-neutral-300">
                {{ t('projectActions.characterCount') }}: {{ voiceoverCharacterCount }} / {{ VOICEOVER_MAX_CHARACTERS }}
              </p>
              <p class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-neutral-300">
                {{ t('projectActions.estimatedDuration') }}:
                {{ estimatedDurationSeconds === null ? t('projectActions.durationAfterGeneration') : estimatedDurationSeconds + 's' }}
              </p>
            </div>

            <p v-if="voiceoverValidationError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/40 p-3 text-sm leading-6 text-rose-100">
              {{ voiceoverValidationError }}
            </p>
          </div>
        </template>

        <template v-else-if="isTryOnAction(activeAction) || isMotionAction(activeAction)">
          <div class="mt-5 space-y-4">
            <p class="text-sm leading-6 text-neutral-300">
              {{ isTryOnAction(activeAction) ? t('projectActions.tryOnSetupBody') : t('projectActions.motionSetupBody') }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
              <article
                v-for="requirement in activeAction.requirements ?? []"
                :key="requirement.key"
                class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3"
              >
                <p class="text-sm font-semibold text-neutral-100">{{ requirement.label }}</p>
                <p class="mt-1 text-xs leading-5 text-neutral-500">
                  {{ requirement.satisfied ? t('projectActions.requirementReady') : requirement.help_text }}
                </p>
              </article>
            </div>

            <div v-if="activeAction.recommended_assets?.length" class="rounded-2xl border border-emerald-900/60 bg-emerald-950/20 p-3">
              <p class="text-sm font-semibold text-emerald-100">{{ t('projectActions.recommendedAssets') }}</p>
              <ul class="mt-2 space-y-1 text-xs leading-5 text-emerald-200/80">
                <li v-for="asset in activeAction.recommended_assets" :key="asset.media_asset_id">
                  {{ asset.original_name ?? 'Project asset #' + asset.media_asset_id }} · {{ asset.usage_role ?? asset.type }}
                </li>
              </ul>
            </div>

            <button
              type="button"
              class="min-h-11 w-full rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-900"
              @click="scrollToProjectAssets"
            >
              {{ t('projectActions.openProjectAssets') }}
            </button>
          </div>
        </template>

        <p
          v-if="actionError"
          role="alert"
          class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm leading-6 text-rose-100"
        >
          {{ actionError }}
        </p>

        <div
          v-if="isCampaignAction(activeAction) || isVoiceoverAction(activeAction)"
          class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
          <button
            type="button"
            class="min-h-11 rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-300 transition hover:bg-neutral-900"
            @click="closeLauncher"
          >
            {{ t('projectActions.cancelSetup') }}
          </button>
          <button
            type="button"
            :disabled="isSubmitting || props.isGenerating || (isVoiceoverAction(activeAction) && !!voiceoverValidationError)"
            class="flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400"
            @click="isVoiceoverAction(activeAction) ? submitVoiceover() : submitCampaign(activeAction)"
          >
            <span
              v-if="isSubmitting"
              class="size-4 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
              aria-hidden="true"
            />
            {{ isSubmitting ? t('projectActions.generating') : isVoiceoverAction(activeAction) ? t('projectActions.generateVoiceover') : t('projectActions.startGeneration') }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </section>
</template>