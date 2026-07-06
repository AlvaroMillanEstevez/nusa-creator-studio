<script setup lang="ts">
import type { ProductToVideoResult } from '@/types/project'

defineProps<{ result: ProductToVideoResult }>()
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Concept title
      </p>
      <h2 class="mt-3 text-xl font-bold">{{ result.concept_title }}</h2>
      <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
        Video angle
      </p>
      <p class="mt-2 text-sm leading-7 text-neutral-200">{{ result.video_angle }}</p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Storyboard</p>
      <div class="mt-4 space-y-4">
        <article
          v-for="scene in result.storyboard"
          :key="scene.scene_number"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="font-semibold">Scene {{ scene.scene_number }}</p>
            <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
              {{ scene.timestamp }}
            </span>
          </div>
          <p class="mt-3 text-sm font-medium text-emerald-200">{{ scene.scene_goal }}</p>
          <dl class="mt-4 space-y-3 text-sm">
            <div>
              <dt class="text-neutral-500">Visual direction</dt>
              <dd class="mt-1 leading-6 text-neutral-200">{{ scene.visual_direction }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Voiceover</dt>
              <dd class="mt-1 leading-6 text-neutral-200">{{ scene.voiceover }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">On-screen text</dt>
              <dd class="mt-1 font-medium text-emerald-300">{{ scene.on_screen_text }}</dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Shot list</p>
        <ul class="mt-4 space-y-2 text-sm text-neutral-300">
          <li v-for="shot in result.shot_list" :key="shot">&bull; {{ shot }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Asset checklist
        </p>
        <ul class="mt-4 space-y-2 text-sm text-neutral-300">
          <li v-for="asset in result.asset_checklist" :key="asset">&bull; {{ asset }}</li>
        </ul>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Caption</p>
      <p class="mt-3 text-sm leading-7 text-neutral-200">{{ result.caption }}</p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hashtags</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span
          v-for="hashtag in result.hashtags"
          :key="hashtag"
          class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-neutral-300"
        >
          {{ hashtag }}
        </span>
      </div>
    </section>

    <section class="rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">CTA</p>
      <p class="mt-3 font-semibold leading-7">{{ result.cta }}</p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Editing notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
        <li v-for="note in result.editing_notes" :key="note">&#10003; {{ note }}</li>
      </ul>
    </section>

    <section class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">
        Compliance notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="note in result.compliance_notes" :key="note">&bull; {{ note }}</li>
      </ul>
    </section>
  </div>
</template>
