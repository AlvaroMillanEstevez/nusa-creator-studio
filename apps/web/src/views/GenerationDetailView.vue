<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import ProjectActionHub from '@/components/ProjectActionHub.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import {
  getCreatorProject,
  getGeneration,
  getProjectActions,
} from '@/services/projects'
import { listProjectMediaAssets } from '@/services/media'
import type { MediaAsset } from '@/types/media'
import type { CreatorGeneration, CreatorProject, ProjectAction } from '@/types/project'
import {
  generationOutput,
  isCampaignGenerationResult,
} from '@/types/project'
import { formatDate, formatLabel } from '@/utils/formatters'
import { generationJobTypeLabel } from '@/utils/generations'

const route = useRoute()
const project = ref<CreatorProject | null>(null)
const generation = ref<CreatorGeneration | null>(null)
const projectAssets = ref<MediaAsset[]>([])
const actions = ref<ProjectAction[]>([])
const isLoading = ref(true)
const loadError = ref('')
const loadErrorStatus = ref<number | null>(null)
const actionRefreshNonce = ref(0)
const POLL_INTERVAL_MS = 2_000

let pollTimer: ReturnType<typeof setTimeout> | null = null

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const generationId = computed(() => {
  const value = Array.isArray(route.params.generationId) ? route.params.generationId[0] : route.params.generationId

  return value || ''
})
const result = computed(() => generation.value === null ? null : generationOutput(generation.value))
const campaignResult = computed(() =>
  generation.value?.job_type === 'generate_affiliate_campaign' && isCampaignGenerationResult(result.value)
    ? result.value
    : null,
)
const voiceoverAction = computed(() =>
  actions.value.find((action) => action.key === 'generate_voiceover_from_campaign') ??
  actions.value.find((action) => action.key === 'generate_voiceover_from_script') ??
  null,
)
const campaignGenerationCount = computed(() =>
  generation.value?.job_type === 'generate_affiliate_campaign' ? 1 : 0,
)
const latestVoiceoverGeneration = computed(() =>
  generation.value?.job_type === 'generate_ai_voiceover' && generation.value.status === 'completed'
    ? generation.value
    : null,
)
const actionRefreshKey = computed(() =>
  `${project.value?.id ?? 'none'}:${generation.value?.id ?? 'none'}:${generation.value?.status ?? 'none'}:${actionRefreshNonce.value}`,
)
const resultTitle = computed(() => {
  if (campaignResult.value?.campaign_title) {
    return campaignResult.value.campaign_title
  }

  if (generation.value?.job_type === 'generate_affiliate_campaign') {
    return t('projectActions.campaignResult')
  }

  return generation.value ? generationJobTypeLabel(generation.value.job_type) : t('metadata.titles.generationResult')
})

function isTerminalGeneration(status: CreatorGeneration['status']): boolean {
  return status === 'completed' || status === 'failed'
}

function stopPolling(): void {
  if (pollTimer !== null) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

function schedulePoll(): void {
  if (pollTimer !== null || generation.value === null || isTerminalGeneration(generation.value.status)) {
    return
  }

  pollTimer = setTimeout(() => {
    pollTimer = null
    void pollGenerationDetail()
  }, POLL_INTERVAL_MS)
}

async function pollGenerationDetail(): Promise<void> {
  const current = generation.value

  if (current === null || isTerminalGeneration(current.status)) {
    stopPolling()
    return
  }

  try {
    const updated = await getGeneration(current.id)

    generation.value = updated

    if (isTerminalGeneration(updated.status)) {
      stopPolling()
      return
    }

    schedulePoll()
  } catch (error: unknown) {
    stopPolling()
    loadError.value = error instanceof ApiError ? error.message : 'We could not refresh this generation result.'
  }
}

function startPollingIfNeeded(target: CreatorGeneration): void {
  stopPolling()

  if (!isTerminalGeneration(target.status)) {
    schedulePoll()
  }
}

async function loadGenerationDetail(): Promise<void> {
  stopPolling()
  isLoading.value = true
  loadError.value = ''
  loadErrorStatus.value = null

  try {
    const [loadedProject, loadedGeneration, loadedAssets, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      getGeneration(generationId.value),
      listProjectMediaAssets(projectId.value),
      getProjectActions(projectId.value),
    ])

    if (loadedGeneration.project_id !== loadedProject.id) {
      loadErrorStatus.value = 404
      loadError.value = t('projects.unavailableBody')
      return
    }

    project.value = loadedProject
    generation.value = loadedGeneration
    startPollingIfNeeded(loadedGeneration)
    projectAssets.value = loadedAssets.data
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
  } catch (error: unknown) {
    if (error instanceof ApiError && [403, 404].includes(error.status)) {
      loadErrorStatus.value = error.status
      loadError.value = t('projects.unavailableBody')
    } else {
      loadError.value = error instanceof ApiError ? error.message : 'We could not load this generation result.'
    }
  } finally {
    isLoading.value = false
  }
}

function handleGenerated(): void {
  actionRefreshNonce.value += 1
}

function handleVoiceoverGenerated(): void {
  actionRefreshNonce.value += 1
}

onMounted(loadGenerationDetail)
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
        <p class="mt-5 text-sm text-neutral-500">Loading generation result...</p>
      </section>

      <section
        v-else-if="loadErrorStatus === 403 || loadErrorStatus === 404"
        class="nusa-card-premium mt-12 rounded-[2rem] p-6"
      >
        <p class="text-sm font-medium text-amber-400">{{ t('metadata.titles.generationResult') }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ t('projects.unavailable') }}</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">
          {{ loadError }}
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
        <p class="text-sm font-medium text-rose-400">{{ t('metadata.titles.generationResult') }}</p>
        <h1 class="mt-3 text-3xl font-bold">This result needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button
          type="button"
          class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950"
          @click="loadGenerationDetail"
        >
          Try again
        </button>
      </section>

      <template v-else-if="project && generation">
        <section class="mt-10">
          <RouterLink
            :to="{ name: 'project-detail', params: { id: project.id } }"
            class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          >
            ← {{ t('projectActions.backToWorkspace') }}
          </RouterLink>

          <div class="mt-6 flex flex-wrap items-center gap-2">
            <span
              class="rounded-full border border-emerald-800 bg-emerald-950 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-300"
            >
              {{ formatLabel(generation.status) }}
            </span>
            <span class="text-xs font-medium uppercase tracking-[0.16em] text-neutral-500">
              {{ generation.provider ?? 'Unknown provider' }} · {{ formatDate(generation.created_at) }}
            </span>
          </div>
          <p class="mt-5 text-sm font-medium text-emerald-400">{{ project.title }}</p>
          <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">{{ resultTitle }}</h1>
          <p class="mt-3 text-sm text-neutral-500">
            {{ t('metadata.titles.generationResult') }} #{{ generation.id }} · {{ generationJobTypeLabel(generation.job_type) }}
          </p>
        </section>

        <CampaignGenerationResult
          v-if="campaignResult"
          class="mt-8"
          :result="campaignResult"
          :project-id="project.id"
          :generation-id="generation.id"
          :voiceover-action="voiceoverAction"
          @voiceover-generated="handleVoiceoverGenerated"
        />

        <GenerationResultRenderer
          v-else
          :generation="generation"
          :media-assets="projectAssets"
          :project-id="project.id"
          :voiceover-action="voiceoverAction"
        />

        <ProjectActionHub
          class="mt-8"
          :project-id="project.id"
          :selected-generation="generation"
          :latest-campaign-generation="generation.job_type === 'generate_affiliate_campaign' ? generation : null"
          :latest-voiceover-generation="latestVoiceoverGeneration"
          :campaign-generation-count="campaignGenerationCount"
          :refresh-key="actionRefreshKey"
          @generated="handleGenerated"
        />
      </template>
    </div>
  </main>
</template>