<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { ApiError } from '@/services/api'
import { generateMotionVideo, getCreatorProject, getProjectActions, getProjectGenerations } from '@/services/projects'
import {
  motionAspectRatioOptions,
  motionDurationOptions,
  motionStyleOptions,
  motionVisualStyleOptions,
  type CreatorGeneration,
  type CreatorProject,
  type GenerateMotionAiVideoPayload,
  type MotionAiVideoScriptSource,
  type MotionAiVideoSourceMode,
  type ProjectAction,
} from '@/types/project'
import { generationSelectorLabel, isMotionCampaignCompatible, positiveInt, workflowOriginForGeneration } from '@/utils/workflowLineage'

const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const generations = ref<CreatorGeneration[]>([])
const sourceMode = ref<MotionAiVideoSourceMode>('text_prompt')
const selectedProductImageAssetId = ref<number | null>(null)
const selectedScenarioImageAssetId = ref<number | null>(null)
const selectedCampaignGenerationId = ref<number | null>(null)
const selectedVoiceoverGenerationId = ref<number | null>(null)
const platform = ref('tiktok')
const aspectRatio = ref('vertical_9_16')
const targetDurationSeconds = ref(8)
const creativeNotes = ref('')
const visualStyle = ref('realistic_product')
const motionStyle = ref('slow_push_in')
const cameraDirection = ref('')
const sceneDescription = ref('')
const backgroundPrompt = ref('')
const negativePrompt = ref('')
const includeVoiceoverPlan = ref(false)
const includeCaptionPlan = ref(true)
const primaryCta = ref('')
const isLoading = ref(true)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')
const advancedOpen = ref(false)

type ConsumerSourceMode = Extract<MotionAiVideoSourceMode, 'text_prompt' | 'campaign_assisted' | 'image_first_frame'>

type SourceModeOption = {
  value: ConsumerSourceMode
  label: string
  eyebrow: string
  description: string
  requiredSummary: string
}

const visualStyleLabels: Record<string, string> = {
  realistic_product: 'Realistic product',
  ugc_lifestyle: 'Lifestyle',
  premium_brand: 'Premium',
  clean_studio: 'Minimal studio',
  cinematic: 'Cinematic',
  social_ad: 'Energetic social video',
}

const cameraMovementLabels: Record<string, string> = {
  slow_push_in: 'Slow push-in',
  handheld_ugc: 'Handheld lifestyle',
  product_orbit: 'Orbit around product',
  top_down_demo: 'Top-down demo',
  lifestyle_reveal: 'Dynamic reveal',
  before_after: 'Before / after reveal',
}

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const motionAction = computed(() => actions.value.find((item) => item.key === 'start_motion_video') ?? null)
const capabilities = computed(() => motionAction.value?.provider_capabilities ?? null)
const providerLabel = computed(() => capabilities.value?.provider_label ?? 'Mock')
const imageToVideoEnabled = computed(() => capabilities.value?.image_to_video_enabled === true)
const supportsRealRendering = computed(() => capabilities.value?.supports_real_rendering === true)
const sourceModeOptions = computed<SourceModeOption[]>(() => {
  const options: SourceModeOption[] = [
    {
      value: 'text_prompt',
      label: 'Describe a video',
      eyebrow: 'Text description',
      description: 'Create a new video from your product brief and creative direction.',
      requiredSummary: 'Requires a clear video description.',
    },
    {
      value: 'campaign_assisted',
      label: 'Use a saved campaign',
      eyebrow: 'Campaign to video',
      description: 'Turn a previous Product Content Kit campaign into video footage.',
      requiredSummary: 'Requires one completed campaign generation.',
    },
  ]

  if (imageToVideoEnabled.value) {
    options.push({
      value: 'image_first_frame',
      label: 'Animate a product image',
      eyebrow: 'Image to video',
      description: 'Use one product image as the first frame of the generated video.',
      requiredSummary: 'Requires exactly one product image.',
    })
  }

  return options
})
const selectedSourceOption = computed(() => sourceModeOptions.value.find((item) => item.value === sourceMode.value) ?? sourceModeOptions.value[0])
const usesProductImage = computed(() => sourceMode.value === 'image_first_frame')
const productImageRequired = computed(() => usesProductImage.value)
const campaignRequired = computed(() => sourceMode.value === 'campaign_assisted')
const productImageHelperText = 'Required for image-to-video. The selected image becomes the starting frame; exact logo, text or product-detail preservation is not guaranteed yet.'
const campaignGenerations = computed(() =>
  generations.value.filter(isMotionCampaignCompatible),
)
const voiceoverGenerations = computed(() =>
  generations.value.filter((item) => item.status === 'completed' && item.job_type === 'generate_ai_voiceover'),
)
const selectedCampaignGeneration = computed(() => campaignGenerations.value.find((item) => item.id === selectedCampaignGenerationId.value) ?? null)
const creditCost = computed(() => motionAction.value?.credit_cost ?? 3)
const isUpgradeRequired = computed(() => motionAction.value?.upgrade_required === true || motionAction.value?.action_mode === 'upgrade')
const visualStyleChoices = computed(() => motionVisualStyleOptions.map((option) => ({ ...option, label: visualStyleLabels[option.value] ?? option.label })))
const cameraMovementChoices = computed(() => motionStyleOptions.map((option) => ({ ...option, label: cameraMovementLabels[option.value] ?? option.label })))
const durationChoices = computed(() => supportsRealRendering.value ? [capabilities.value?.default_duration_seconds ?? 8] : [...motionDurationOptions])
const aspectRatioChoices = computed(() => supportsRealRendering.value ? motionAspectRatioOptions.filter((option) => option.value === 'vertical_9_16') : motionAspectRatioOptions)
const expectedBaseDuration = computed(() => capabilities.value?.default_duration_seconds ?? targetDurationSeconds.value)
const selectedModeSummary = computed(() => {
  if (sourceMode.value === 'campaign_assisted') {
    return selectedCampaignGeneration.value ? `Saved campaign: ${generationLabel(selectedCampaignGeneration.value)}` : 'Saved campaign required'
  }

  if (sourceMode.value === 'image_first_frame') {
    return selectedProductImageAssetId.value === null ? 'Product image required' : `Product image #${selectedProductImageAssetId.value} as starting frame`
  }

  return 'Text description with project brief'
})
const canUseVoiceoverTiming = computed(() => voiceoverGenerations.value.length > 0)
const effectiveVoiceoverEnabled = computed(() => includeVoiceoverPlan.value && selectedVoiceoverGenerationId.value !== null)
const primaryDescriptionEmpty = computed(() => sceneDescription.value.trim() === '')
const formBlockingReason = computed(() => {
  if (isSubmitting.value) {
    return 'Generation is already being submitted.'
  }

  if (isUpgradeRequired.value) {
    return ''
  }

  if (sourceMode.value === 'image_first_frame' && !imageToVideoEnabled.value) {
    return 'Image-to-video is not enabled for this workspace yet.'
  }

  if (campaignRequired.value && selectedCampaignGenerationId.value === null) {
    return 'Choose a saved campaign first.'
  }

  if (productImageRequired.value && selectedProductImageAssetId.value === null) {
    return 'Choose one product image first.'
  }

  if (primaryDescriptionEmpty.value) {
    return 'Describe the video you want first.'
  }

  return ''
})
const canSubmit = computed(() => formBlockingReason.value === '')
const generateButtonLabel = computed(() => {
  if (isSubmitting.value) {
    return 'Generating video...'
  }

  if (isUpgradeRequired.value) {
    return 'View plan options'
  }

  return sourceMode.value === 'image_first_frame' ? 'Animate product image' : 'Generate video'
})

function initializeForm(loadedProject: CreatorProject): void {
  const input = (loadedProject.input_payload ?? {}) as Record<string, unknown>
  const initialDescription = firstString([input.content_idea, input.product_description, input.target_audience])

  selectedCampaignGenerationId.value = null
  selectedVoiceoverGenerationId.value = voiceoverGenerations.value[0]?.id ?? null
  includeVoiceoverPlan.value = selectedVoiceoverGenerationId.value !== null
  primaryCta.value = typeof input.cta_preference === 'string' ? input.cta_preference : ''

  if (sceneDescription.value.trim() === '' && initialDescription !== null) {
    sceneDescription.value = initialDescription
  }

  targetDurationSeconds.value = durationChoices.value[0] ?? 8
  aspectRatio.value = aspectRatioChoices.value[0]?.value ?? 'vertical_9_16'
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, generationResponse, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      getProjectGenerations(projectId.value),
      getProjectActions(projectId.value),
    ])

    project.value = loadedProject
    generations.value = Array.isArray(generationResponse.data) ? generationResponse.data : []
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
    initializeForm(loadedProject)
    applyQueryPreselection()
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : 'We could not load Motion AI Video setup.'
  } finally {
    isLoading.value = false
  }
}

function selectSourceMode(mode: ConsumerSourceMode): void {
  sourceMode.value = mode
  formError.value = ''

  if (mode === 'campaign_assisted' && selectedCampaignGenerationId.value === null) {
    selectedCampaignGenerationId.value = campaignGenerations.value[0]?.id ?? null
  }

  if (mode !== 'campaign_assisted') {
    selectedCampaignGenerationId.value = null
  }

  if (mode !== 'image_first_frame') {
    selectedProductImageAssetId.value = null
  }
}

function scriptSourceForMode(): MotionAiVideoScriptSource {
  return sourceMode.value === 'campaign_assisted' ? 'campaign_script' : 'project_brief'
}

function handleVoiceoverToggle(): void {
  if (!includeVoiceoverPlan.value) {
    selectedVoiceoverGenerationId.value = null
    return
  }

  selectedVoiceoverGenerationId.value = voiceoverGenerations.value[0]?.id ?? null

  if (selectedVoiceoverGenerationId.value === null) {
    includeVoiceoverPlan.value = false
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isSubmitting.value) {
    return
  }

  if (isUpgradeRequired.value) {
    await router.push(motionAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (!canSubmit.value) {
    formError.value = formBlockingReason.value
    return
  }

  isSubmitting.value = true

  const payload: GenerateMotionAiVideoPayload = {
    source_mode: sourceMode.value,
    product_image_asset_id: usesProductImage.value ? selectedProductImageAssetId.value : null,
    scenario_image_asset_id: selectedScenarioImageAssetId.value,
    campaign_generation_id: campaignRequired.value ? selectedCampaignGenerationId.value : null,
    voiceover_generation_id: effectiveVoiceoverEnabled.value ? selectedVoiceoverGenerationId.value : null,
    platform: platform.value,
    target_duration_seconds: targetDurationSeconds.value,
    script_source: scriptSourceForMode(),
    manual_script: null,
    creative_notes: creativeNotes.value.trim() || null,
    visual_style: visualStyle.value,
    motion_style: motionStyle.value,
    camera_direction: cameraDirection.value.trim() || null,
    scene_description: sceneDescription.value.trim() || null,
    background_prompt: backgroundPrompt.value.trim() || null,
    negative_prompt: negativePrompt.value.trim() || null,
    aspect_ratio: aspectRatio.value,
    include_voiceover_plan: effectiveVoiceoverEnabled.value,
    include_caption_plan: includeCaptionPlan.value,
    primary_cta: primaryCta.value.trim() || null,
  }

  try {
    const response = await generateMotionVideo(projectId.value, payload)
    await router.push(`/projects/${projectId.value}/generations/${response.data.id}`)
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      formError.value = 'You do not have enough credits to generate this motion video.'
    } else {
      formError.value = error instanceof ApiError ? error.message : 'We could not generate this motion video.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function generationLabel(generation: CreatorGeneration): string {
  return generationSelectorLabel(generation, project.value)
}

function applyQueryPreselection(): void {
  const querySourceMode = Array.isArray(route.query.sourceMode) ? route.query.sourceMode[0] : route.query.sourceMode

  if (querySourceMode === 'campaign_assisted') {
    const campaignGenerationId = positiveInt(Array.isArray(route.query.campaignGenerationId) ? route.query.campaignGenerationId[0] : route.query.campaignGenerationId)
    const generation = campaignGenerationId === null
      ? null
      : campaignGenerations.value.find((item) => item.id === campaignGenerationId) ?? null

    if (generation !== null) {
      sourceMode.value = 'campaign_assisted'
      selectedCampaignGenerationId.value = generation.id
    }
  }

  if (querySourceMode === 'text_prompt' || querySourceMode === 'image_first_frame') {
    if (sourceModeOptions.value.some((option) => option.value === querySourceMode)) {
      sourceMode.value = querySourceMode
    }
  }
}

function firstString(values: unknown[]): string | null {
  for (const value of values) {
    if (typeof value === 'string' && value.trim() !== '') {
      return value
    }
  }

  return null
}

function campaignPreview(generation: CreatorGeneration): string {
  const output = generation.result_payload && typeof generation.result_payload === 'object'
    ? generation.result_payload as unknown as Record<string, unknown>
    : {}
  const text = firstString([output.campaign_title, output.campaign_angle, output.product_angle, output.caption])

  return text ?? 'Completed campaign context ready for a video prompt.'
}

onMounted(loadPage)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-4xl px-5 pb-28 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-4 w-36 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-8 h-80 animate-pulse rounded-[2rem] bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">Loading Motion AI Video setup...</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">Motion AI Video unavailable</p>
        <h1 class="mt-3 text-3xl font-bold">This setup needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button type="button" class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950" @click="loadPage">
          Try again
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300">
            ← Back to workspace
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Motion AI Video</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            Create short visual-first product footage for social commerce. Nusa keeps captions, CTA overlays and voiceover composition honest as post-production steps.
          </p>
          <p class="mt-4 max-w-2xl rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-4 text-sm leading-6 text-emerald-100/80">
            {{ providerLabel }} creates the base visual clip. Final captions, narration and platform polish can be added later in the workspace.
          </p>
        </header>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
          <section class="nusa-card-premium rounded-[2rem] p-5">
            <fieldset>
              <legend>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">1 · How should the video start?</span>
                <span class="mt-2 block text-xl font-semibold text-white">Choose the source</span>
              </legend>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Pick one clear starting point. Advanced provider and transport details stay out of the creator flow.
              </p>

              <div class="mt-5 space-y-3" role="radiogroup" aria-label="Motion AI Video source">
                <div v-for="option in sourceModeOptions" :key="option.value" class="rounded-[1.75rem] border border-neutral-800 bg-neutral-950/30 p-2">
                  <button
                    type="button"
                    role="radio"
                    :aria-checked="sourceMode === option.value"
                    :data-testid="`motion-source-mode-${option.value}`"
                    class="w-full rounded-[1.35rem] border p-4 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300"
                    :class="sourceMode === option.value ? 'border-emerald-400 bg-emerald-950/30' : 'border-neutral-800 bg-neutral-950/60 hover:border-neutral-600'"
                    @click="selectSourceMode(option.value)"
                  >
                    <span class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                      <span class="min-w-0">
                        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-300">{{ option.eyebrow }}</span>
                        <span class="mt-2 block text-lg font-semibold text-white">{{ option.label }}</span>
                        <span class="mt-2 block text-sm leading-6 text-neutral-400">{{ option.description }}</span>
                      </span>
                      <span class="rounded-full border border-neutral-700 px-3 py-1 text-xs font-semibold text-neutral-300">
                        {{ sourceMode === option.value ? 'Selected' : 'Choose' }}
                      </span>
                    </span>
                    <span class="mt-3 block text-xs leading-5 text-neutral-500">{{ option.requiredSummary }}</span>
                  </button>

                  <div v-if="sourceMode === option.value && option.value === 'campaign_assisted'" class="mt-3 rounded-[1.25rem] border border-emerald-900/50 bg-emerald-950/20 p-4" data-testid="motion-campaign-inline-panel">
                    <label class="block">
                      <span class="nusa-label">Saved campaign *</span>
                      <select v-model.number="selectedCampaignGenerationId" class="nusa-select mt-2" data-testid="motion-campaign-select" required>
                        <option :value="null">Choose a campaign generation</option>
                        <option v-for="generation in campaignGenerations" :key="generation.id" :value="generation.id">
                          {{ generationLabel(generation) }}
                        </option>
                      </select>
                    </label>
                    <div v-if="selectedCampaignGeneration" class="mt-3 rounded-2xl border border-emerald-900/50 bg-neutral-950/50 p-3 text-sm leading-6 text-emerald-100/80">
                      <span class="block font-semibold text-emerald-200">Selected campaign context</span>
                      <span class="block text-neutral-300">{{ campaignPreview(selectedCampaignGeneration) }}</span>
                      <span class="mt-1 block text-xs font-semibold text-emerald-200">{{ workflowOriginForGeneration(selectedCampaignGeneration).label }}</span>
                      <span class="block text-xs text-neutral-500">Nusa summarizes the campaign into text-based creative direction. No video bytes are sent from this mode.</span>
                    </div>
                    <p v-if="campaignGenerations.length === 0" class="mt-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-400">
                      No compatible Product Content Kit or Analyze Footage generations yet. Create one first, or use text description mode.
                    </p>
                  </div>

                  <div v-if="sourceMode === option.value && option.value === 'image_first_frame'" class="mt-3 rounded-[1.25rem] border border-emerald-900/50 bg-emerald-950/20 p-4" data-testid="motion-image-inline-panel">
                    <ProjectAssetInput
                      v-model="selectedProductImageAssetId"
                      :project-id="projectId"
                      accepted-types="image"
                      usage-role="product_image"
                      title="Upload or choose product image *"
                      :helper-text="productImageHelperText"
                      :required="productImageRequired"
                      auto-select-first
                    />
                  </div>
                </div>
              </div>

              <p v-if="!imageToVideoEnabled" class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-xs leading-5 text-neutral-500">
                Product image animation appears here only when the verified first-frame provider capability is enabled. Reference images remain disabled.
              </p>
            </fieldset>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">2 · Describe the video</p>
            <h2 class="mt-2 text-xl font-semibold">Describe the video you want</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-400">
              Explain what should happen in the video, how the product should appear, and the feeling you want.
            </p>
            <label class="mt-5 block" for="motion-scene-description">
              <span class="nusa-label">Video description *</span>
              <textarea
                id="motion-scene-description"
                v-model="sceneDescription"
                rows="6"
                class="nusa-textarea mt-2"
                data-testid="motion-main-description"
                placeholder="Show the product on a clean studio surface with a slow camera push-in, soft lighting and a premium look."
                required
              />
            </label>
            <p class="mt-2 text-xs leading-5 text-neutral-500">
              This maps to Nusa’s scene description field and becomes the main creative instruction for the provider.
            </p>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">3 · Basic video settings</p>
            <h2 class="mt-2 text-xl font-semibold">Set the look and format</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              <label class="block">
                <span class="nusa-label">Visual style</span>
                <select v-model="visualStyle" class="nusa-select mt-2" data-testid="motion-visual-style-select">
                  <option v-for="option in visualStyleChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="nusa-label">Camera movement</span>
                <select v-model="motionStyle" class="nusa-select mt-2" data-testid="motion-style-select">
                  <option v-for="option in cameraMovementChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="nusa-label">Duration</span>
                <select v-model.number="targetDurationSeconds" class="nusa-select mt-2" data-testid="motion-duration-select">
                  <option v-for="duration in durationChoices" :key="duration" :value="duration">
                    {{ duration }} seconds
                  </option>
                </select>
                <span v-if="supportsRealRendering" class="mt-2 block text-xs leading-5 text-neutral-500">
                  Current Veo beta output is generated as an {{ expectedBaseDuration }}s base clip.
                </span>
              </label>

              <label class="block">
                <span class="nusa-label">Aspect ratio</span>
                <select v-model="aspectRatio" class="nusa-select mt-2" data-testid="motion-aspect-ratio-select">
                  <option v-for="option in aspectRatioChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
                <span v-if="supportsRealRendering" class="mt-2 block text-xs leading-5 text-neutral-500">
                  Verified production flow currently uses vertical 9:16.
                </span>
              </label>
            </div>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">4 · Post-production</p>
            <h2 class="mt-2 text-xl font-semibold">Prepare captions and pacing</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-400">
              These options prepare the visual footage for captions or voiceover that can be added after the Veo clip is generated.
            </p>

            <div class="mt-5 space-y-3">
              <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4 text-sm text-neutral-300">
                <input v-model="includeCaptionPlan" type="checkbox" class="mt-1 size-4 accent-emerald-400" data-testid="motion-caption-plan-toggle">
                <span>
                  <span class="block font-semibold text-neutral-100">Prepare space for captions</span>
                  <span class="mt-1 block leading-6 text-neutral-400">The generated footage will leave readable space for captions added later. Captions are not burned into the Veo MP4.</span>
                </span>
              </label>

              <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4 text-sm text-neutral-300" :class="!canUseVoiceoverTiming ? 'opacity-70' : ''">
                <input
                  v-model="includeVoiceoverPlan"
                  type="checkbox"
                  class="mt-1 size-4 accent-emerald-400"
                  data-testid="motion-voiceover-plan-toggle"
                  :disabled="!canUseVoiceoverTiming"
                  @change="handleVoiceoverToggle"
                >
                <span>
                  <span class="block font-semibold text-neutral-100">Use voiceover timing</span>
                  <span class="mt-1 block leading-6 text-neutral-400">Use an existing voiceover to guide pacing. The audio is added during post-production.</span>
                  <span v-if="!canUseVoiceoverTiming" class="mt-2 block text-xs text-amber-200">Generate or select a voiceover first to use timing guidance.</span>
                </span>
              </label>
            </div>

            <label v-if="includeVoiceoverPlan && canUseVoiceoverTiming" class="mt-4 block">
              <span class="nusa-label">Voiceover for pacing</span>
              <select v-model.number="selectedVoiceoverGenerationId" class="nusa-select mt-2" data-testid="motion-voiceover-select">
                <option :value="null">No voiceover selected</option>
                <option v-for="generation in voiceoverGenerations" :key="generation.id" :value="generation.id">
                  {{ generationLabel(generation) }}
                </option>
              </select>
            </label>

            <label class="mt-4 block">
              <span class="nusa-label">CTA for later edit</span>
              <input v-model="primaryCta" class="nusa-input mt-2" placeholder="See product details" data-testid="motion-primary-cta-input">
              <span class="mt-2 block text-xs leading-5 text-neutral-500">Optional. If left empty, Nusa may suggest a generic CTA and label it as suggested.</span>
            </label>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-4 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300"
              :aria-expanded="advancedOpen"
              aria-controls="motion-advanced-settings"
              data-testid="motion-advanced-toggle"
              @click="advancedOpen = !advancedOpen"
            >
              <span>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">5 · Advanced settings</span>
                <span class="mt-2 block text-xl font-semibold text-white">Fine-tune optional context</span>
              </span>
              <span class="rounded-full border border-neutral-700 px-3 py-1 text-sm font-semibold text-neutral-300">
                {{ advancedOpen ? 'Hide' : 'Show' }}
              </span>
            </button>

            <div v-show="advancedOpen" id="motion-advanced-settings" class="mt-5 space-y-4">
              <ProjectAssetInput
                v-model="selectedScenarioImageAssetId"
                :project-id="projectId"
                accepted-types="image"
                usage-role="background_reference"
                title="Upload or choose scenario image"
                helper-text="Optional. Add a background or scene reference to guide the environment."
              />

              <label class="block">
                <span class="nusa-label">Background or setting</span>
                <textarea v-model="backgroundPrompt" rows="3" class="nusa-textarea mt-2" placeholder="Minimal light-grey studio with soft shadows." />
              </label>

              <label class="block">
                <span class="nusa-label">Detailed camera direction</span>
                <textarea v-model="cameraDirection" rows="3" class="nusa-textarea mt-2" placeholder="Optional: slow push-in, then hold a stable hero frame." />
              </label>

              <label class="block">
                <span class="nusa-label">Things to avoid</span>
                <textarea v-model="negativePrompt" rows="3" class="nusa-textarea mt-2" data-testid="motion-things-to-avoid" placeholder="Busy background, shaky camera, distorted product, visible text." />
                <span class="mt-2 block text-xs leading-5 text-neutral-500">Optional. System safety and visual-integrity rules still apply separately.</span>
              </label>

              <label class="block">
                <span class="nusa-label">Additional notes</span>
                <textarea v-model="creativeNotes" rows="3" class="nusa-textarea mt-2" data-testid="motion-additional-notes" placeholder="Optional: add mood, product moment or extra visual guidance." />
              </label>
            </div>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5" data-testid="motion-generate-card">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">6 · Generate</p>
            <h2 class="mt-2 text-xl font-semibold">Ready to create the base clip</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
              <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
                <span class="block text-xs text-neutral-500">Cost</span>
                <span class="mt-1 block font-semibold text-neutral-100">{{ creditCost }} credits</span>
              </p>
              <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
                <span class="block text-xs text-neutral-500">Base duration</span>
                <span class="mt-1 block font-semibold text-neutral-100">{{ expectedBaseDuration }}s</span>
              </p>
              <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
                <span class="block text-xs text-neutral-500">Source</span>
                <span class="mt-1 block font-semibold text-neutral-100">{{ selectedSourceOption?.label }}</span>
              </p>
            </div>
            <p class="mt-4 text-sm leading-6 text-neutral-400">
              {{ selectedModeSummary }}. Veo creates the visual footage first; captions, voiceover and final overlays are post-production.
            </p>
            <p v-if="formBlockingReason && !isSubmitting" class="mt-3 text-xs leading-5 text-amber-200" data-testid="motion-submit-helper">
              {{ formBlockingReason }}
            </p>
            <p v-if="formError" role="alert" class="mt-4 rounded-2xl border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">
              {{ formError }}
            </p>
            <button
              type="submit"
              :disabled="!canSubmit"
              data-testid="motion-submit"
              class="mt-5 flex min-h-12 w-full items-center justify-center rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ generateButtonLabel }}
            </button>
          </section>
        </form>
      </template>
    </div>
  </main>
</template>
