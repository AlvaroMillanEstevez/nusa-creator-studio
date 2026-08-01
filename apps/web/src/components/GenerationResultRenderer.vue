<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import AiVoiceoverGenerationResult from '@/components/AiVoiceoverGenerationResult.vue'
import AutoVideoEditorGenerationResult from '@/components/AutoVideoEditorGenerationResult.vue'
import AutoVideoAdGenerationResult from '@/components/AutoVideoAdGenerationResult.vue'
import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import FashionTryOnGenerationResult from '@/components/FashionTryOnGenerationResult.vue'
import MotionAiVideoGenerationResult from '@/components/MotionAiVideoGenerationResult.vue'
import ProductToVideoGenerationResult from '@/components/ProductToVideoGenerationResult.vue'
import VideoEnhancerGenerationResult from '@/components/VideoEnhancerGenerationResult.vue'
import VideoToCampaignGenerationResult from '@/components/VideoToCampaignGenerationResult.vue'
import { useTranslations } from '@/i18n'
import type { MediaAsset } from '@/types/media'
import {
  generationOutput,
  isAiVoiceoverResult,
  isAutoVideoEditorResult,
  isAutoVideoAdResult,
  isCampaignGenerationResult,
  isFashionTryOnResult,
  isMotionVideoResult,
  isProductToVideoResult,
  isVideoEnhancerResult,
  isVideoToCampaignResult,
  type CreatorGeneration,
  type ProjectAction,
} from '@/types/project'
import { formatDate } from '@/utils/formatters'
import { voiceoverSourceGenerationId } from '@/utils/workflowLineage'

const props = defineProps<{
  generation: CreatorGeneration | null
  showQueueWorkerHint?: boolean
  mediaAssets?: MediaAsset[]
  projectId?: number | string | null
  voiceoverAction?: ProjectAction | null
  projectGenerations?: CreatorGeneration[]
}>()

interface FailureAction {
  key: string
  label: string
  to: RouteLocationRaw
  primary: boolean
}

const { t } = useTranslations()

const result = computed(() =>
  props.generation === null ? null : generationOutput(props.generation),
)
const campaignResult = computed(() =>
  props.generation?.job_type === 'generate_affiliate_campaign' &&
  isCampaignGenerationResult(result.value)
    ? result.value
    : null,
)
const videoEnhancerResult = computed(() =>
  props.generation?.job_type === 'generate_video_enhancer' && isVideoEnhancerResult(result.value)
    ? result.value
    : null,
)
const productToVideoResult = computed(() =>
  props.generation?.job_type === 'generate_product_video_script' &&
  isProductToVideoResult(result.value)
    ? result.value
    : null,
)
const aiVoiceoverResult = computed(() =>
  props.generation?.job_type === 'generate_ai_voiceover' && isAiVoiceoverResult(result.value)
    ? result.value
    : null,
)
const fashionTryOnResult = computed(() =>
  props.generation?.job_type === 'generate_fashion_try_on' && isFashionTryOnResult(result.value)
    ? result.value
    : null,
)
const motionVideoResult = computed(() =>
  props.generation?.job_type === 'generate_motion_ai_video' && isMotionVideoResult(result.value)
    ? result.value
    : null,
)
const videoToCampaignResult = computed(() =>
  props.generation?.job_type === 'generate_video_to_campaign' && isVideoToCampaignResult(result.value)
    ? result.value
    : null,
)
const autoVideoEditorResult = computed(() =>
  props.generation?.job_type === 'generate_auto_video_edit' && isAutoVideoEditorResult(result.value)
    ? result.value
    : null,
)
const autoVideoAdResult = computed(() =>
  props.generation?.job_type === 'generate_auto_video_ad' && isAutoVideoAdResult(result.value)
    ? result.value
    : null,
)
const linkedVoiceoverGeneration = computed(() => {
  if (props.generation === null || props.generation.job_type !== 'generate_video_to_campaign') {
    return null
  }

  return (props.projectGenerations ?? []).find(
    (item) => item.job_type === 'generate_ai_voiceover'
      && item.status === 'completed'
      && voiceoverSourceGenerationId(item) === props.generation?.id,
  ) ?? null
})
const hasSupportedResult = computed(
  () =>
    campaignResult.value !== null ||
    videoEnhancerResult.value !== null ||
    productToVideoResult.value !== null ||
    aiVoiceoverResult.value !== null ||
    fashionTryOnResult.value !== null ||
    motionVideoResult.value !== null ||
    videoToCampaignResult.value !== null ||
    autoVideoEditorResult.value !== null ||
    autoVideoAdResult.value !== null,
)
const isQueued = computed(
  () => props.generation?.status === 'queued' || props.generation?.status === 'pending',
)
const projectWorkspaceRoute = computed(() => {
  const id = props.projectId ?? props.generation?.project_id

  return id === null || id === undefined ? '/dashboard' : `/projects/${String(id)}`
})
const retryRoute = computed(() => {
  const id = props.projectId ?? props.generation?.project_id

  if (id === null || id === undefined) {
    return '/dashboard'
  }

  if (props.generation?.job_type === 'generate_video_to_campaign') {
    return `/projects/${String(id)}/actions/video-to-campaign`
  }

  if (props.generation?.job_type === 'generate_auto_video_edit') {
    return `/projects/${String(id)}/actions/auto-video-editor`
  }
  if (props.generation?.job_type === 'generate_auto_video_ad') {
    return `/projects/${String(id)}/actions/auto-video-ads`
  }
  if (props.generation?.job_type === 'generate_motion_ai_video') {
    return `/projects/${String(id)}/actions/motion-ai-video`
  }

  if (props.generation?.job_type === 'generate_fashion_try_on') {
    return `/projects/${String(id)}/actions/fashion-try-on`
  }

  return `/projects/${String(id)}`
})
const isVideoToCampaignGeneration = computed(() =>
  props.generation?.job_type === 'generate_video_to_campaign',
)
const isAutoVideoEditorGeneration = computed(() =>
  props.generation?.job_type === 'generate_auto_video_edit',
)
const isMotionAiVideoGeneration = computed(() =>
  props.generation?.job_type === 'generate_motion_ai_video',
)
const isFashionTryOnGeneration = computed(() =>
  props.generation?.job_type === 'generate_fashion_try_on',
)
const isVideoProcessingWorkflow = computed(() =>
  isVideoToCampaignGeneration.value || isAutoVideoEditorGeneration.value || isMotionAiVideoGeneration.value,
)
const isMediaProcessingWorkflow = computed(() =>
  isVideoProcessingWorkflow.value || isFashionTryOnGeneration.value,
)
const processingTitle = computed(() =>
  isVideoToCampaignGeneration.value
    ? 'Analyzing your video and creating the campaign.'
    : isAutoVideoEditorGeneration.value
      ? 'Rendering your video.'
      : isMotionAiVideoGeneration.value
        ? 'Creating your video.'
        : isFashionTryOnGeneration.value
          ? 'Creating your fashion try-on.'
          : 'Creating your result...',
)
const processingDescription = computed(() => {
  if (isMotionAiVideoGeneration.value) {
    return 'Preparing the creative direction, generating the visual footage and saving your result. This can take a few minutes. You can stay on this page; the result updates automatically.'
  }

  if (isFashionTryOnGeneration.value) {
    return 'Preparing your images, creating the fashion try-on and saving your private result. This can take a short while; the result updates automatically.'
  }

  if (isVideoProcessingWorkflow.value) {
    return 'This can take a few minutes depending on video length and provider processing time. You can stay on this page; the result updates automatically.'
  }

  return 'We are creating your result. You can stay on this page; the result updates automatically.'
})

const fashionFailureCategories = [
  'fashn_pose_not_detected',
  'fashn_image_load_failed',
  'fashn_input_validation',
  'fashn_content_moderation',
  'fashn_auth_failed',
  'fashn_insufficient_credits',
  'fashn_provider_unavailable',
  'fashn_poll_timeout',
  'fashn_output_invalid',
] as const

type FashionFailureCategory = (typeof fashionFailureCategories)[number]

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function metadataString(metadata: Record<string, unknown>, key: string): string | null {
  const value = metadata[key]

  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

const failurePayload = computed(() => isRecord(result.value) ? result.value : null)
const failureMetadata = computed<Record<string, unknown>>(() => {
  const metadata = failurePayload.value?.provider_metadata

  return isRecord(metadata) ? metadata : {}
})
const fashionSafeFailureCategory = computed<FashionFailureCategory | null>(() => {
  const category = metadataString(failureMetadata.value, 'safe_error_category')

  if (fashionFailureCategories.includes(category as FashionFailureCategory)) {
    return category as FashionFailureCategory
  }

  if (metadataString(failureMetadata.value, 'provider_error_name') === 'PoseError') {
    return 'fashn_pose_not_detected'
  }

  return null
})
const fashionFailureCopy = computed(() => {
  if (!isFashionTryOnGeneration.value) {
    return null
  }

  const category = fashionSafeFailureCategory.value

  if (category !== null) {
    return {
      title: t(`fashionTryOn.failure.categories.${category}.title`),
      message: t(`fashionTryOn.failure.categories.${category}.message`),
    }
  }

  return {
    title: t('fashionTryOn.failure.genericTitle'),
    message: t('fashionTryOn.failure.genericMessage'),
  }
})
const fashionActionRoute = computed<RouteLocationRaw>(() => {
  const id = props.projectId ?? props.generation?.project_id

  return id === null || id === undefined
    ? '/dashboard'
    : { name: 'project-fashion-try-on-action', params: { projectId: String(id) } }
})
const fashionPersonPhotoRoute = computed<RouteLocationRaw>(() => {
  const id = props.projectId ?? props.generation?.project_id

  return id === null || id === undefined
    ? '/dashboard'
    : { name: 'project-fashion-try-on-action', params: { projectId: String(id) }, query: { focus: 'person_photo' } }
})
const fashionProductPhotoRoute = computed<RouteLocationRaw>(() => {
  const id = props.projectId ?? props.generation?.project_id

  return id === null || id === undefined
    ? '/dashboard'
    : { name: 'project-fashion-try-on-action', params: { projectId: String(id) }, query: { focus: 'product_photo' } }
})
const fashionFailureActions = computed<FailureAction[]>(() => {
  const editAction: FailureAction = {
    key: 'edit',
    label: t('fashionTryOn.failure.editSettings'),
    to: fashionActionRoute.value,
    primary: fashionSafeFailureCategory.value !== 'fashn_pose_not_detected',
  }
  const personAction: FailureAction = {
    key: 'person',
    label: t('fashionTryOn.failure.choosePerson'),
    to: fashionPersonPhotoRoute.value,
    primary: fashionSafeFailureCategory.value === 'fashn_pose_not_detected',
  }
  const productAction: FailureAction = {
    key: 'product',
    label: t('fashionTryOn.failure.chooseProduct'),
    to: fashionProductPhotoRoute.value,
    primary: false,
  }
  const returnAction: FailureAction = {
    key: 'return',
    label: t('fashionTryOn.failure.returnToProject'),
    to: projectWorkspaceRoute.value,
    primary: false,
  }

  return fashionSafeFailureCategory.value === 'fashn_pose_not_detected'
    ? [personAction, editAction, productAction, returnAction]
    : [editAction, personAction, productAction, returnAction]
})
const failedTitle = computed(() =>
  fashionFailureCopy.value !== null
    ? fashionFailureCopy.value.title
    : isVideoToCampaignGeneration.value
    ? 'Video analysis failed. Your credits were refunded if they were charged.'
    : isAutoVideoEditorGeneration.value
      ? 'Video rendering failed. Your credits were refunded if they were charged.'
      : isMotionAiVideoGeneration.value
        ? 'Video generation failed. Your credits were refunded if they were charged.'
        : isFashionTryOnGeneration.value
          ? 'Fashion try-on failed. Your credits were refunded if they were charged.'
          : 'Generation failed. Please try again.',
)
const failedDescription = computed(() =>
  fashionFailureCopy.value !== null
    ? fashionFailureCopy.value.message
    : isMotionAiVideoGeneration.value
    ? 'No fake output was created. We kept technical provider details out of the UI so private data stays safe. Edit the settings and try again when you are ready.'
    : isVideoProcessingWorkflow.value
      ? 'The provider did not return a usable result. We kept the technical details out of the UI so private provider data stays safe.'
      : 'Your charged credits are returned automatically for an internal processing failure.',
)
const retryActionLabel = computed(() =>
  isMotionAiVideoGeneration.value || isFashionTryOnGeneration.value ? 'Edit settings' : 'Try again',
)
</script>

<template>
  <section
    v-if="generation && isQueued"
    data-testid="generation-status"
    class="mt-5 rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5"
    aria-live="polite"
  >
    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">Queued</p>
    <h2 class="mt-2 text-lg font-semibold">Your generation is queued.</h2>
    <p class="mt-2 text-sm leading-6 text-neutral-400">
      We will start creating your result as soon as a worker is available.
    </p>
    <p v-if="showQueueWorkerHint" class="mt-3 text-xs text-amber-300">
      Queue worker may not be running.
    </p>
  </section>

  <section
    v-else-if="generation?.status === 'processing'"
    data-testid="generation-status"
    class="mt-5 rounded-3xl border border-emerald-900/60 bg-emerald-950/20 p-5"
    aria-live="polite"
  >
    <div class="flex items-center gap-3">
      <span
        class="size-5 animate-spin rounded-full border-2 border-emerald-900 border-t-emerald-300"
        aria-hidden="true"
      />
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Processing</p>
        <h2 class="mt-1 text-lg font-semibold">{{ processingTitle }}</h2>
      </div>
    </div>
    <p class="mt-3 text-sm leading-6 text-neutral-300">
      {{ processingDescription }}
    </p>
    <p v-if="showQueueWorkerHint" class="mt-3 text-xs text-amber-300">
      Queue worker may not be running.
    </p>
  </section>

  <section
    v-else-if="generation?.status === 'failed'"
    data-testid="generation-status"
    role="alert"
    class="mt-5 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5"
  >
    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-400">Failed</p>
    <h2 class="mt-2 text-lg font-semibold">{{ failedTitle }}</h2>
    <p class="mt-2 text-sm leading-6 text-rose-200">
      {{ failedDescription }}
    </p>
    <div v-if="isFashionTryOnGeneration" class="mt-5 grid gap-3 sm:grid-cols-2" data-testid="fashion-failure-actions">
      <RouterLink
        v-for="action in fashionFailureActions"
        :key="action.key"
        :to="action.to"
        class="flex min-h-11 items-center justify-center rounded-2xl px-4 text-center text-sm font-semibold transition"
        :class="action.primary
          ? 'bg-white text-neutral-950 hover:bg-emerald-300'
          : 'border border-neutral-700 text-neutral-100 hover:border-emerald-500 hover:bg-emerald-950/30'"
      >
        {{ action.label }}
      </RouterLink>
    </div>
    <div v-else-if="isMediaProcessingWorkflow" class="mt-5 grid gap-3 sm:grid-cols-2">
      <RouterLink
        :to="projectWorkspaceRoute"
        class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-100 transition hover:border-emerald-500 hover:bg-emerald-950/30"
      >
        Back to workspace
      </RouterLink>
      <RouterLink
        :to="retryRoute"
        class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
      >
        {{ retryActionLabel }}
      </RouterLink>
    </div>
  </section>

  <div
    v-else-if="generation && generation.status === 'completed' && !hasSupportedResult"
    class="nusa-card-soft mt-5 rounded-2xl p-4 text-sm text-neutral-400"
  >
    This generation does not have a supported output payload to display.
  </div>

  <div
    v-else-if="generation && generation.status === 'completed' && hasSupportedResult"
    class="mt-8"
    aria-live="polite"
  >
    <section class="nusa-card-premium mb-5 rounded-[2rem] p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Selected generation
          </p>
          <p class="mt-2 text-xs text-neutral-500">{{ formatDate(generation.created_at) }}</p>
        </div>
        <span class="text-xs font-medium text-neutral-400">
          {{ generation.provider ?? 'Unknown provider' }}
        </span>
      </div>
    </section>

    <CampaignGenerationResult
      v-if="campaignResult"
      :result="campaignResult"
      :project-id="projectId ?? generation.project_id"
      :generation-id="generation.id"
      :voiceover-action="voiceoverAction"
    />
    <VideoEnhancerGenerationResult v-else-if="videoEnhancerResult" :result="videoEnhancerResult" />
    <ProductToVideoGenerationResult
      v-else-if="productToVideoResult"
      :result="productToVideoResult"
    />
    <AiVoiceoverGenerationResult
      v-else-if="aiVoiceoverResult"
      :result="aiVoiceoverResult"
      :media-assets="mediaAssets"
    />
    <FashionTryOnGenerationResult
      v-else-if="fashionTryOnResult"
      :result="fashionTryOnResult"
      :project-id="projectId ?? generation.project_id"
      :generation-id="generation.id"
      :credits-charged="generation.credits_charged"
      :created-at="generation.created_at"
    />
    <MotionAiVideoGenerationResult
      v-else-if="motionVideoResult"
      :result="motionVideoResult"
      :project-id="projectId ?? generation.project_id"
      :generation-id="generation.id"
    />
    <VideoToCampaignGenerationResult
      v-else-if="videoToCampaignResult"
      :result="videoToCampaignResult"
      :project-id="projectId ?? generation.project_id"
      :generation-id="generation.id"
      :voiceover-action="voiceoverAction"
      :linked-voiceover-generation="linkedVoiceoverGeneration"
    />
    <AutoVideoEditorGenerationResult
      v-else-if="autoVideoEditorResult"
      :result="autoVideoEditorResult"
      :generation-id="generation.id"
      :project-id="projectId ?? generation.project_id"
    />
    <AutoVideoAdGenerationResult v-else-if="autoVideoAdResult" :result="autoVideoAdResult" />
  </div>
</template>