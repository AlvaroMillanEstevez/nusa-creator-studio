<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getCreatorTools } from '@/services/tools'
import { useAuthStore } from '@/stores/auth'
import type { CreatorTool } from '@/types/tool'
import { formatLabel } from '@/utils/formatters'

const tools = ref<CreatorTool[]>([])
const isLoading = ref(true)
const error = ref('')
const auth = useAuthStore()

const visibleTools = computed(() => tools.value.filter((tool) => tool.status !== 'disabled'))
const availableTools = computed(() =>
  visibleTools.value.filter((tool) => tool.status === 'available'),
)
const maintenanceTools = computed(() =>
  visibleTools.value.filter((tool) => tool.status === 'maintenance'),
)
const comingSoonTools = computed(() =>
  visibleTools.value.filter((tool) => tool.status === 'coming_soon'),
)

function toolDestination(route: string): RouteLocationRaw {
  return auth.isAuthenticated ? route : { path: '/login', query: { redirect: route } }
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
        <section class="mt-10">
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                {{ t('tools.ready') }}
              </p>
              <h2 class="mt-1 text-xl font-semibold">{{ t('tools.availableTools') }}</h2>
            </div>
            <span class="text-xs text-neutral-500">{{ t('tools.live', { count: availableTools.length }) }}</span>
          </div>

          <div v-if="availableTools.length" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template v-for="tool in availableTools" :key="tool.key">
              <RouterLink
                v-if="tool.route"
                :to="toolDestination(tool.route)"
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
                    {{ auth.isAuthenticated ? t('tools.openTool') : t('tools.loginToUse') }}
                    <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </RouterLink>

              <article
                v-else
                :data-testid="'tool-card-' + tool.key"
                aria-disabled="true"
                class="nusa-card-soft rounded-[2rem] p-5 opacity-75"
              >
                <h3 class="text-lg font-semibold">{{ tool.title }}</h3>
                <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
                <p class="mt-4 text-xs text-amber-300">{{ t('tools.launchRouteMissing') }}</p>
              </article>
            </template>
          </div>
          <p
            v-else
            class="mt-5 rounded-3xl border border-dashed border-neutral-800 p-6 text-sm text-neutral-500"
          >
            {{ t('tools.noTools') }}
          </p>
        </section>

        <section v-if="maintenanceTools.length" class="mt-12">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">
            {{ t('tools.inMaintenance') }}
          </p>
          <h2 class="mt-1 text-xl font-semibold">{{ t('tools.temporarilyUnavailable') }}</h2>

          <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="tool in maintenanceTools"
              :key="tool.key"
              :data-testid="'tool-card-' + tool.key"
              aria-disabled="true"
              class="nusa-card-soft rounded-[2rem] p-5"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                    {{ formatLabel(tool.category) }}
                  </p>
                  <h3 class="mt-2 text-lg font-semibold text-neutral-200">{{ tool.title }}</h3>
                </div>
                <span class="shrink-0 rounded-full bg-amber-950 px-3 py-1 text-xs text-amber-300">
                  {{ tool.badge_label ?? t('common.maintenance') }}
                </span>
              </div>
              <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
              <p class="mt-4 text-sm leading-6 text-amber-200">
                {{ t('tools.maintenanceBody') }}
              </p>
            </article>
          </div>
        </section>

        <section v-if="comingSoonTools.length" class="mt-12">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('tools.expanding') }}
          </p>
          <h2 class="mt-1 text-xl font-semibold">{{ t('tools.comingSoon') }}</h2>

          <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="tool in comingSoonTools"
              :key="tool.key"
              :data-testid="'tool-card-' + tool.key"
              aria-disabled="true"
              class="nusa-card-soft rounded-[2rem] p-5 opacity-75"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                    {{ formatLabel(tool.category) }}
                  </p>
                  <h3 class="mt-2 text-lg font-semibold text-neutral-200">{{ tool.title }}</h3>
                </div>
                <span
                  class="shrink-0 rounded-full bg-neutral-800 px-3 py-1 text-xs text-neutral-400"
                >
                  {{ tool.badge_label ?? t('tools.comingSoon') }}
                </span>
              </div>
              <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
              <p class="mt-4 text-xs font-medium text-neutral-600">
                {{ tool.credit_cost_label ?? t('tools.creditCostComingSoon') }}
              </p>
            </article>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>
