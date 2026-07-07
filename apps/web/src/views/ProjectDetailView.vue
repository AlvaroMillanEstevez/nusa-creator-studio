<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import AffiliateCampaignBrief from '@/components/AffiliateCampaignBrief.vue'
import AiVoiceoverBrief from '@/components/AiVoiceoverBrief.vue'
import AppNav from '@/components/AppNav.vue'
import FashionTryOnBrief from '@/components/FashionTryOnBrief.vue'
import GenerationActionCard from '@/components/GenerationActionCard.vue'
import GenerationHistory from '@/components/GenerationHistory.vue'
import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import MotionVideoBrief from '@/components/MotionVideoBrief.vue'
import ProductToVideoBrief from '@/components/ProductToVideoBrief.vue'
import ProjectAssets from '@/components/ProjectAssets.vue'
import VideoEnhancerBrief from '@/components/VideoEnhancerBrief.vue'
import { ApiError } from '@/services/api'
import {
  generateAiVoiceover,
  generateCampaignProject,
  generateFashionTryOn,
  generateMotionVideo,
  generateProductVideoScript,
  generateVideoEnhancerProject,
  getCreatorProject,
  getGeneration,
  getProjectGenerations,
} from '@/services/projects'
import { getCreatorTools } from '@/services/tools'
import type { MediaAsset } from '@/types/media'
import type { CreatorGeneration, CreatorProject, GenerationStatus } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const POLL_INTERVAL_MS = 2_000
const QUEUE_HINT_AFTER_POLLS = 3

const route = useRoute()
const project = ref<CreatorProject | null>(null)
const generation = ref<CreatorGeneration | null>(null)
const generationHistory = ref<CreatorGeneration[]>([])
const projectAssets = ref<MediaAsset[]>([])
const isLoading = ref(true)
const isGenerating = ref(false)
const isHistoryLoading = ref(false)
const loadError = ref('')
const loadErrorStatus = ref<number | null>(null)
const generationError = ref('')
const historyError = ref('')
const remainingCredits = ref<number | null>(null)
const toolCreditCost = ref<number | null>(null)
const showQueueWorkerHint = ref(false)

const hasRequiredFashionTryOnAssets = computed(() => {
  if (project.value?.type !== 'fashion_try_on') return true

  const hasModelReference = projectAssets.value.some(
    (asset) => asset.type === 'image' && asset.usage_role === 'model_reference',
  )
  const hasGarmentReference = projectAssets.value.some(
    (asset) => asset.type === 'image' && asset.usage_role === 'garment_reference',
  )

  return hasModelReference && hasGarmentReference
})

const hasRequiredMotionVideoAssets = computed(() => {
  if (project.value?.type !== 'motion_ai_video') return true

  const hasRoleTaggedVisualReference = projectAssets.value.some((asset) => {
    if (asset.type === 'video') {
      return asset.usage_role === 'source_video'
    }

    if (asset.type === 'image') {
      return ['product_image', 'background_reference', 'brand_reference'].includes(
        asset.usage_role ?? '',
      )
    }

    return false
  })

  const hasFallbackVisualReference = projectAssets.value.some((asset) =>
    ['image', 'video'].includes(asset.type),
  )

  return hasRoleTaggedVisualReference || hasFallbackVisualReference
})

let pollTimer: ReturnType<typeof setTimeout> | null = null
let pollingGenerationId: number | null = null
let pollCount = 0

const emptyHistoryCopy = computed(() => {
  if (project.value?.type === 'motion_ai_video') {
    return 'No saved generations yet. Generate your first motion video mock to create a scene plan, motion prompt, editing notes and provider-ready payload preview.'
  }

  if (project.value?.type === 'fashion_try_on') {
    return 'No saved generations yet. Generate your first try-on mock to create a concept plan, prompts, reference notes and a quality checklist.'
  }

  if (project.value?.type === 'ai_voiceover') {
    return 'No saved generations yet. Generate your first voiceover plan to create narration, timing, delivery notes and audio-ready guidance.'
  }

  if (project.value?.type === 'product_to_video') {
    return 'No saved generations yet. Generate your first video script pack to create a storyboard, shot list and production notes.'
  }

  if (project.value?.type === 'video_enhancer') {
    return 'No saved generations yet. Generate your first caption pack to create subtitles, cuts, posting text and hashtags.'
  }

  return 'No saved generations yet. Generate your first campaign to create hooks, script, caption and hashtags.'
})

function currentProjectId(): string | undefined {
  const routeId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id

  return routeId || undefined
}

function isTerminalStatus(status: GenerationStatus): boolean {
  return status === 'completed' || status === 'failed'
}

function stopPolling(): void {
  if (pollTimer !== null) {
    clearTimeout(pollTimer)
    pollTimer = null
  }

  pollingGenerationId = null
  pollCount = 0
  showQueueWorkerHint.value = false
}

function upsertGeneration(updated: CreatorGeneration, select = false): void {
  generationHistory.value = [
    updated,
    ...generationHistory.value.filter((item) => item.id !== updated.id),
  ]

  if (select || generation.value?.id === updated.id) {
    generation.value = updated
  }
}

function schedulePoll(): void {
  if (pollingGenerationId === null || pollTimer !== null) {
    return
  }

  pollTimer = setTimeout(() => {
    pollTimer = null
    void pollGeneration()
  }, POLL_INTERVAL_MS)
}

async function pollGeneration(): Promise<void> {
  const generationId = pollingGenerationId

  if (generationId === null) {
    return
  }

  try {
    const updated = await getGeneration(generationId)

    if (pollingGenerationId !== generationId) {
      return
    }

    upsertGeneration(updated)

    if (isTerminalStatus(updated.status)) {
      stopPolling()
      isGenerating.value = false
      return
    }

    pollCount += 1
    showQueueWorkerHint.value = import.meta.env.DEV && pollCount >= QUEUE_HINT_AFTER_POLLS
    schedulePoll()
  } catch {
    if (pollingGenerationId !== generationId) {
      return
    }

    stopPolling()
    isGenerating.value = false
    generationError.value = 'We could not check the generation status. Please try again.'
  }
}

function startPolling(target: CreatorGeneration): void {
  if (isTerminalStatus(target.status)) {
    return
  }

  stopPolling()
  pollingGenerationId = target.id
  isGenerating.value = true
  schedulePoll()
}

function updateProjectAssets(assets: MediaAsset[]): void {
  projectAssets.value = assets
}

function selectGeneration(selected: CreatorGeneration): void {
  generation.value = selected

  if (!isTerminalStatus(selected.status) && pollingGenerationId !== selected.id) {
    startPolling(selected)
  }
}

async function loadGenerationHistory(projectId: string): Promise<void> {
  isHistoryLoading.value = true
  historyError.value = ''

  try {
    const response = await getProjectGenerations(projectId)

    generationHistory.value = response.data
    generation.value = response.data[0] ?? null

    if (generation.value !== null && !isTerminalStatus(generation.value.status)) {
      startPolling(generation.value)
    }
  } catch (error: unknown) {
    historyError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not load generation history. You can still generate a new result.'
  } finally {
    isHistoryLoading.value = false
  }
}

async function loadToolCreditCost(): Promise<void> {
  try {
    const tools = await getCreatorTools()
    const tool = Array.isArray(tools)
      ? tools.find((item) => item.key === project.value?.type)
      : undefined

    toolCreditCost.value = tool?.credit_cost ?? null
  } catch {
    toolCreditCost.value = null
  }
}

async function loadProject(): Promise<void> {
  isLoading.value = true
  loadError.value = ''
  loadErrorStatus.value = null

  const projectId = currentProjectId()

  if (!projectId) {
    loadError.value = 'This project link is invalid.'
    isLoading.value = false
    return
  }

  const historyRequest = loadGenerationHistory(projectId)

  try {
    project.value = await getCreatorProject(projectId)
    await loadToolCreditCost()
  } catch (error: unknown) {
    if (error instanceof ApiError && [403, 404].includes(error.status)) {
      loadErrorStatus.value = error.status
      loadError.value = 'This project does not exist or you do not have access to it.'
    } else {
      loadError.value =
        error instanceof ApiError
          ? error.message
          : 'We could not load this project. Check the API connection and try again.'
    }
  } finally {
    isLoading.value = false
    await historyRequest
  }
}

async function generateContent(): Promise<void> {
  const projectId = currentProjectId()

  if (!projectId || !project.value) {
    return
  }

  stopPolling()
  generationError.value = ''
  isGenerating.value = true

  try {
    let generated: CreatorGeneration
    let creditsBalance: number

    if (project.value.type === 'motion_ai_video') {
      const response = await generateMotionVideo(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    } else if (project.value.type === 'fashion_try_on') {
      const response = await generateFashionTryOn(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    } else if (project.value.type === 'ai_voiceover') {
      const response = await generateAiVoiceover(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    } else if (project.value.type === 'product_to_video') {
      const response = await generateProductVideoScript(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    } else if (project.value.type === 'video_enhancer') {
      const response = await generateVideoEnhancerProject(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    } else {
      const response = await generateCampaignProject(projectId)
      generated = response.data
      creditsBalance = response.meta.credits_balance
    }

    historyError.value = ''
    upsertGeneration(generated, true)
    remainingCredits.value = creditsBalance

    if (isTerminalStatus(generated.status)) {
      isGenerating.value = false
    } else {
      startPolling(generated)
    }
  } catch (error: unknown) {
    isGenerating.value = false

    if (
      error instanceof ApiError &&
      (error.code === 'insufficient_credits' || error.status === 402)
    ) {
      const requiredCredits = toolCreditCost.value

      generationError.value =
        requiredCredits === null
          ? 'You do not have enough platform credits to generate this content. Your balance was not changed.'
          : `You need at least ${requiredCredits} platform ${requiredCredits === 1 ? 'credit' : 'credits'} to generate this content. Your balance was not changed.`
    } else {
      generationError.value =
        error instanceof ApiError
          ? error.message
          : 'We could not generate this content. Please try again.'
    }
  }
}

onMounted(loadProject)
onBeforeUnmount(stopPolling)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-7xl px-5 pb-16 pt-6 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-16" aria-live="polite">
        <div class="h-4 w-28 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-3 h-10 w-3/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-10 h-56 animate-pulse rounded-3xl bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">Loading project...</p>
      </section>

      <section
        v-else-if="loadErrorStatus === 403 || loadErrorStatus === 404"
        class="nusa-card-premium mt-12 rounded-[2rem] p-6"
      >
        <p class="text-sm font-medium text-amber-400">Project access</p>
        <h1 class="mt-3 text-3xl font-bold">Project unavailable</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">
          This project does not exist or you do not have access to it.
        </p>
        <div class="mt-7 grid gap-3 sm:grid-cols-2">
          <RouterLink
            to="/dashboard"
            class="flex min-h-12 items-center justify-center rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          >
            Back to dashboard
          </RouterLink>
          <RouterLink
            to="/tools"
            class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
          >
            Browse tools
          </RouterLink>
        </div>
      </section>

      <section v-else-if="loadError" class="mt-16">
        <p class="text-sm font-medium text-rose-400">Unable to load project</p>
        <h1 class="mt-3 text-3xl font-bold">This page needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button
          type="button"
          class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950"
          @click="loadProject"
        >
          Try again
        </button>
      </section>

      <template v-else-if="project">
        <section class="mt-10">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full border border-emerald-800 bg-emerald-950 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-300"
            >
              {{ formatLabel(project.status) }}
            </span>
            <span class="text-xs font-medium uppercase tracking-[0.16em] text-neutral-500">
              {{ formatLabel(project.type) }}
            </span>
          </div>
          <h1 class="mt-5 text-3xl font-bold leading-tight sm:text-4xl">{{ project.title }}</h1>
          <p class="mt-3 text-sm text-neutral-500">Project #{{ project.id }}</p>
        </section>

        <AffiliateCampaignBrief v-if="project.type === 'affiliate_campaign'" :project="project" />
        <VideoEnhancerBrief v-else-if="project.type === 'video_enhancer'" :project="project" />
        <ProductToVideoBrief v-else-if="project.type === 'product_to_video'" :project="project" />
        <AiVoiceoverBrief v-else-if="project.type === 'ai_voiceover'" :project="project" />
        <FashionTryOnBrief v-else-if="project.type === 'fashion_try_on'" :project="project" />
        <MotionVideoBrief v-else-if="project.type === 'motion_ai_video'" :project="project" />

        <ProjectAssets
          :project-id="project.id"
          :project-type="project.type"
          @assets-changed="updateProjectAssets"
        />

        <div
          v-if="project.type === 'fashion_try_on' && !hasRequiredFashionTryOnAssets"
          class="mt-5 rounded-2xl border border-amber-900/60 bg-amber-950/30 p-4 text-sm leading-6 text-amber-100"
        >
          Add a model reference image and garment reference image before generating.
        </div>

        <div
          v-if="project.type === 'motion_ai_video' && !hasRequiredMotionVideoAssets"
          class="mt-5 rounded-2xl border border-amber-900/60 bg-amber-950/30 p-4 text-sm leading-6 text-amber-100"
        >
          Add a product image, source video, background reference or brand reference before generating.
        </div>

        <GenerationActionCard
          :project-type="project.type"
          :has-generation="generation !== null"
          :is-generating="isGenerating"
          :error="generationError"
          :remaining-credits="remainingCredits"
          :credit-cost="toolCreditCost"
          @generate="generateContent"
        />

        <GenerationHistory
          :generations="generationHistory"
          :selected-generation="generation"
          :is-loading="isHistoryLoading"
          :error="historyError"
          :empty-copy="emptyHistoryCopy"
          @select="selectGeneration"
          @retry="loadGenerationHistory(String(project.id))"
        />

        <GenerationResultRenderer
          :generation="generation"
          :show-queue-worker-hint="showQueueWorkerHint"
        />
      </template>
    </div>
  </main>
</template>
