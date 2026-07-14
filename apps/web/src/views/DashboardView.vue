<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { betaWorkflows } from '@/config/workflows'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getDashboardSummary } from '@/services/dashboard'
import type { DashboardSummary } from '@/types/dashboard'
import { formatDate, formatLabel } from '@/utils/formatters'
import { generationJobTypeLabel } from '@/utils/generations'

const route = useRoute()
const dashboard = ref<DashboardSummary | null>(null)
const isLoading = ref(true)
const loadError = ref('')

const toolIntentLabels: Record<string, string> = {
  product_content_kit: 'Product Content Kit',
  auto_video_ads: 'Auto Video Ads',
  affiliate_campaign: 'Product Content Kit',
  video_enhancer: 'Video Enhancer / Caption Pack',
  video_to_campaign: 'Product Content Kit · Video analysis',
  product_to_video: 'Product-to-Video Script Pack',
  ai_voiceover: 'AI Voiceover',
  fashion_try_on: 'Fashion Try-On',
  motion_ai_video: 'Motion AI Video',
  auto_video_editor: 'Auto Video Editor',
}

const toolIntent = computed(() => {
  const value = Array.isArray(route.query.tool) ? route.query.tool[0] : route.query.tool

  if (typeof value !== 'string' || !(value in toolIntentLabels)) {
    return null
  }

  return {
    key: value,
    label: toolIntentLabels[value],
  }
})

const dashboardWorkflowCards = computed(() =>
  betaWorkflows.map((workflow) => ({
    key: workflow.key,
    title: t('workflows.' + workflow.key + '.title'),
    description: t('workflows.' + workflow.key + '.shortDescription'),
    status: workflow.status === 'available' ? t('common.available') : t('workflows.plannedClosedBeta'),
  })),
)

const statCards = computed(() => {
  const stats = dashboard.value?.stats

  if (!stats) {
    return []
  }

  return [
    { label: t('dashboard.stats.totalProjects'), value: stats.total_projects },
    { label: t('dashboard.stats.workspaceProjects'), value: stats.workspace_projects },
    { label: t('dashboard.stats.totalGenerations'), value: stats.total_generations },
    { label: t('dashboard.stats.completedGenerations'), value: stats.completed_generations },
    { label: t('dashboard.stats.failedGenerations'), value: stats.failed_generations },
    { label: t('dashboard.stats.draftProjects'), value: stats.draft_projects },
    { label: t('dashboard.stats.creditsSpent'), value: stats.total_credits_spent },
    { label: t('dashboard.stats.affiliateCampaigns'), value: stats.affiliate_campaign_projects },
    { label: t('dashboard.stats.videoEnhancers'), value: stats.video_enhancer_projects },
    { label: t('dashboard.stats.productToVideo'), value: stats.product_to_video_projects },
    { label: t('dashboard.stats.fashionTryOn'), value: stats.fashion_try_on_projects },
    { label: t('dashboard.stats.motionAiVideo'), value: stats.motion_ai_video_projects },
  ]
})

function statusClass(status: string): string {
  if (status === 'completed') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'failed') {
    return 'border-rose-900 bg-rose-950 text-rose-300'
  }

  if (status === 'processing' || status === 'pending' || status === 'queued') {
    return 'border-amber-900 bg-amber-950 text-amber-300'
  }

  return 'border-neutral-700 bg-neutral-800 text-neutral-300'
}

async function loadDashboard(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    dashboard.value = await getDashboardSummary()
  } catch (error: unknown) {
    loadError.value =
      error instanceof ApiError
        ? error.message
        : t('errors.dashboardLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-7xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header v-if="!toolIntent" class="pt-12">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="text-sm font-medium text-emerald-400">{{ t('dashboard.kicker') }}</p>
            <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
              {{ t('dashboard.projectWorkspaces') }}
            </h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
              {{ t('dashboard.body') }}
            </p>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              to="/projects/new"
              class="nusa-button-primary nusa-focus-ring inline-flex min-h-12 items-center justify-center rounded-2xl px-5 text-sm font-black text-neutral-950 transition hover:brightness-105"
            >
              {{ t('dashboard.createNewProject') }}
            </RouterLink>
            <RouterLink
              :to="{ path: '/dashboard', hash: '#analytics' }"
              class="nusa-focus-ring inline-flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
            >
              {{ t('dashboard.analytics') }}
            </RouterLink>
          </div>
        </div>
      </header>

      <section v-if="isLoading" class="mt-9 space-y-5" aria-live="polite">
        <div class="h-40 animate-pulse rounded-3xl bg-neutral-900" />
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div v-for="index in 4" :key="index" class="h-32 animate-pulse rounded-2xl bg-neutral-900" />
        </div>
        <p class="text-sm text-neutral-500">{{ t('dashboard.loading') }}</p>
      </section>

      <section v-else-if="loadError" class="mt-9 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5">
        <p class="text-sm font-medium text-rose-300">{{ t('dashboard.unavailable') }}</p>
        <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.retryTitle') }}</h2>
        <p role="alert" class="mt-3 text-sm leading-6 text-rose-100/70">{{ loadError }}</p>
        <button
          type="button"
          class="mt-5 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300"
          @click="loadDashboard"
        >
          {{ t('common.tryAgain') }}
        </button>
      </section>

      <template v-else-if="dashboard">
        <section v-if="toolIntent" class="nusa-card-premium mt-10 rounded-[2rem] p-5 sm:p-6">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                {{ t('dashboard.selectedWorkflow') }}
              </p>
              <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
                {{ t('dashboard.startWithWorkflow', { workflow: toolIntent.label }) }}
              </h1>
              <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
                {{ t('dashboard.chooseProjectWorkspaceToContinue', { workflow: toolIntent.label }) }}
              </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
              <RouterLink
                to="/projects/new"
                class="nusa-button-primary nusa-focus-ring inline-flex min-h-12 items-center justify-center rounded-2xl px-5 text-sm font-black text-neutral-950 transition hover:brightness-105"
              >
                {{ t('dashboard.createNewProject') }}
              </RouterLink>
              <RouterLink
                :to="'/dashboard?tool=' + toolIntent.key + '#analytics'"
                class="nusa-focus-ring inline-flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
              >
                {{ t('dashboard.viewWorkspaceActivity') }}
              </RouterLink>
            </div>
          </div>
        </section>

        <section class="mt-9" data-testid="dashboard-beta-workflows">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                {{ t('workflows.mainWorkflows') }}
              </p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('workflows.toolsTitle') }}</h2>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-500">
                {{ t('dashboard.betaWorkflowBody') }}
              </p>
            </div>
            <RouterLink
              to="/tools"
              class="nusa-focus-ring inline-flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
            >
              {{ t('dashboard.browseTools') }}
            </RouterLink>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article
              v-for="workflow in dashboardWorkflowCards"
              :key="workflow.key"
              class="nusa-card-soft rounded-2xl p-4"
              :data-testid="'dashboard-workflow-' + workflow.key"
            >
              <div class="flex items-start justify-between gap-3">
                <h3 class="text-sm font-semibold text-neutral-100">{{ workflow.title }}</h3>
                <span class="shrink-0 rounded-full border border-neutral-700 bg-neutral-900 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">
                  {{ workflow.status }}
                </span>
              </div>
              <p class="mt-3 text-xs leading-5 text-neutral-500">{{ workflow.description }}</p>
            </article>
          </div>
        </section>

        <section class="mt-9">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                {{ t('dashboard.projects') }}
              </p>
              <h2 class="mt-2 text-2xl font-semibold">{{ toolIntent ? t('dashboard.chooseExistingProject') : t('dashboard.recentProjects') }}</h2>
              <p class="mt-2 text-sm leading-6 text-neutral-500">
                {{ toolIntent ? t('dashboard.chooseProjectWorkspaceToContinue', { workflow: toolIntent.label }) : t('dashboard.openWorkspaceToContinue') }}
              </p>
            </div>
            <span v-if="dashboard.recent_projects.length" class="text-xs text-neutral-500">
              {{ t('dashboard.latestCount', { count: dashboard.recent_projects.length }) }}
            </span>
          </div>

          <div v-if="dashboard.recent_projects.length === 0" class="nusa-card-soft mt-4 rounded-[2rem] border-dashed p-6">
            <h3 class="font-semibold text-neutral-200">{{ t('dashboard.noProjects') }}</h3>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('dashboard.noProjectsBody') }}
            </p>
            <RouterLink
              to="/projects/new"
              class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
            >
              {{ t('dashboard.createFirstProject') }}
            </RouterLink>
          </div>

          <div v-else class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <RouterLink
              v-for="project in dashboard.recent_projects"
              :key="project.id"
              :to="'/projects/' + project.id"
              class="nusa-card-interactive nusa-focus-ring flex min-h-52 flex-col rounded-[2rem] p-5 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-lg font-semibold text-neutral-100">{{ project.title }}</p>
                  <p class="mt-1 text-xs text-neutral-500">{{ formatLabel(project.type) }}</p>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                  :class="statusClass(project.status)"
                >
                  {{ project.status }}
                </span>
              </div>
              <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                <span>{{ t('dashboard.generationCount', { count: project.generations_count }) }}</span>
                <template v-if="project.latest_generation_status">
                  <span aria-hidden="true">&bull;</span>
                  <span>{{ t('dashboard.latestStatus', { status: formatLabel(project.latest_generation_status) }) }}</span>
                </template>
              </div>
              <p class="mt-3 text-xs text-neutral-600">
                {{ t('dashboard.updatedOn', { date: formatDate(project.updated_at ?? project.created_at) }) }}
              </p>
              <span class="mt-auto pt-5 text-sm font-semibold text-emerald-300">
                {{ toolIntent ? t('dashboard.openWorkspaceAndRunFromContinue') : t('dashboard.openWorkspace') }} <span aria-hidden="true">&rarr;</span>
              </span>
            </RouterLink>
          </div>
        </section>

        <section id="analytics" class="mt-10 scroll-mt-24">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('dashboard.analytics') }}
            </p>
            <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.workspaceActivity') }}</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-500">
              {{ t('dashboard.analyticsComingSoon') }}
            </p>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <article v-for="stat in statCards" :key="stat.label" class="nusa-card-soft rounded-2xl p-4">
              <p class="text-2xl font-bold text-neutral-100">{{ stat.value }}</p>
              <p class="mt-2 text-xs leading-5 text-neutral-500">{{ stat.label }}</p>
            </article>
          </div>
        </section>

        <section class="mt-10">
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                {{ t('dashboard.generations') }}
              </p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.recentGenerations') }}</h2>
            </div>
            <span v-if="dashboard.recent_generations.length" class="text-xs text-neutral-500">
              {{ t('dashboard.latestCount', { count: dashboard.recent_generations.length }) }}
            </span>
          </div>

          <div v-if="dashboard.recent_generations.length === 0" class="nusa-card-soft mt-4 rounded-[2rem] border-dashed p-6">
            <h3 class="font-semibold text-neutral-200">{{ t('dashboard.noGenerations') }}</h3>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('dashboard.noGenerationsBody') }}
            </p>
          </div>

          <div v-else class="mt-4 space-y-3">
            <component
              :is="generation.project_id === null ? 'article' : RouterLink"
              v-for="generation in dashboard.recent_generations"
              :key="generation.id"
              v-bind="generation.project_id === null ? {} : { to: '/projects/' + generation.project_id }"
              class="nusa-card-interactive nusa-focus-ring block rounded-2xl p-4 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-neutral-100">
                    {{ generation.project_title ?? t('dashboard.projectUnavailable') }}
                  </p>
                  <p class="mt-1 text-xs text-neutral-500">
                    {{ generationJobTypeLabel(generation.job_type) }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                  :class="statusClass(generation.status)"
                >
                  {{ generation.status }}
                </span>
              </div>
              <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                <span>{{ generation.provider ?? t('dashboard.unknownProvider') }}</span>
                <span aria-hidden="true">&bull;</span>
                <span>{{ t('dashboard.creditCharged', { count: generation.credits_charged }) }}</span>
                <span aria-hidden="true">&bull;</span>
                <span>{{ formatDate(generation.created_at) }}</span>
              </div>
            </component>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>