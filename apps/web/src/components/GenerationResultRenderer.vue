<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import AiVoiceoverGenerationResult from '@/components/AiVoiceoverGenerationResult.vue'
import AutoVideoEditorGenerationResult from '@/components/AutoVideoEditorGenerationResult.vue'
import AutoVideoAdGenerationResult from '@/components/AutoVideoAdGenerationResult.vue'
import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import FashionTryOnGenerationResult from '@/components/FashionTryOnGenerationResult.vue'
import MotionAiVideoGenerationResult from '@/components/MotionAiVideoGenerationResult.vue'
import ProductToVideoGenerationResult from '@/components/ProductToVideoGenerationResult.vue'
import VideoEnhancerGenerationResult from '@/components/VideoEnhancerGenerationResult.vue'
import VideoToCampaignGenerationResult from '@/components/VideoToCampaignGenerationResult.vue'
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

const props = defineProps<{
  generation: CreatorGeneration | null
  showQueueWorkerHint?: boolean
  mediaAssets?: MediaAsset[]
  projectId?: number | string | null
  voiceoverAction?: ProjectAction | null
}>()

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

  return `/projects/${String(id)}`
})
const isVideoToCampaignGeneration = computed(() =>
  props.generation?.job_type === 'generate_video_to_campaign',
)
const isAutoVideoEditorGeneration = computed(() =>
  props.generation?.job_type === 'generate_auto_video_edit',
)
const isVideoProcessingWorkflow = computed(() =>
  isVideoToCampaignGeneration.value || isAutoVideoEditorGeneration.value,
)
const processingTitle = computed(() =>
  isVideoToCampaignGeneration.value
    ? 'Analyzing your video and creating the campaign.'
    : isAutoVideoEditorGeneration.value
      ? 'Rendering your video.'
      : 'Creating your result...',
)
const processingDescription = computed(() =>
  isVideoProcessingWorkflow.value
    ? 'This can take a few minutes depending on video length and provider processing time.'
    : 'We are creating your result. The result will update automatically.',
)
const failedTitle = computed(() =>
  isVideoToCampaignGeneration.value
    ? 'Video analysis failed. Your credits were refunded if they were charged.'
    : isAutoVideoEditorGeneration.value
      ? 'Video rendering failed. Your credits were refunded if they were charged.'
      : 'Generation failed. Please try again.',
)
const failedDescription = computed(() =>
  isVideoProcessingWorkflow.value
    ? 'The provider did not return a usable result. We kept the technical details out of the UI so private provider data stays safe.'
    : 'Your charged credits are returned automatically for an internal processing failure.',
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
    <p class="mt-2 text-sm leading-6 text-neutral-400">
      Please keep this page open. The result will update automatically.
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
    <div v-if="isVideoProcessingWorkflow" class="mt-5 grid gap-3 sm:grid-cols-2">
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
        Try again
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

    <CampaignGenerationResult v-if="campaignResult" :result="campaignResult" />
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
    <FashionTryOnGenerationResult v-else-if="fashionTryOnResult" :result="fashionTryOnResult" />
    <MotionAiVideoGenerationResult v-else-if="motionVideoResult" :result="motionVideoResult" />
    <VideoToCampaignGenerationResult
      v-else-if="videoToCampaignResult"
      :result="videoToCampaignResult"
      :project-id="projectId ?? generation.project_id"
      :generation-id="generation.id"
      :voiceover-action="voiceoverAction"
    />
    <AutoVideoEditorGenerationResult
      v-else-if="autoVideoEditorResult"
      :result="autoVideoEditorResult"
      :generation-id="generation.id"
    />
    <AutoVideoAdGenerationResult v-else-if="autoVideoAdResult" :result="autoVideoAdResult" />
  </div>
</template>