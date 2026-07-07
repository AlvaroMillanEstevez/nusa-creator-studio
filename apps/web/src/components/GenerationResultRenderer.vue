<script setup lang="ts">
import { computed } from 'vue'

import AiVoiceoverGenerationResult from '@/components/AiVoiceoverGenerationResult.vue'
import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import FashionTryOnGenerationResult from '@/components/FashionTryOnGenerationResult.vue'
import MotionVideoGenerationResult from '@/components/MotionVideoGenerationResult.vue'
import ProductToVideoGenerationResult from '@/components/ProductToVideoGenerationResult.vue'
import VideoEnhancerGenerationResult from '@/components/VideoEnhancerGenerationResult.vue'
import {
  generationOutput,
  isAiVoiceoverResult,
  isCampaignGenerationResult,
  isFashionTryOnResult,
  isMotionVideoResult,
  isProductToVideoResult,
  isVideoEnhancerResult,
  type CreatorGeneration,
} from '@/types/project'
import { formatDate } from '@/utils/formatters'

const props = defineProps<{
  generation: CreatorGeneration | null
  showQueueWorkerHint?: boolean
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
const hasSupportedResult = computed(
  () =>
    campaignResult.value !== null ||
    videoEnhancerResult.value !== null ||
    productToVideoResult.value !== null ||
    aiVoiceoverResult.value !== null ||
    fashionTryOnResult.value !== null ||
    motionVideoResult.value !== null,
)
const isQueued = computed(
  () => props.generation?.status === 'queued' || props.generation?.status === 'pending',
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
        <h2 class="mt-1 text-lg font-semibold">Creating your result...</h2>
      </div>
    </div>
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
    <h2 class="mt-2 text-lg font-semibold">Generation failed. Please try again.</h2>
    <p class="mt-2 text-sm leading-6 text-rose-200">
      Your charged credits are returned automatically for an internal processing failure.
    </p>
  </section>

  <div
    v-else-if="generation && generation.status === 'completed' && !hasSupportedResult"
    class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-900 p-4 text-sm text-neutral-400"
  >
    This generation does not have a supported output payload to display.
  </div>

  <div
    v-else-if="generation && generation.status === 'completed' && hasSupportedResult"
    class="mt-8"
    aria-live="polite"
  >
    <section class="mb-5 rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
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
    <AiVoiceoverGenerationResult v-else-if="aiVoiceoverResult" :result="aiVoiceoverResult" />
    <FashionTryOnGenerationResult v-else-if="fashionTryOnResult" :result="fashionTryOnResult" />
    <MotionVideoGenerationResult v-else-if="motionVideoResult" :result="motionVideoResult" />
  </div>
</template>