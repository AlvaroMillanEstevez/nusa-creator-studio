<script setup lang="ts">
import type { MotionVideoResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ result: MotionVideoResult }>()
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Motion video concept</p>
      <h2 class="mt-3 text-xl font-bold">{{ result.video_title }}</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.concept_summary }}</p>
    </section>

    <section class="rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">Generated video placeholder</p>
      <p class="mt-3 text-sm leading-7 text-sky-100/80">{{ result.generated_video.message }}</p>
      <p class="mt-3 text-xs text-sky-300">
        Provider: {{ result.generated_video.provider }} · Status: {{ result.generated_video.status }}
      </p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Selected references</p>
      <div class="mt-4 space-y-3">
        <article
          v-for="asset in result.selected_references.visual_references"
          :key="'visual-' + asset.id"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3"
        >
          <p class="text-sm font-semibold text-neutral-200">{{ asset.original_name ?? 'Uploaded visual asset' }}</p>
          <p class="mt-1 text-xs text-neutral-500">
            {{ formatLabel(asset.type) }} · {{ formatLabel(asset.usage_role ?? undefined) }}
          </p>
        </article>
        <article
          v-for="asset in result.selected_references.audio_tracks"
          :key="'audio-' + asset.id"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3"
        >
          <p class="text-sm font-semibold text-neutral-200">{{ asset.original_name ?? 'Uploaded audio asset' }}</p>
          <p class="mt-1 text-xs text-neutral-500">Audio track</p>
        </article>
        <p v-if="result.selected_references.visual_references.length === 0" class="text-sm text-neutral-500">
          No visual references were included in this result.
        </p>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Scene plan</p>
      <div class="mt-4 space-y-4">
        <article v-for="scene in result.scene_plan" :key="scene.timestamp" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ scene.timestamp }} · {{ scene.scene_goal }}</p>
          <p class="mt-3 text-sm leading-6 text-neutral-200">{{ scene.visual_direction }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">Motion: {{ scene.motion_direction }}</p>
          <p class="mt-2 text-xs font-medium text-neutral-500">On-screen: {{ scene.on_screen_text }}</p>
        </article>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Motion prompt</p>
      <p class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
        {{ result.motion_prompt }}
      </p>
      <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">Negative prompt</p>
      <p class="mt-2 text-sm leading-7 text-neutral-300">{{ result.negative_prompt }}</p>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Camera motion notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.camera_motion_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Editing plan</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.editing_plan" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Audio sync notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.audio_sync_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Platform usage</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.platform_usage_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Quality checklist</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.quality_checklist" :key="item">✓ {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Recommended asset improvements</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.recommended_asset_improvements" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">API-ready payload preview</p>
      <pre class="mt-4 overflow-x-auto whitespace-pre-wrap rounded-2xl bg-neutral-950/70 p-4 text-xs leading-6 text-neutral-300">{{ JSON.stringify(result.api_ready_payload_preview, null, 2) }}</pre>
    </section>

    <section class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in result.compliance_notes" :key="item">&bull; {{ item }}</li>
      </ul>
    </section>
  </div>
</template>