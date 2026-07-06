<script setup lang="ts">
import type { CreatorGeneration } from '@/types/project'
import { formatDate } from '@/utils/formatters'

defineProps<{
  generations: CreatorGeneration[]
  selectedGeneration: CreatorGeneration | null
  isLoading: boolean
  error: string
  emptyCopy: string
}>()

defineEmits<{
  select: [generation: CreatorGeneration]
  retry: []
}>()
</script>

<template>
  <section class="mt-5 rounded-3xl border border-neutral-800 bg-neutral-900/60 p-5">
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
        @click="$emit('select', item)"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-neutral-100">
              {{ index === 0 ? 'Latest generation' : 'Generation #' + item.id }}
            </p>
            <p class="mt-1 text-xs text-neutral-500">{{ formatDate(item.created_at) }}</p>
          </div>
          <span
            class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
            :class="
              item.status === 'completed'
                ? 'bg-emerald-950 text-emerald-300'
                : 'bg-neutral-800 text-neutral-300'
            "
          >
            {{ item.status }}
          </span>
        </div>
        <div class="mt-3 flex items-center gap-3 text-xs text-neutral-400">
          <span>{{ item.provider ?? 'Unknown provider' }}</span>
          <span aria-hidden="true">&bull;</span>
          <span>{{ item.credits_charged }} credit charged</span>
        </div>
      </button>
    </div>
  </section>
</template>
