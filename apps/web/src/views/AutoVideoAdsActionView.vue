<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { listProjectMediaAssets } from '@/services/media'
import {
  generateAutoVideoAd,
  getCreatorProject,
  getProjectActions,
  getProjectGenerations,
} from '@/services/projects'
import type { MediaAsset } from '@/types/media'
import {
  autoVideoAdDurationOptions,
  autoVideoAdFormatOptions,
  autoVideoAdGoalOptions,
  autoVideoAdHookStyleOptions,
  autoVideoAdPlatformOptions,
  autoVideoAdToneOptions,
  autoVideoAdVisualStyleOptions,
  type CreatorGeneration,
  type CreatorProject,
  type GenerateAutoVideoAdPayload,
  type ProjectAction,
} from '@/types/project'
import { generationDisplayName } from '@/utils/generations'

const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const generations = ref<CreatorGeneration[]>([])
const mediaAssets = ref<MediaAsset[]>([])
const selectedImageAssetId = ref<number | null>(null)
const selectedVideoAssetId = ref<number | null>(null)
const selectedCampaignGenerationId = ref<number | null>(null)
const selectedVoiceoverGenerationId = ref<number | null>(null)
const adFormat = ref('vertical_9_16')
const platform = ref('tiktok_ads')
const adGoal = ref('sales')
const targetDurationSeconds = ref(15)
const tone = ref('direct_response')
const visualStyle = ref('clean_product')
const hookStyle = ref('benefit_first')
const primaryCta = ref('')
const offerText = ref('')
const landingPageContext = ref('')
const audienceOverride = ref('')
const includeVoiceoverScript = ref(true)
const includeCaptionVariants = ref(true)
const isLoading = ref(true)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})

const autoVideoAdsAction = computed(() => actions.value.find((item) => item.key === 'create_video_ad') ?? null)
const productImages = computed(() => mediaAssets.value.filter((asset) => asset.type === 'image'))
const sourceVideos = computed(() => mediaAssets.value.filter((asset) => asset.type === 'video'))
const campaignGenerations = computed(() =>
  generations.value.filter(
    (item) =>
      item.status === 'completed' &&
      ['generate_affiliate_campaign', 'generate_video_to_campaign', 'generate_product_video_script'].includes(item.job_type),
  ),
)
const voiceoverGenerations = computed(() =>
  generations.value.filter((item) => item.status === 'completed' && item.job_type === 'generate_ai_voiceover'),
)
const creditCost = computed(() => autoVideoAdsAction.value?.credit_cost ?? 12)
const isRealCapableBeta = computed(() => creditCost.value >= 12)
const submitLabel = computed(() => isRealCapableBeta.value ? 'Generate Auto Video Ad' : 'Generate Ad Plan')
const submittingLabel = computed(() => isRealCapableBeta.value ? 'Generating auto video ad...' : 'Generating ad plan...')
const selectedVisualType = computed(() => {
  if (selectedImageAssetId.value !== null && selectedVideoAssetId.value !== null) {
    return 'Hybrid ad'
  }

  if (selectedVideoAssetId.value !== null) {
    return 'Video ad'
  }

  if (selectedImageAssetId.value !== null) {
    return 'Image ad'
  }

  return 'No visual selected'
})
const isUpgradeRequired = computed(() =>
  autoVideoAdsAction.value?.upgrade_required === true || autoVideoAdsAction.value?.action_mode === 'upgrade',
)

function initializeForm(loadedProject: CreatorProject): void {
  const input = (loadedProject.input_payload ?? {}) as Record<string, unknown>

  selectedImageAssetId.value = productImages.value[0]?.id ?? null
  selectedVideoAssetId.value = sourceVideos.value[0]?.id ?? null
  selectedCampaignGenerationId.value = campaignGenerations.value[0]?.id ?? null
  selectedVoiceoverGenerationId.value = voiceoverGenerations.value[0]?.id ?? null
  primaryCta.value = typeof input.cta_preference === 'string' ? input.cta_preference : ''
  audienceOverride.value = typeof input.target_audience === 'string' ? input.target_audience : ''
  landingPageContext.value = typeof input.product_description === 'string' ? input.product_description : ''
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, assetResponse, generationResponse, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      listProjectMediaAssets(projectId.value),
      getProjectGenerations(projectId.value),
      getProjectActions(projectId.value),
    ])

    project.value = loadedProject
    mediaAssets.value = Array.isArray(assetResponse.data) ? assetResponse.data : []
    generations.value = Array.isArray(generationResponse.data) ? generationResponse.data : []
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
    initializeForm(loadedProject)
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : 'We could not load Auto Video Ads setup.'
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isUpgradeRequired.value) {
    await router.push(autoVideoAdsAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (selectedImageAssetId.value === null && selectedVideoAssetId.value === null) {
    formError.value = 'Select a product image, a source video, or both before generating an auto video ad.'
    return
  }

  isSubmitting.value = true

  const payload: GenerateAutoVideoAdPayload = {
    ad_format: adFormat.value,
    platform: platform.value,
    ad_goal: adGoal.value,
    product_image_asset_id: selectedImageAssetId.value,
    source_video_asset_id: selectedVideoAssetId.value,
    campaign_generation_id: selectedCampaignGenerationId.value,
    voiceover_generation_id: selectedVoiceoverGenerationId.value,
    target_duration_seconds: targetDurationSeconds.value,
    primary_cta: primaryCta.value.trim() || null,
    offer_text: offerText.value.trim() || null,
    landing_page_context: landingPageContext.value.trim() || null,
    audience_override: audienceOverride.value.trim() || null,
    tone: tone.value,
    visual_style: visualStyle.value,
    hook_style: hookStyle.value,
    include_voiceover_script: includeVoiceoverScript.value,
    include_caption_variants: includeCaptionVariants.value,
  }

  try {
    const response = await generateAutoVideoAd(projectId.value, payload)
    await router.push(`/projects/${projectId.value}/generations/${response.data.id}`)
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      formError.value = 'You do not have enough credits to generate this auto video ad.'
    } else {
      formError.value = error instanceof ApiError ? error.message : 'We could not generate this auto video ad.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function generationLabel(generation: CreatorGeneration): string {
  return generationDisplayName(generation, project.value)
}

onMounted(loadPage)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-5xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-4 w-32 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-8 h-80 animate-pulse rounded-[2rem] bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">Loading Auto Video Ads setup...</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">Auto Video Ads unavailable</p>
        <h1 class="mt-3 text-3xl font-bold">This setup needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button type="button" class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950" @click="loadPage">
          Try again
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30">
            ← Back to workspace
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Auto Video Ads</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            Create paid ad creative from your product assets, campaign content and CTA. When the real beta provider is enabled, Nusa renders a final MP4.
          </p>
        </header>

        <form class="mt-8 grid gap-5 lg:grid-cols-[1.05fr_0.95fr]" @submit.prevent="submit">
          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Source assets</p>
              <h2 class="mt-2 text-xl font-semibold">Choose visual assets</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Use an image for product-focused motion ads, a video for demo ads, or both for hybrid ads. Mock mode returns the plan; real beta mode renders the MP4.
              </p>

              <label class="mt-5 block">
                <span class="nusa-label">Product image</span>
                <select v-model.number="selectedImageAssetId" class="nusa-select mt-2" data-testid="auto-video-ad-image-select">
                  <option :value="null">No image</option>
                  <option v-for="asset in productImages" :key="asset.id" :value="asset.id">
                    {{ asset.original_name ?? 'Product image #' + asset.id }}
                  </option>
                </select>
              </label>

              <label class="mt-4 block">
                <span class="nusa-label">Source video</span>
                <select v-model.number="selectedVideoAssetId" class="nusa-select mt-2" data-testid="auto-video-ad-video-select">
                  <option :value="null">No video</option>
                  <option v-for="asset in sourceVideos" :key="asset.id" :value="asset.id">
                    {{ asset.original_name ?? 'Source video #' + asset.id }}
                  </option>
                </select>
              </label>

              <p class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300" data-testid="auto-video-ad-visual-type">
                {{ selectedVisualType }}
              </p>
              <RouterLink to="/media" class="mt-4 inline-flex text-sm font-semibold text-emerald-300 hover:text-emerald-200">
                Manage media assets →
              </RouterLink>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Campaign source</p>
              <h2 class="mt-2 text-xl font-semibold">Reuse campaign context</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Recommended. Campaign content improves hooks, script and CTA.
              </p>
              <select v-model.number="selectedCampaignGenerationId" class="nusa-select mt-5" data-testid="auto-video-ad-campaign-select">
                <option :value="null">No campaign source</option>
                <option v-for="item in campaignGenerations" :key="item.id" :value="item.id">
                  {{ generationLabel(item) }} · #{{ item.id }}
                </option>
              </select>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Voiceover</p>
              <h2 class="mt-2 text-xl font-semibold">Optional voiceover source</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Optional. Real beta rendering can include the selected voiceover; mock mode uses it as planning context.
              </p>
              <select v-model.number="selectedVoiceoverGenerationId" class="nusa-select mt-5" data-testid="auto-video-ad-voiceover-select">
                <option :value="null">No voiceover</option>
                <option v-for="item in voiceoverGenerations" :key="item.id" :value="item.id">
                  {{ generationLabel(item) }} · #{{ item.id }}
                </option>
              </select>
            </div>
          </section>

          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Ad setup</p>
              <h2 class="mt-2 text-xl font-semibold">Configure the ad plan</h2>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="nusa-label">Platform</span>
                  <select v-model="platform" class="nusa-select mt-2" data-testid="auto-video-ad-platform-select">
                    <option v-for="option in autoVideoAdPlatformOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">Format</span>
                  <select v-model="adFormat" class="nusa-select mt-2">
                    <option v-for="option in autoVideoAdFormatOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">Ad goal</span>
                  <select v-model="adGoal" class="nusa-select mt-2">
                    <option v-for="option in autoVideoAdGoalOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">Target duration</span>
                  <select v-model.number="targetDurationSeconds" class="nusa-select mt-2">
                    <option v-for="duration in autoVideoAdDurationOptions" :key="duration" :value="duration">{{ duration }}s</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">Tone</span>
                  <select v-model="tone" class="nusa-select mt-2">
                    <option v-for="option in autoVideoAdToneOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">Visual style</span>
                  <select v-model="visualStyle" class="nusa-select mt-2">
                    <option v-for="option in autoVideoAdVisualStyleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block sm:col-span-2">
                  <span class="nusa-label">Hook style</span>
                  <select v-model="hookStyle" class="nusa-select mt-2">
                    <option v-for="option in autoVideoAdHookStyleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
              </div>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Offer and CTA</p>
              <h2 class="mt-2 text-xl font-semibold">Guide the paid ad message</h2>
              <label class="mt-5 block">
                <span class="nusa-label">Primary CTA</span>
                <input v-model="primaryCta" class="nusa-input mt-2" type="text" placeholder="See product details">
              </label>
              <label class="mt-4 block">
                <span class="nusa-label">Offer text</span>
                <input v-model="offerText" class="nusa-input mt-2" type="text" placeholder="Optional. Only include real offers.">
              </label>
              <label class="mt-4 block">
                <span class="nusa-label">Landing page / product context</span>
                <textarea v-model="landingPageContext" class="nusa-textarea mt-2 min-h-24" />
              </label>
              <label class="mt-4 block">
                <span class="nusa-label">Audience override</span>
                <input v-model="audienceOverride" class="nusa-input mt-2" type="text" placeholder="Optional">
              </label>
              <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="includeVoiceoverScript" type="checkbox" class="mt-1 accent-emerald-400">
                  <span>Include voiceover-ready script</span>
                </label>
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="includeCaptionVariants" type="checkbox" class="mt-1 accent-emerald-400">
                  <span>Include caption variants</span>
                </label>
              </div>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ isRealCapableBeta ? 'Render beta' : 'Planning preview' }}</p>
              <p class="mt-2 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                Costs {{ creditCost }} credits. {{ isRealCapableBeta ? 'Generates the paid ad and renders a final MP4 when the Shotstack beta provider is enabled.' : 'Creates a structured ad plan only — not a final MP4 render.' }}
              </p>
              <p v-if="formError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">{{ formError }}</p>
              <button type="submit" :disabled="isSubmitting" class="mt-5 min-h-12 w-full rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400">
                {{ isSubmitting ? submittingLabel : submitLabel }}
              </button>
            </div>
          </section>
        </form>
      </template>
    </div>
  </main>
</template>
