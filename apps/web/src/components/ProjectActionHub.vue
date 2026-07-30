<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import {
  workflowDefinitionsForFamily,
  workflowFamilyDefinitions,
  type BetaWorkflowDefinition,
  type WorkflowFamilyDefinition,
} from '@/config/workflows'
import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateCampaignProject, getProjectActions } from '@/services/projects'
import { useAuthStore } from '@/stores/auth'
import type {
  CampaignGenerationResponse,
  CreatorGeneration,
  ProjectAction,
  ProjectActionKey,
} from '@/types/project'
import {
  isFootageAnalysisGeneration,
  isMotionCampaignCompatible,
  motionFinalVideoAssetId,
  sourceVideoAssetIdFromAnalysis,
} from '@/utils/workflowLineage'

const props = defineProps<{
  projectId: number
  selectedGeneration: CreatorGeneration | null
  latestCampaignGeneration?: CreatorGeneration | null
  latestVoiceoverGeneration?: CreatorGeneration | null
  latestVideoAnalysisGeneration?: CreatorGeneration | null
  latestFashionTryOnGeneration?: CreatorGeneration | null
  latestMotionAiVideoGeneration?: CreatorGeneration | null
  latestAutoVideoEditorGeneration?: CreatorGeneration | null
  campaignGenerationCount?: number
  refreshKey?: string | number
  isGenerating?: boolean
}>()

const emit = defineEmits<{
  generated: [response: CampaignGenerationResponse]
}>()

const { t } = useTranslations()
const router = useRouter()
const auth = useAuthStore()
const actions = ref<ProjectAction[]>([])
const activeAction = ref<ProjectAction | null>(null)
const isLoading = ref(false)
const isSubmitting = ref(false)
const loadError = ref('')
const actionError = ref('')

interface WorkflowActionCard {
  workflow: BetaWorkflowDefinition
  action: ProjectAction
}

interface WorkflowActionSection {
  family: WorkflowFamilyDefinition
  cards: WorkflowActionCard[]
}

const hasInternalOrUnlimitedAccess = computed(() => {
  const user = auth.user

  return user?.is_unlimited === true ||
    user?.role === 'admin' ||
    user?.role === 'internal_creator' ||
    user?.plan_key === 'internal_unlimited'
})

const actionSections = computed<WorkflowActionSection[]>(() =>
  workflowFamilyDefinitions
    .map((family) => ({
      family,
      cards: workflowDefinitionsForFamily(family.key)
        .map((workflow) => workflowActionCard(workflow))
        .filter((card): card is WorkflowActionCard => card !== null),
    }))
    .filter((section) => section.cards.length > 0),
)

function workflowActionCard(workflow: BetaWorkflowDefinition): WorkflowActionCard | null {
  const action = actionForWorkflow(workflow)

  return action === null ? null : { workflow, action }
}

function actionForWorkflow(workflow: BetaWorkflowDefinition): ProjectAction | null {
  const key = workflow.projectActionKey

  if (key === null) {
    return null
  }

  if (key === 'create_video_ad') {
    return findAction('create_video_ad') ?? findAction('start_video_ad') ?? placeholderAction(workflow, 'create_video_ad')
  }

  return findAction(key) ?? (workflow.status === 'coming_soon' ? placeholderAction(workflow, key) : null)
}

function findAction(key: ProjectActionKey): ProjectAction | null {
  return actions.value.find((action) => action.key === key) ?? null
}

function placeholderAction(workflow: BetaWorkflowDefinition, key: ProjectActionKey): ProjectAction {
  return {
    key,
    title: t(workflowTitleKey(workflow)),
    description: t(workflowDescriptionKey(workflow)),
    service_tier: null,
    output_type: null,
    status: 'coming_soon',
    credit_cost: null,
    user_has_access: false,
    upgrade_required: false,
    access_label: null,
    locked_reason: null,
    action_mode: 'coming_soon',
    primary_cta: { label: t('projectActions.status.coming_soon'), route: null, action: null },
    requirements: [],
    recommended_sources: [],
    recommended_assets: [],
    missing_requirements_count: 0,
    required_source: null,
    source_generation_id: null,
    reason: null,
    action_endpoint: null,
    tool_key: workflow.sourceToolKey,
    tool_status: 'coming_soon',
  }
}

function workflowTitleKey(workflow: BetaWorkflowDefinition): string {
  return 'workflows.' + workflow.key + '.title'
}

function workflowDescriptionKey(workflow: BetaWorkflowDefinition): string {
  return 'workflows.' + workflow.key + '.shortDescription'
}

function translatedTitle(workflow: BetaWorkflowDefinition, action: ProjectAction): string {
  const key = workflowTitleKey(workflow)
  const title = t(key)

  return title === key ? action.title : title
}

function translatedDescription(workflow: BetaWorkflowDefinition, action: ProjectAction): string {
  const key = workflowDescriptionKey(workflow)
  const description = t(key)

  return description === key ? action.description : description
}

function isCampaignAction(action: ProjectAction): boolean {
  return action.key === 'generate_campaign'
}

function isVideoToCampaignAction(action: ProjectAction): boolean {
  return action.key === 'generate_video_to_campaign'
}

function isFashionTryOnAction(action: ProjectAction): boolean {
  return action.key === 'start_fashion_try_on'
}

function isMotionAction(action: ProjectAction): boolean {
  return action.key === 'start_motion_video'
}

function isAutoVideoEditorAction(action: ProjectAction): boolean {
  return action.key === 'generate_auto_video_edit'
}

function isAutoVideoAdsAction(action: ProjectAction): boolean {
  return action.key === 'create_video_ad' || action.key === 'start_video_ad'
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

function latestGenerationForAction(action: ProjectAction): CreatorGeneration | null {
  if (isCampaignAction(action)) {
    return completedGeneration(props.latestCampaignGeneration, 'generate_affiliate_campaign')
      ?? completedGeneration(props.selectedGeneration, 'generate_affiliate_campaign')
  }

  if (isVideoToCampaignAction(action)) {
    return completedGeneration(props.latestVideoAnalysisGeneration, 'generate_video_to_campaign')
      ?? completedGeneration(props.selectedGeneration, 'generate_video_to_campaign')
  }

  if (isFashionTryOnAction(action)) {
    return completedGeneration(props.latestFashionTryOnGeneration, 'generate_fashion_try_on')
      ?? completedGeneration(props.selectedGeneration, 'generate_fashion_try_on')
  }

  if (isMotionAction(action)) {
    return completedGeneration(props.latestMotionAiVideoGeneration, 'generate_motion_ai_video')
      ?? completedGeneration(props.selectedGeneration, 'generate_motion_ai_video')
  }

  if (isAutoVideoEditorAction(action)) {
    return completedGeneration(props.latestAutoVideoEditorGeneration, 'generate_auto_video_edit')
      ?? completedGeneration(props.selectedGeneration, 'generate_auto_video_edit')
  }

  return null
}

function completedGeneration(generation: CreatorGeneration | null | undefined, jobType: string): CreatorGeneration | null {
  return generation?.job_type === jobType && generation.status === 'completed' ? generation : null
}

function resultRoute(generation: CreatorGeneration | null): string | null {
  return generation === null ? null : `/projects/${String(props.projectId)}/generations/${String(generation.id)}`
}

function latestResultLabel(action: ProjectAction): string {
  if (isCampaignAction(action)) {
    return t('projectActions.viewLatestProductContentKit')
  }

  if (isVideoToCampaignAction(action)) {
    return t('projectActions.viewLatestVideoAnalysis')
  }

  if (isFashionTryOnAction(action)) {
    return t('projectActions.viewLatestFashionTryOn')
  }

  if (isMotionAction(action)) {
    return t('projectActions.viewLatestMotionVideo')
  }

  if (isAutoVideoEditorAction(action)) {
    return t('projectActions.viewLatestEditedVideo')
  }

  return t('projectActions.viewLatestResult')
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

  if (isAutoVideoEditorAction(action) && props.latestVideoAnalysisGeneration === null) {
    return t('projectActions.analyzeFootageFirstBody')
  }

  return action.reason ?? null
}

function actionButtonLabel(action: ProjectAction): string {
  if (isUpgradeAction(action)) {
    return t('projectActions.upgradeSubscription')
  }

  if (isComingSoon(action)) {
    return t('projectActions.status.coming_soon')
  }

  if (isCampaignAction(action)) {
    return latestGenerationForAction(action) === null
      ? t('projectActions.createProductContentKit')
      : t('projectActions.generateAnotherProductContentKit')
  }

  if (isVideoToCampaignAction(action)) {
    return t('projectActions.analyzeNewFootage')
  }

  if (isFashionTryOnAction(action)) {
    return t('projectActions.setupFashionTryOn')
  }

  if (isMotionAction(action)) {
    return t('projectActions.createMotionAiVideo')
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

function compatibleCampaignForMotion(): CreatorGeneration | null {
  if (props.selectedGeneration !== null && isMotionCampaignCompatible(props.selectedGeneration)) {
    return props.selectedGeneration
  }

  if (props.latestVideoAnalysisGeneration && isMotionCampaignCompatible(props.latestVideoAnalysisGeneration)) {
    return props.latestVideoAnalysisGeneration
  }

  if (props.latestCampaignGeneration && isMotionCampaignCompatible(props.latestCampaignGeneration)) {
    return props.latestCampaignGeneration
  }

  return null
}

function motionAiVideoActionRoute() {
  const campaign = compatibleCampaignForMotion()

  return {
    name: 'project-motion-ai-video-action',
    params: { projectId: String(props.projectId) },
    query: campaign === null ? {} : {
      sourceMode: 'campaign_assisted',
      campaignGenerationId: String(campaign.id),
    },
  }
}

function fashionTryOnActionRoute() {
  return {
    name: 'project-fashion-try-on-action',
    params: { projectId: String(props.projectId) },
  }
}

function videoToCampaignActionRoute(sourceVideoAssetId: number | null = null, fromGenerationId: number | null = null) {
  const query: Record<string, string> = {}

  if (sourceVideoAssetId !== null) {
    query.sourceVideoAssetId = String(sourceVideoAssetId)
  }

  if (fromGenerationId !== null) {
    query.fromGenerationId = String(fromGenerationId)
    query.returnWorkflow = 'motion_ai_video'
  }

  return {
    name: 'project-video-to-campaign-action',
    params: { projectId: String(props.projectId) },
    query,
  }
}

function autoVideoEditorActionRoute() {
  const analysis = props.selectedGeneration !== null && isFootageAnalysisGeneration(props.selectedGeneration)
    ? props.selectedGeneration
    : props.latestVideoAnalysisGeneration ?? null
  const sourceVideoAssetId = sourceVideoAssetIdFromAnalysis(analysis ?? null)

  return {
    name: 'project-auto-video-editor-action',
    params: { projectId: String(props.projectId) },
    query: analysis === null ? {} : {
      editMode: 'ai_video_analysis',
      analysisGenerationId: String(analysis.id),
      ...(sourceVideoAssetId === null ? {} : { sourceVideoAssetId: String(sourceVideoAssetId) }),
    },
  }
}

function motionAnalysisSecondaryRoute(action: ProjectAction) {
  const generation = latestGenerationForAction(action)
  const finalVideoAssetId = motionFinalVideoAssetId(generation)

  if (generation === null || finalVideoAssetId === null) {
    return null
  }

  return videoToCampaignActionRoute(finalVideoAssetId, generation.id)
}

function secondaryAction(action: ProjectAction): { label: string, to: ReturnType<typeof videoToCampaignActionRoute> } | null {
  if (!isMotionAction(action)) {
    return null
  }

  const route = motionAnalysisSecondaryRoute(action)

  return route === null ? null : {
    label: t('projectActions.analyzeThisVideoWithAi'),
    to: route,
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

  if (isVideoToCampaignAction(action)) {
    await router.push(videoToCampaignActionRoute())
    return
  }

  if (isAutoVideoEditorAction(action)) {
    await router.push(autoVideoEditorActionRoute())
    return
  }

  if (isFashionTryOnAction(action)) {
    await router.push(fashionTryOnActionRoute())
    return
  }

  if (isMotionAction(action)) {
    await router.push(motionAiVideoActionRoute())
    return
  }

  if (isAutoVideoAdsAction(action)) {
    return
  }

  if (isCampaignAction(action) && action.status === 'available' && action.action_mode !== 'configure') {
    await submitCampaign(action)
    return
  }

  actionError.value = action.reason ?? t('projectActions.unavailableBody')
}

async function submitCampaign(action: ProjectAction): Promise<void> {
  activeAction.value = action
  isSubmitting.value = true
  actionError.value = ''

  try {
    const response = await generateCampaignProject(props.projectId)
    emit('generated', response)
    await loadActions()
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      actionError.value = t('projectActions.insufficientCredits')
    } else {
      actionError.value = error instanceof ApiError ? error.message : t('projectActions.actionError')
    }
  } finally {
    activeAction.value = null
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
        <section v-for="section in actionSections" :key="section.family.key" :data-testid="`project-${section.family.key}-workflow-section`">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 class="text-sm font-semibold text-neutral-100">
                {{ t(section.family.titleKey) }}
              </h3>
              <p class="mt-1 text-xs leading-5 text-neutral-500">
                {{ t(section.family.descriptionKey) }}
              </p>
            </div>
          </div>

          <div class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-4">
            <article
              v-for="card in section.cards"
              :key="card.workflow.key"
              class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4 transition"
              :class="isUpgradeAction(card.action) ? 'border-amber-900/70' : card.action.user_has_access === true && !isComingSoon(card.action) ? 'border-emerald-900/60' : 'border-neutral-800'"
              :data-testid="'project-action-' + card.action.key"
            >
              <div class="flex h-full min-w-0 flex-col">
                <div class="min-w-0 flex-1">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-400">
                    {{ t('workflows.categories.' + card.workflow.categoryKey) }}
                  </p>
                  <h4 class="mt-2 break-words text-sm font-semibold text-white">
                    {{ translatedTitle(card.workflow, card.action) }}
                  </h4>
                  <p class="mt-2 text-xs leading-5 text-neutral-500">
                    {{ translatedDescription(card.workflow, card.action) }}
                  </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ creditCostBadge(card.action) }}
                  </span>
                  <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="accessBadgeClasses(card.action)"
                  >
                    {{ accessBadgeLabel(card.action) }}
                  </span>
                </div>

                <p v-if="cardNote(card.action)" class="mt-3 text-xs leading-5 text-neutral-400">
                  {{ cardNote(card.action) }}
                </p>

                <RouterLink
                  v-if="resultRoute(latestGenerationForAction(card.action))"
                  :to="resultRoute(latestGenerationForAction(card.action)) ?? '/'"
                  class="mt-4 flex min-h-11 w-full items-center justify-center rounded-2xl border border-emerald-800 bg-emerald-950/40 px-4 text-center text-sm font-semibold text-emerald-100 transition hover:border-emerald-500 hover:bg-emerald-900/50"
                  :data-testid="'view-latest-' + card.workflow.key"
                >
                  {{ latestResultLabel(card.action) }}
                </RouterLink>

                <RouterLink
                  v-if="secondaryAction(card.action)"
                  :to="secondaryAction(card.action)?.to ?? '/'"
                  class="mt-3 flex min-h-11 w-full items-center justify-center rounded-2xl border border-sky-800 bg-sky-950/30 px-4 text-center text-sm font-semibold text-sky-100 transition hover:border-sky-500 hover:bg-sky-900/40"
                  :data-testid="'project-secondary-' + card.workflow.key"
                >
                  {{ secondaryAction(card.action)?.label }}
                </RouterLink>

                <button
                  type="button"
                  class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold transition"
                  :class="isActionButtonDisabled(card.action) ? 'cursor-not-allowed border border-neutral-800 text-neutral-500' : isUpgradeAction(card.action) ? 'border border-amber-700 bg-amber-950/40 text-amber-100 hover:bg-amber-900/50' : 'bg-white text-neutral-950 hover:bg-emerald-300'"
                  :disabled="isActionButtonDisabled(card.action)"
                  :aria-disabled="isActionButtonDisabled(card.action)"
                  @click="handleAction(card.action)"
                >
                  <span
                    v-if="isActionSubmitting(card.action)"
                    class="size-4 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
                    aria-hidden="true"
                  />
                  {{ isActionSubmitting(card.action) ? t('projectActions.generating') : actionButtonLabel(card.action) }}
                </button>
              </div>
            </article>
          </div>
        </section>
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
