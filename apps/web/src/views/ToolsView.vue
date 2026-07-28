<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import {
  availableWorkflowDefinitions,
  comingSoonWorkflowDefinitions,
  type BetaWorkflowDefinition,
} from '@/config/workflows'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getCreatorTools } from '@/services/tools'
import { useAuthStore } from '@/stores/auth'
import type { CreatorTool, ToolStatus } from '@/types/tool'

type WorkflowCardStatus = ToolStatus | 'coming_soon'

interface BetaWorkflowCard {
  key: string
  title: string
  description: string
  longDescription: string
  category: string
  status: WorkflowCardStatus
  canStart: boolean
  destination: RouteLocationRaw | null
  creditCostLabel: string
  badgeLabel: string
  recommendedInputs: string[]
  outputs: string[]
}

interface WorkflowToolData {
  status: ToolStatus
  credit_cost: number
  credit_cost_label: string | null
  badge_label: string | null
}

const tools = ref<CreatorTool[]>([])
const isLoading = ref(true)
const error = ref('')
const auth = useAuthStore()

const visibleTools = computed(() => tools.value.filter((tool) => tool.status !== 'disabled'))
const toolsByKey = computed(() =>
  new Map(visibleTools.value.map((tool) => [tool.key, tool])),
)

const availableWorkflowCards = computed<BetaWorkflowCard[]>(() =>
  availableWorkflowDefinitions.map((workflow) => workflowCard(workflow)),
)

const comingSoonWorkflowCards = computed<BetaWorkflowCard[]>(() =>
  comingSoonWorkflowDefinitions.map((workflow) => workflowCard(workflow)),
)

function workflowCard(workflow: BetaWorkflowDefinition): BetaWorkflowCard {
  const tool = workflow.sourceToolKey === null ? null : toolsByKey.value.get(workflow.sourceToolKey)
  const toolData = toolDataFor(tool)
  const isComingSoon = workflow.status === 'coming_soon'
  const status = isComingSoon ? 'coming_soon' : toolData.status
  const canStart = workflow.status === 'available' && toolData.status === 'available'
  const targetPath = '/dashboard?tool=' + encodeURIComponent(workflow.dashboardToolKey)

  return {
    key: workflow.key,
    title: t('workflows.' + workflow.key + '.title'),
    description: t('workflows.' + workflow.key + '.shortDescription'),
    longDescription: t('workflows.' + workflow.key + '.longDescription'),
    category: t('workflows.categories.' + workflow.categoryKey),
    status,
    canStart,
    destination: canStart
      ? auth.isAuthenticated
        ? targetPath
        : { path: '/login', query: { redirect: targetPath } }
      : null,
    creditCostLabel: isComingSoon
      ? t('workflows.creditPlanned')
      : toolData.credit_cost_label ?? (toolData.credit_cost > 0 ? t('workflows.fromCredits', { credits: toolData.credit_cost }) : t('tools.creditCostComingSoon')),
    badgeLabel: isComingSoon
      ? t('common.comingSoon')
      : toolData.badge_label ?? t('common.available'),
    recommendedInputs: workflow.recommendedInputKeys.map((key) => t('workflows.inputs.' + key)),
    outputs: workflow.outputKeys.map((key) => t('workflows.outputItems.' + key)),
  }
}

function toolDataFor(tool: CreatorTool | null | undefined): WorkflowToolData {
  return {
    status: tool?.status ?? 'coming_soon',
    credit_cost: tool?.credit_cost ?? 0,
    credit_cost_label: tool?.credit_cost_label ?? null,
    badge_label: tool?.badge_label ?? null,
  }
}

function statusClasses(status: WorkflowCardStatus): string {
  if (status === 'available') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'maintenance') {
    return 'border-amber-800 bg-amber-950 text-amber-300'
  }

  return 'border-neutral-700 bg-neutral-800 text-neutral-300'
}

function statusLabel(card: BetaWorkflowCard): string {
  if (card.status === 'available') {
    return t('common.available')
  }

  if (card.status === 'maintenance') {
    return t('tools.inMaintenance')
  }

  return t('common.comingSoon')
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
          v-for="index in 4"
          :key="index"
          class="h-56 animate-pulse rounded-[2rem] border border-slate-800/70 bg-slate-900/60"
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
        <section class="mt-10" data-testid="available-workflow-section">
          <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('workflows.mainWorkflows') }}
            </p>
            <h2 class="mt-2 text-2xl font-semibold">{{ t('workflows.toolsTitle') }}</h2>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
              {{ t('workflows.toolsBody') }}
            </p>
          </div>

          <div class="mt-6 grid gap-4 md:grid-cols-2">
            <template v-for="workflow in availableWorkflowCards" :key="workflow.key">
              <RouterLink
                v-if="workflow.canStart && workflow.destination"
                :to="workflow.destination"
                :data-testid="'workflow-card-' + workflow.key"
                data-workflow-card="available"
                class="nusa-card-interactive nusa-focus-ring group block rounded-[2rem] p-5 transition"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-400">
                      {{ workflow.category }}
                    </p>
                    <h3 class="mt-2 break-words text-xl font-semibold">{{ workflow.title }}</h3>
                  </div>
                  <span class="nusa-badge shrink-0 rounded-full px-3 py-1 text-xs font-bold">
                    {{ workflow.badgeLabel }}
                  </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-neutral-300">
                  {{ workflow.description }}
                </p>
                <p class="mt-3 text-sm leading-6 text-neutral-500">
                  {{ workflow.longDescription }}
                </p>

                <div class="mt-5 grid gap-3 text-xs sm:grid-cols-2">
                  <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
                    <p class="font-semibold text-neutral-300">{{ t('workflows.recommendedInputs') }}</p>
                    <p class="mt-1 leading-5 text-neutral-500">{{ workflow.recommendedInputs.join(', ') }}</p>
                  </div>
                  <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
                    <p class="font-semibold text-neutral-300">{{ t('workflows.outputsLabel') }}</p>
                    <p class="mt-1 leading-5 text-neutral-500">{{ workflow.outputs.join(', ') }}</p>
                  </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-800 pt-4">
                  <span class="text-xs font-medium text-neutral-400">
                    {{ workflow.creditCostLabel }}
                  </span>
                  <span class="text-sm font-semibold text-emerald-300">
                    {{ auth.isAuthenticated ? t('tools.startWithWorkflow') : t('tools.loginToStart') }}
                    <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </RouterLink>

              <article
                v-else
                :data-testid="'workflow-card-' + workflow.key"
                data-workflow-card="available"
                aria-disabled="true"
                class="nusa-card-soft rounded-[2rem] p-5 opacity-90"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                      {{ workflow.category }}
                    </p>
                    <h3 class="mt-2 break-words text-xl font-semibold text-neutral-200">{{ workflow.title }}</h3>
                  </div>
                  <span
                    class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold"
                    :class="statusClasses(workflow.status)"
                  >
                    {{ statusLabel(workflow) }}
                  </span>
                </div>
                <p class="mt-4 text-sm leading-6 text-neutral-400">{{ workflow.description }}</p>
                <p class="mt-5 text-xs font-medium text-neutral-500">{{ workflow.creditCostLabel }}</p>
              </article>
            </template>
          </div>
        </section>

        <section class="mt-12" data-testid="coming-soon-workflow-section">
          <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('workflows.laterTitle') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('workflows.laterBody') }}
            </p>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <article
              v-for="workflow in comingSoonWorkflowCards"
              :key="workflow.key"
              :data-testid="'workflow-card-' + workflow.key"
              data-workflow-card="coming-soon"
              aria-disabled="true"
              class="nusa-card-soft rounded-[2rem] p-5 opacity-85"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                    {{ workflow.category }}
                  </p>
                  <h3 class="mt-2 break-words text-xl font-semibold text-neutral-200">{{ workflow.title }}</h3>
                </div>
                <span class="shrink-0 rounded-full border border-neutral-700 bg-neutral-800 px-2.5 py-1 text-xs font-semibold text-neutral-300">
                  {{ t('common.comingSoon') }}
                </span>
              </div>

              <p class="mt-4 text-sm leading-6 text-neutral-400">{{ workflow.description }}</p>
              <p class="mt-3 text-sm leading-6 text-neutral-500">{{ workflow.longDescription }}</p>
              <p class="mt-5 border-t border-neutral-800 pt-4 text-xs font-medium text-neutral-500">
                {{ workflow.creditCostLabel }}
              </p>
            </article>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>
