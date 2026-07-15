<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { ApiError } from '@/services/api'
import {
  generateMotionVideo,
  getCreatorProject,
  getProjectActions,
  getProjectGenerations,
} from '@/services/projects'
import {
  motionAspectRatioOptions,
  motionDurationOptions,
  motionPlatformOptions,
  motionStyleOptions,
  motionVisualStyleOptions,
  type CreatorGeneration,
  type CreatorProject,
  type GenerateMotionAiVideoPayload,
  type MotionAiVideoScriptSource,
  type ProjectAction,
} from '@/types/project'
import { generationDisplayName } from '@/utils/generations'

const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const generations = ref<CreatorGeneration[]>([])
const selectedProductImageAssetId = ref<number | null>(null)
const selectedScenarioImageAssetId = ref<number | null>(null)
const selectedCampaignGenerationId = ref<number | null>(null)
const selectedVoiceoverGenerationId = ref<number | null>(null)
const platform = ref('tiktok')
const aspectRatio = ref('vertical_9_16')
const targetDurationSeconds = ref(8)
const scriptSource = ref<MotionAiVideoScriptSource>('project_brief')
const manualScript = ref('')
const creativeNotes = ref('')
const visualStyle = ref('realistic_product')
const motionStyle = ref('slow_push_in')
const cameraDirection = ref('')
const sceneDescription = ref('')
const backgroundPrompt = ref('')
const negativePrompt = ref('')
const includeVoiceoverPlan = ref(true)
const includeCaptionPlan = ref(true)
const primaryCta = ref('')
const isLoading = ref(true)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')

const scriptSourceOptions: Array<{ value: MotionAiVideoScriptSource; label: string }> = [
  { value: 'campaign_script', label: 'Use campaign script' },
  { value: 'project_brief', label: 'Use project brief' },
  { value: 'manual_script', label: 'Write manual script' },
]

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const motionAction = computed(() => actions.value.find((item) => item.key === 'start_motion_video') ?? null)
const campaignGenerations = computed(() =>
  generations.value.filter(
    (item) =>
      item.status === 'completed' &&
      [
        'generate_affiliate_campaign',
        'generate_video_to_campaign',
        'generate_product_video_script',
        'generate_auto_video_ad',
      ].includes(item.job_type),
  ),
)
const voiceoverGenerations = computed(() =>
  generations.value.filter((item) => item.status === 'completed' && item.job_type === 'generate_ai_voiceover'),
)
const creditCost = computed(() => motionAction.value?.credit_cost ?? 3)
const isUpgradeRequired = computed(() =>
  motionAction.value?.upgrade_required === true || motionAction.value?.action_mode === 'upgrade',
)

function initializeForm(loadedProject: CreatorProject): void {
  const input = (loadedProject.input_payload ?? {}) as Record<string, unknown>

  selectedCampaignGenerationId.value = campaignGenerations.value[0]?.id ?? null
  selectedVoiceoverGenerationId.value = voiceoverGenerations.value[0]?.id ?? null
  scriptSource.value = selectedCampaignGenerationId.value === null ? 'project_brief' : 'campaign_script'
  primaryCta.value = typeof input.cta_preference === 'string' ? input.cta_preference : 'See product details'
  sceneDescription.value =
    typeof input.product_description === 'string' ? input.product_description : ''
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
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : 'We could not load Motion AI Video setup.'
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isUpgradeRequired.value) {
    await router.push(motionAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (selectedProductImageAssetId.value === null) {
    formError.value = 'Upload or select a product image before generating a motion video plan.'
    return
  }

  if (scriptSource.value === 'manual_script' && manualScript.value.trim() === '') {
    formError.value = 'Write a manual script or choose another script source.'
    return
  }

  isSubmitting.value = true

  const payload: GenerateMotionAiVideoPayload = {
    product_image_asset_id: selectedProductImageAssetId.value,
    scenario_image_asset_id: selectedScenarioImageAssetId.value,
    campaign_generation_id: selectedCampaignGenerationId.value,
    voiceover_generation_id: selectedVoiceoverGenerationId.value,
    platform: platform.value,
    target_duration_seconds: targetDurationSeconds.value,
    script_source: scriptSource.value,
    manual_script: scriptSource.value === 'manual_script' ? manualScript.value.trim() : null,
    creative_notes: creativeNotes.value.trim() || null,
    visual_style: visualStyle.value,
    motion_style: motionStyle.value,
    camera_direction: cameraDirection.value.trim() || null,
    scene_description: sceneDescription.value.trim() || null,
    background_prompt: backgroundPrompt.value.trim() || null,
    negative_prompt: negativePrompt.value.trim() || null,
    aspect_ratio: aspectRatio.value,
    include_voiceover_plan: includeVoiceoverPlan.value,
    include_caption_plan: includeCaptionPlan.value,
    primary_cta: primaryCta.value.trim() || null,
  }

  try {
    const response = await generateMotionVideo(projectId.value, payload)
    await router.push(`/projects/${projectId.value}/generations/${response.data.id}`)
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      formError.value = 'You do not have enough credits to generate this motion video plan.'
    } else {
      formError.value = error instanceof ApiError ? error.message : 'We could not generate this motion video plan.'
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
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30">
            ← Back to workspace
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Motion AI Video</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            Create a short visual-first motion product clip from your product image, scenario and campaign context.
          </p>
          <p class="mt-4 rounded-2xl border border-amber-900/50 bg-amber-950/20 p-4 text-sm leading-6 text-amber-100/80">
            Mock mode creates a render-ready plan. Real internal beta mode renders a base MP4 motion clip that can later be combined with voiceover, captions and final polish.
          </p>
        </header>

        <form class="mt-8 grid gap-5 lg:grid-cols-[1.05fr_0.95fr]" @submit.prevent="submit">
          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Product image</p>
              <h2 class="mt-2 text-xl font-semibold">Choose the source product image</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Product image is required. Upload or select it here without leaving this workflow.
              </p>

              <ProjectAssetInput
                v-model="selectedProductImageAssetId"
                :project-id="projectId"
                accepted-types="image"
                usage-role="product_image"
                title="Upload or choose product image"
                helper-text="Upload or select a clear product image. This is the main visual reference for the motion clip."
                required
                auto-select-first
                class="mt-5"
              />
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Scenario / background</p>
              <h2 class="mt-2 text-xl font-semibold">Add environment context</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Add a scenario image or describe the environment you want.
              </p>

              <ProjectAssetInput
                v-model="selectedScenarioImageAssetId"
                :project-id="projectId"
                accepted-types="image"
                usage-role="background_reference"
                title="Upload or choose scenario image"
                helper-text="Optional. Add a background or scene reference to guide the environment."
                class="mt-5"
              />

              <label class="mt-4 block">
                <span class="nusa-label">Scene description</span>
                <textarea v-model="sceneDescription" rows="3" class="nusa-textarea mt-2" placeholder="e.g. Product on a warm creator desk with soft daylight." />
              </label>

              <label class="mt-4 block">
                <span class="nusa-label">Background prompt</span>
                <textarea v-model="backgroundPrompt" rows="3" class="nusa-textarea mt-2" placeholder="Describe the background or environment." />
              </label>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Campaign / script source</p>
              <h2 class="mt-2 text-xl font-semibold">Choose the script context</h2>

              <label class="mt-5 block">
                <span class="nusa-label">Campaign generation</span>
                <select v-model.number="selectedCampaignGenerationId" class="nusa-select mt-2" data-testid="motion-campaign-select">
                  <option :value="null">No campaign selected</option>
                  <option v-for="generation in campaignGenerations" :key="generation.id" :value="generation.id">
                    {{ generationLabel(generation) }}
                  </option>
                </select>
              </label>

              <label class="mt-4 block">
                <span class="nusa-label">Script source</span>
                <select v-model="scriptSource" class="nusa-select mt-2" data-testid="motion-script-source-select">
                  <option v-for="option in scriptSourceOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label v-if="scriptSource === 'manual_script'" class="mt-4 block">
                <span class="nusa-label">Manual script</span>
                <textarea v-model="manualScript" rows="5" class="nusa-textarea mt-2" data-testid="motion-manual-script" placeholder="Write the narration or visual script Nusa should plan around." />
              </label>
            </div>
          </section>

          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Motion setup</p>
              <h2 class="mt-2 text-xl font-semibold">Style the motion plan</h2>

              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="nusa-label">Platform</span>
                  <select v-model="platform" class="nusa-select mt-2" data-testid="motion-platform-select">
                    <option v-for="option in motionPlatformOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">Aspect ratio</span>
                  <select v-model="aspectRatio" class="nusa-select mt-2" data-testid="motion-aspect-ratio-select">
                    <option v-for="option in motionAspectRatioOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">Duration</span>
                  <select v-model.number="targetDurationSeconds" class="nusa-select mt-2" data-testid="motion-duration-select">
                    <option v-for="duration in motionDurationOptions" :key="duration" :value="duration">
                      {{ duration }} seconds
                    </option>
                  </select>
                </label>

                <label class="block">
                  <span class="nusa-label">Visual style</span>
                  <select v-model="visualStyle" class="nusa-select mt-2" data-testid="motion-visual-style-select">
                    <option v-for="option in motionVisualStyleOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>

                <label class="block sm:col-span-2">
                  <span class="nusa-label">Motion style</span>
                  <select v-model="motionStyle" class="nusa-select mt-2" data-testid="motion-style-select">
                    <option v-for="option in motionStyleOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </label>
              </div>

              <label class="mt-4 block">
                <span class="nusa-label">Camera direction</span>
                <textarea v-model="cameraDirection" rows="3" class="nusa-textarea mt-2" placeholder="Optional camera notes, e.g. slow push-in then stable hero frame." />
              </label>

              <label class="mt-4 block">
                <span class="nusa-label">Creative notes</span>
                <textarea v-model="creativeNotes" rows="3" class="nusa-textarea mt-2" data-testid="motion-creative-notes" placeholder="Optional: describe the mood, product moment, or visual guidance for the clip." />
              </label>

              <label class="mt-4 block">
                <span class="nusa-label">Negative prompt</span>
                <textarea v-model="negativePrompt" rows="3" class="nusa-textarea mt-2" placeholder="Optional: warped product, fake logos, unreadable text..." />
              </label>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Voiceover and captions</p>
              <h2 class="mt-2 text-xl font-semibold">Plan narration and text</h2>

              <label class="mt-5 block">
                <span class="nusa-label">Voiceover generation</span>
                <select v-model.number="selectedVoiceoverGenerationId" class="nusa-select mt-2" data-testid="motion-voiceover-select">
                  <option :value="null">No voiceover selected</option>
                  <option v-for="generation in voiceoverGenerations" :key="generation.id" :value="generation.id">
                    {{ generationLabel(generation) }}
                  </option>
                </select>
              </label>

              <div class="mt-5 space-y-3">
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="includeVoiceoverPlan" type="checkbox" class="mt-1 size-4 accent-emerald-400">
                  <span>Include voiceover plan</span>
                </label>
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="includeCaptionPlan" type="checkbox" class="mt-1 size-4 accent-emerald-400">
                  <span>Include caption plan</span>
                </label>
              </div>

              <label class="mt-4 block">
                <span class="nusa-label">Primary CTA</span>
                <input v-model="primaryCta" class="nusa-input mt-2" placeholder="See product details" data-testid="motion-primary-cta-input">
              </label>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Ready to plan</p>
              <h2 class="mt-2 text-xl font-semibold">Generate Motion Video</h2>
              <p class="mt-3 text-sm leading-6 text-neutral-400">
                Costs {{ creditCost }} credits. Real provider mode creates a visual-first MP4 motion clip; mock mode creates a render-ready plan.
              </p>
              <p v-if="formError" role="alert" class="mt-4 rounded-2xl border border-rose-800 bg-rose-950/40 p-3 text-sm text-rose-200">
                {{ formError }}
              </p>
              <button
                type="submit"
                :disabled="isSubmitting"
                data-testid="motion-submit"
                class="mt-5 flex min-h-12 w-full items-center justify-center rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {{ isSubmitting ? 'Generating motion video...' : 'Generate Motion Video' }}
              </button>
            </div>
          </section>
        </form>
      </template>
    </div>
  </main>
</template>
