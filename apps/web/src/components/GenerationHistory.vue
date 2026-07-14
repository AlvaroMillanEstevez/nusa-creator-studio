<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { useTranslations } from '@/i18n'
import type { CreatorGeneration, GenerationStatus } from '@/types/project'
import { formatDate } from '@/utils/formatters'
import { generationFriendlyLabel } from '@/utils/generations'

const props = withDefaults(defineProps<{
  generations: CreatorGeneration[]
  selectedGeneration: CreatorGeneration | null
  isLoading: boolean
  error: string
  emptyCopy: string
  projectId?: number | string | null
  openResults?: boolean
}>(), {
  projectId: null,
  openResults: false,
})

defineEmits<{
  select: [generation: CreatorGeneration]
  retry: []
}>()

const { t } = useTranslations()

function statusClasses(status: GenerationStatus): string {
  if (status === 'completed') {
    return 'bg-emerald-950 text-emerald-300'
  }

  if (status === 'failed') {
    return 'bg-rose-950 text-rose-300'
  }

  if (status === 'processing') {
    return 'bg-sky-950 text-sky-300'
  }

  return 'bg-amber-950 text-amber-300'
}

function generationTitle(item: CreatorGeneration, index: number): string {
  return index === 0 ? 'Latest generation' : `Generation #${item.id}`
}

function resultRoute(item: CreatorGeneration): string {
  return `/projects/${String(props.projectId ?? item.project_id ?? '')}/generations/${String(item.id)}`
}
</script>

<template>
  <section class="nusa-card-soft mt-5 rounded-[2rem] p-5" data-testid="generation-history">
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          Saved results
        </p>
        <h2 class="mt-1 text-lg font-semibold">Generation history</h2>
      </div>
      <span
        v-if="generations.length"
        class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-neutral-400"
      >
        {{ generations.length }}
      </span>
    </div>

    <div v-if="isLoading" class="mt-5 space-y-3" aria-live="polite">
      <div class="h-16 animate-pulse rounded-2xl bg-neutral-800/70" />
      <div class="h-16 animate-pulse rounded-2xl bg-neutral-800/50" />
      <p class="text-xs text-neutral-500">Loading generation history...</p>
    </div>

    <div
      v-else-if="error"
      role="alert"
      class="mt-5 rounded-2xl border border-amber-900/60 bg-amber-950/30 p-4"
    >
      <p class="text-sm leading-6 text-amber-200">{{ error }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-amber-300 underline underline-offset-4"
        @click="$emit('retry')"
      >
        Retry history
      </button>
    </div>

    <p
      v-else-if="generations.length === 0"
      class="mt-5 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-6 text-neutral-400"
    >
      {{ emptyCopy }}
    </p>

    <div v-else class="mt-5 space-y-3">
      <template v-if="openResults">
        <article
          v-for="(item, index) in generations"
          :key="item.id"
          class="w-full rounded-2xl border p-4 text-left transition"
          :class="
            selectedGeneration?.id === item.id
              ? 'border-emerald-500 bg-emerald-950/30'
              : 'border-neutral-800 bg-neutral-950/70 hover:border-neutral-700'
          "
          data-testid="generation-history-item"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="break-words text-sm font-semibold text-neutral-100">
                {{ generationTitle(item, index) }}
              </p>
              <p class="mt-1 text-xs text-neutral-500">{{ formatDate(item.created_at) }}</p>
            </div>
            <span
              class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
              :class="statusClasses(item.status)"
            >
              {{ item.status }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
            <span>{{ generationFriendlyLabel(item) }}</span>
            <span aria-hidden="true">&bull;</span>
            <span>{{ item.provider ?? 'Unknown provider' }}</span>
            <span aria-hidden="true">&bull;</span>
            <span>
              {{ item.credits_charged }}
              {{ item.credits_charged === 1 ? 'credit' : 'credits' }} charged
            </span>
          </div>
          <RouterLink
            :to="resultRoute(item)"
            class="mt-4 inline-flex min-h-10 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-xs font-semibold text-neutral-100 transition hover:border-emerald-500 hover:bg-emerald-950/30"
            data-testid="generation-result-link"
          >
            {{ t('projectActions.viewResult') }}
          </RouterLink>
        </article>
      </template>

      <template v-else>
        <button
          v-for="(item, index) in generations"
          :key="item.id"
          type="button"
          class="w-full rounded-2xl border p-4 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          :class="
            selectedGeneration?.id === item.id
              ? 'border-emerald-500 bg-emerald-950/30'
              : 'border-neutral-800 bg-neutral-950/70 hover:border-neutral-700'
          "
          data-testid="generation-history-item"
          @click="$emit('select', item)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="break-words text-sm font-semibold text-neutral-100">
                {{ generationTitle(item, index) }}
              </p>
              <p class="mt-1 text-xs text-neutral-500">{{ formatDate(item.created_at) }}</p>
            </div>
            <span
              class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
              :class="statusClasses(item.status)"
            >
              {{ item.status }}
            </span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
            <span>{{ generationFriendlyLabel(item) }}</span>
            <span aria-hidden="true">&bull;</span>
            <span>{{ item.provider ?? 'Unknown provider' }}</span>
            <span aria-hidden="true">&bull;</span>
            <span>
              {{ item.credits_charged }}
              {{ item.credits_charged === 1 ? 'credit' : 'credits' }} charged
            </span>
          </div>
        </button>
      </template>
    </div>
  </section>
</template>