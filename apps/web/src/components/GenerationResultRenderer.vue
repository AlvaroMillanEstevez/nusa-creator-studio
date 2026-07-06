<script setup lang="ts">
import { computed } from 'vue'

import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import ProductToVideoGenerationResult from '@/components/ProductToVideoGenerationResult.vue'
import VideoEnhancerGenerationResult from '@/components/VideoEnhancerGenerationResult.vue'
import {
  isCampaignGenerationResult,
  isProductToVideoResult,
  isVideoEnhancerResult,
  type CreatorGeneration,
} from '@/types/project'
import { formatDate } from '@/utils/formatters'

const props = defineProps<{ generation: CreatorGeneration | null }>()

const result = computed(() => props.generation?.result_payload ?? null)
const campaignResult = computed(() =>
  props.generation?.job_type === 'generate_affiliate_campaign' &&
  isCampaignGenerationResult(result.value)
    ? result.value
    : null,
)
const videoEnhancerResult = computed(() =>
  props.generation?.job_type === 'generate_video_enhancer' &&
  isVideoEnhancerResult(result.value)
    ? result.value
    : null,
)
const productToVideoResult = computed(() =>
  props.generation?.job_type === 'generate_product_video_script' &&
  isProductToVideoResult(result.value)
    ? result.value
    : null,
)
const hasSupportedResult = computed(
  () =>
    campaignResult.value !== null ||
    videoEnhancerResult.value !== null ||
    productToVideoResult.value !== null,
)
</script>

<template>
  <div
    v-if="generation && !hasSupportedResult"
    class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-900 p-4 text-sm text-neutral-400"
  >
    This generation does not have a supported result payload to display.
  </div>

  <div v-else-if="generation && hasSupportedResult" class="mt-8" aria-live="polite">
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
    <VideoEnhancerGenerationResult
      v-else-if="videoEnhancerResult"
      :result="videoEnhancerResult"
    />
    <ProductToVideoGenerationResult
      v-else-if="productToVideoResult"
      :result="productToVideoResult"
    />
  </div>
</template>
