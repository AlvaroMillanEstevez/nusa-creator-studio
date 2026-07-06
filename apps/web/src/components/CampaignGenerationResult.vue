<script setup lang="ts">
import type { CampaignGenerationResult } from '@/types/project'

defineProps<{
  result: CampaignGenerationResult
}>()
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <h2 class="text-2xl font-bold">Hooks</h2>
      <ol class="mt-5 space-y-3">
        <li
          v-for="(hook, index) in result.hooks"
          :key="hook"
          class="flex gap-3 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-6 text-neutral-200"
        >
          <span class="font-bold text-emerald-400">{{ index + 1 }}</span>
          <span>{{ hook }}</span>
        </li>
      </ol>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            TikTok script
          </p>
          <h2 class="mt-2 text-xl font-bold">{{ result.script.title }}</h2>
        </div>
        <span class="shrink-0 text-sm text-neutral-400">
          {{ result.script.duration_seconds }}s
        </span>
      </div>

      <div class="mt-6 space-y-4">
        <article
          v-for="(scene, index) in result.script.scenes"
          :key="scene.timestamp"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-white">Scene {{ index + 1 }}</p>
            <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
              {{ scene.timestamp }}
            </span>
          </div>
          <dl class="mt-4 space-y-4 text-sm">
            <div>
              <dt class="font-semibold text-neutral-500">Voiceover</dt>
              <dd class="mt-1 leading-6 text-neutral-200">{{ scene.voiceover }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">Visual direction</dt>
              <dd class="mt-1 leading-6 text-neutral-300">{{ scene.visual_direction }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">On-screen text</dt>
              <dd class="mt-1 font-medium text-emerald-300">{{ scene.on_screen_text }}</dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Caption</p>
      <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-200">
        {{ result.caption }}
      </p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
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

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
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
