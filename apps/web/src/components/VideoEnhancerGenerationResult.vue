<script setup lang="ts">
import type { VideoEnhancerResult } from '@/types/project'

defineProps<{
  result: VideoEnhancerResult
}>()
</script>

<template>
  <div class="space-y-5">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Improved caption
      </p>
      <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-200">
        {{ result.improved_caption }}
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Subtitle plan
      </p>
      <div class="mt-4 space-y-3">
        <article
          v-for="cue in result.subtitle_plan"
          :key="cue.timestamp"
          class="rounded-2xl bg-neutral-950/70 p-4"
        >
          <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
            {{ cue.timestamp }}
          </span>
          <p class="mt-3 text-sm leading-6 text-neutral-200">{{ cue.text }}</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Cut plan</p>
      <div class="mt-4 space-y-4">
        <article
          v-for="cut in result.cut_plan"
          :key="cut.timestamp"
          class="nusa-card-soft rounded-2xl p-4"
        >
          <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
            {{ cut.timestamp }}
          </span>
          <p class="mt-3 text-sm font-medium leading-6 text-neutral-200">
            {{ cut.edit_direction }}
          </p>
          <p class="mt-2 text-xs leading-5 text-neutral-500">{{ cut.reason }}</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        On-screen text
      </p>
      <ul class="mt-4 space-y-2">
        <li
          v-for="text in result.on_screen_text"
          :key="text"
          class="rounded-2xl bg-neutral-950/70 px-4 py-3 text-sm font-medium text-neutral-200"
        >
          {{ text }}
        </li>
      </ul>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Posting text</p>
      <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-200">
        {{ result.posting_text }}
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hashtags</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span
          v-for="hashtag in result.hashtags"
          :key="hashtag"
          class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs font-medium text-neutral-300"
        >
          {{ hashtag }}
        </span>
      </div>
    </section>

    <section class="rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">CTA</p>
      <p class="mt-3 text-base font-semibold leading-7 text-white">{{ result.cta }}</p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Practical notes
      </p>
      <ul class="mt-4 space-y-3">
        <li
          v-for="note in result.notes"
          :key="note"
          class="flex gap-3 text-sm leading-6 text-neutral-300"
        >
          <span class="text-emerald-400" aria-hidden="true">&#10003;</span>
          <span>{{ note }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>
