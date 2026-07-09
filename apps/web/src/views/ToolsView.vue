<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getCreatorTools } from '@/services/tools'
import { useAuthStore } from '@/stores/auth'
import type {
  CreatorTool,
  ToolImplementationStage,
  ToolOutputType,
  ToolServiceTier,
} from '@/types/tool'
import { formatLabel } from '@/utils/formatters'

const serviceTierOrder: ToolServiceTier[] = ['basic', 'advanced', 'premium']

const tools = ref<CreatorTool[]>([])
const isLoading = ref(true)
const error = ref('')
const auth = useAuthStore()

const visibleTools = computed(() => tools.value.filter((tool) => tool.status !== 'disabled'))
const groupedToolSections = computed(() =>
  serviceTierOrder.map((tier) => ({
    tier,
    title: t('tools.serviceTiers.' + tier + '.title'),
    description: t('tools.serviceTiers.' + tier + '.description'),
    tools: visibleTools.value
      .filter((tool) => tool.service_tier === tier)
      .sort((left, right) => left.sort_order - right.sort_order || left.title.localeCompare(right.title)),
  })),
)

function toolDestination(tool: CreatorTool): RouteLocationRaw {
  const dashboardRoute = { path: '/dashboard', query: { tool: tool.key } }

  return auth.isAuthenticated
    ? dashboardRoute
    : { path: '/login', query: { redirect: '/dashboard?tool=' + tool.key } }
}

function outputTypeLabel(outputType: ToolOutputType | null): string | null {
  return outputType ? t('tools.outputTypes.' + outputType) : null
}

function implementationStageLabel(stage: ToolImplementationStage): string {
  return t('tools.implementationStages.' + stage)
}

function stageClasses(stage: ToolImplementationStage): string {
  if (stage === 'real_provider') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (stage === 'beta') {
    return 'border-cyan-800 bg-cyan-950 text-cyan-300'
  }

  if (stage === 'future') {
    return 'border-violet-900 bg-violet-950 text-violet-300'
  }

  return 'border-neutral-700 bg-neutral-900 text-neutral-300'
}

function statusClasses(status: CreatorTool['status']): string {
  if (status === 'available') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'maintenance') {
    return 'border-amber-800 bg-amber-950 text-amber-300'
  }

  return 'border-neutral-700 bg-neutral-800 text-neutral-300'
}

async function loadTools(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    tools.value = await getCreatorTools()
  } catch (requestError: unknown) {
    error.value =
      requestError instanceof ApiError
        ? requestError.message
        : t('errors.toolsLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadTools()
})
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="mx-auto max-w-3xl pt-12 text-center lg:pt-16">
        <p class="text-sm font-medium text-emerald-400">{{ t('tools.kicker') }}</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight">{{ t('tools.headline') }}</h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          {{ t('tools.body') }}
        </p>
      </header>

      <div v-if="isLoading" class="mt-10 space-y-4" :aria-label="t('tools.loading')">
        <div
          v-for="index in 3"
          :key="index"
          class="h-52 animate-pulse rounded-[2rem] border border-slate-800/70 bg-slate-900/60"
        />
      </div>

      <div
        v-else-if="error"
        role="alert"
        class="mt-10 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5"
      >
        <h2 class="text-lg font-semibold text-rose-100">{{ t('tools.unavailable') }}</h2>
        <p class="mt-2 text-sm leading-6 text-rose-200">{{ error }}</p>
        <button
          type="button"
          class="mt-5 min-h-11 rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          @click="loadTools"
        >
          {{ t('common.tryAgain') }}
        </button>
      </div>

      <template v-else>
        <section
          v-for="section in groupedToolSections"
          :key="section.tier"
          :data-testid="'tool-tier-section-' + section.tier"
          class="mt-10"
        >
          <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('tools.serviceTier') }}
            </p>
            <h2 class="mt-1 text-2xl font-semibold">{{ section.title }}</h2>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
              {{ section.description }}
            </p>
          </div>

          <div v-if="section.tools.length" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template v-for="tool in section.tools" :key="tool.key">
              <RouterLink
                v-if="tool.status === 'available' && tool.route"
                :to="toolDestination(tool)"
                :data-testid="'tool-card-' + tool.key"
                class="nusa-card-interactive nusa-focus-ring group block rounded-[2rem] p-5 transition"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-400">
                      {{ formatLabel(tool.category) }}
                    </p>
                    <h3 class="mt-2 text-xl font-semibold">{{ tool.title }}</h3>
                  </div>
                  <span
                    class="nusa-badge shrink-0 rounded-full px-3 py-1 text-xs font-bold"
                  >
                    {{ tool.badge_label ?? t('common.available') }}
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ section.title }}
                  </span>
                  <span
                    v-if="outputTypeLabel(tool.output_type)"
                    class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300"
                  >
                    {{ outputTypeLabel(tool.output_type) }}
                  </span>
                  <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="stageClasses(tool.implementation_stage)"
                  >
                    {{ implementationStageLabel(tool.implementation_stage) }}
                  </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-neutral-300">
                  {{ tool.short_description }}
                </p>
                <p v-if="tool.long_description" class="mt-3 text-sm leading-6 text-neutral-500">
                  {{ tool.long_description }}
                </p>

                <div
                  class="mt-5 flex items-center justify-between border-t border-neutral-800 pt-4"
                >
                  <span class="text-xs font-medium text-neutral-400">
                    {{ tool.credit_cost_label ?? tool.credit_cost + ' credits' }}
                  </span>
                  <span class="text-sm font-semibold text-emerald-300">
                    {{ auth.isAuthenticated ? t('tools.startWithWorkflow') : t('tools.loginToStart') }}
                    <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </RouterLink>

              <article
                v-else
                :data-testid="'tool-card-' + tool.key"
                aria-disabled="true"
                class="nusa-card-soft rounded-[2rem] p-5 opacity-80"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                      {{ formatLabel(tool.category) }}
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-neutral-200">{{ tool.title }}</h3>
                  </div>
                  <span
                    class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold"
                    :class="statusClasses(tool.status)"
                  >
                    {{ tool.badge_label ?? formatLabel(tool.status) }}
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ section.title }}
                  </span>
                  <span
                    v-if="outputTypeLabel(tool.output_type)"
                    class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300"
                  >
                    {{ outputTypeLabel(tool.output_type) }}
                  </span>
                  <span
                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                    :class="stageClasses(tool.implementation_stage)"
                  >
                    {{ implementationStageLabel(tool.implementation_stage) }}
                  </span>
                </div>

                <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
                <p v-if="tool.status === 'maintenance'" class="mt-4 text-sm leading-6 text-amber-200">
                  {{ t('tools.maintenanceBody') }}
                </p>
                <p v-else-if="tool.status === 'available'" class="mt-4 text-xs text-amber-300">
                  {{ t('tools.launchRouteMissing') }}
                </p>
                <p v-else class="mt-4 text-xs font-medium text-neutral-600">
                  {{ tool.credit_cost_label ?? t('tools.creditCostComingSoon') }}
                </p>
              </article>
            </template>
          </div>
          <p
            v-else
            class="mt-5 rounded-3xl border border-dashed border-neutral-800 p-6 text-sm text-neutral-500"
          >
            {{ t('tools.noToolsInTier') }}
          </p>
        </section>
      </template>
    </div>
  </main>
</template>
