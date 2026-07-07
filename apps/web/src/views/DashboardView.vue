<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getDashboardSummary } from '@/services/dashboard'
import type { DashboardSummary } from '@/types/dashboard'
import { formatDate, formatLabel } from '@/utils/formatters'

const dashboard = ref<DashboardSummary | null>(null)
const isLoading = ref(true)
const loadError = ref('')

const quickActions = [
  { labelKey: 'dashboard.browseTools', to: '/tools', primary: true },
  { labelKey: 'dashboard.startCampaign', to: '/campaigns/new', primary: false },
  { labelKey: 'dashboard.startVideoEnhancer', to: '/video-enhancer/new', primary: false },
  { labelKey: 'dashboard.startProductVideo', to: '/product-to-video/new', primary: false },
  { labelKey: 'dashboard.startFashion', to: '/fashion-try-on/new', primary: false },
  { labelKey: 'dashboard.startMotion', to: '/motion-video/new', primary: false },
] as const

const statCards = computed(() => {
  const stats = dashboard.value?.stats

  if (!stats) {
    return []
  }

  return [
    { label: t('dashboard.stats.totalProjects'), value: stats.total_projects },
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

  if (status === 'processing' || status === 'pending') {
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

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">{{ t('dashboard.kicker') }}</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          {{ dashboard ? t('dashboard.welcomeBack', { name: dashboard.user.name }) : t('dashboard.fallbackTitle') }}
        </h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
          {{ t('dashboard.body') }}
        </p>
      </header>

      <section v-if="isLoading" class="mt-9 space-y-5" aria-live="polite">
        <div class="h-40 animate-pulse rounded-3xl bg-neutral-900" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="index in 6"
            :key="index"
            class="h-24 animate-pulse rounded-2xl bg-neutral-900"
          />
        </div>
        <p class="text-sm text-neutral-500">{{ t('dashboard.loading') }}</p>
      </section>

      <section
        v-else-if="loadError"
        class="mt-9 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5"
      >
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
        <section
          class="mt-9 overflow-hidden rounded-3xl border border-emerald-900/60 bg-gradient-to-br from-emerald-950/60 to-neutral-900 p-5 sm:p-6"
        >
          <div class="flex items-start justify-between gap-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                {{ t('dashboard.planAccess') }}
              </p>
              <template v-if="dashboard.access.is_unlimited">
                <h2 class="mt-3 text-3xl font-bold tracking-tight">{{ t('dashboard.unlimitedTitle') }}</h2>
                <p class="mt-3 max-w-lg text-sm leading-6 text-emerald-100/70">
                  {{ t('dashboard.unlimitedBody') }}
                </p>
              </template>
              <template v-else>
                <p class="mt-3 text-sm font-semibold text-emerald-300">
                  {{ dashboard.access.label }}
                </p>
                <p class="mt-2 text-5xl font-bold tracking-tight">
                  {{ dashboard.access.credits_balance }}
                </p>
                <p class="mt-2 text-sm text-neutral-400">{{ t('dashboard.availableCredits') }}</p>
                <p class="mt-2 text-xs text-neutral-500">
                  {{ t('dashboard.monthlyAllowance', { count: dashboard.access.monthly_credit_allowance }) }}
                </p>
              </template>
            </div>
            <div
              class="rounded-2xl border border-emerald-800/70 bg-neutral-950/50 px-3 py-2 text-right"
            >
              <p class="text-xs text-neutral-500">{{ t('dashboard.usedSoFar') }}</p>
              <p class="mt-1 font-semibold text-emerald-300">
                {{ dashboard.stats.total_credits_spent }}
              </p>
            </div>
          </div>
          <div
            class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-emerald-900/60 pt-4"
          >
            <p class="text-xs text-neutral-500">
              {{ dashboard.user.name }} · {{ dashboard.user.email }}
            </p>
            <div class="flex items-center gap-4">
              <RouterLink
                to="/pricing"
                class="text-xs font-semibold text-neutral-400 transition hover:text-white"
              >
                {{ t('dashboard.viewPlans') }}
              </RouterLink>
              <RouterLink
                to="/account#plan"
                class="text-xs font-semibold text-emerald-400 transition hover:text-emerald-300"
              >
                {{ t('dashboard.manageAccountPlan') }}
              </RouterLink>
            </div>
          </div>
        </section>

        <section class="mt-8">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('dashboard.overview') }}
            </p>
            <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.workspaceActivity') }}</h2>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <article
              v-for="stat in statCards"
              :key="stat.label"
              class="nusa-card-soft rounded-2xl p-4"
            >
              <p class="text-2xl font-bold text-neutral-100">{{ stat.value }}</p>
              <p class="mt-2 text-xs leading-5 text-neutral-500">{{ stat.label }}</p>
            </article>
          </div>
        </section>

        <section class="nusa-card-soft mt-8 rounded-[2rem] p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('dashboard.quickActions') }}
          </p>
          <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.createSomething') }}</h2>
          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <RouterLink
              v-for="action in quickActions"
              :key="action.to"
              :to="action.to"
              class="flex min-h-12 items-center justify-between rounded-2xl px-4 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
              :class="
                action.primary
                  ? 'bg-white text-neutral-950 hover:bg-emerald-300'
                  : 'border border-neutral-700 bg-neutral-950/60 text-neutral-200 hover:border-neutral-600 hover:bg-neutral-800'
              "
            >
              <span>{{ t(action.labelKey) }}</span>
              <span aria-hidden="true">&rarr;</span>
            </RouterLink>
          </div>
        </section>

        <section class="mt-8">
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                {{ t('dashboard.projects') }}
              </p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('dashboard.recentProjects') }}</h2>
            </div>
            <span v-if="dashboard.recent_projects.length" class="text-xs text-neutral-500">
              {{ t('dashboard.latestCount', { count: dashboard.recent_projects.length }) }}
            </span>
          </div>

          <div
            v-if="dashboard.recent_projects.length === 0"
            class="nusa-card-soft mt-4 rounded-[2rem] border-dashed p-6"
          >
            <h3 class="font-semibold text-neutral-200">{{ t('dashboard.noProjects') }}</h3>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('dashboard.noProjectsBody') }}
            </p>
            <RouterLink
              to="/tools"
              class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-semibold text-neutral-950"
            >
              {{ t('dashboard.browseTools') }}
            </RouterLink>
          </div>

          <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
            <RouterLink
              v-for="project in dashboard.recent_projects"
              :key="project.id"
              :to="'/projects/' + project.id"
              class="nusa-card-interactive nusa-focus-ring rounded-[2rem] p-5 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-semibold text-neutral-100">{{ project.title }}</p>
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
              <p class="mt-3 text-xs text-neutral-600">{{ formatDate(project.created_at) }}</p>
            </RouterLink>
          </div>
        </section>

        <section class="mt-8">
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

          <div
            v-if="dashboard.recent_generations.length === 0"
            class="nusa-card-soft mt-4 rounded-[2rem] border-dashed p-6"
          >
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
              v-bind="
                generation.project_id === null ? {} : { to: '/projects/' + generation.project_id }
              "
              class="nusa-card-interactive nusa-focus-ring block rounded-2xl p-4 transition"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-neutral-100">
                    {{ generation.project_title ?? t('dashboard.projectUnavailable') }}
                  </p>
                  <p class="mt-1 text-xs text-neutral-500">
                    {{ formatLabel(generation.job_type) }}
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
