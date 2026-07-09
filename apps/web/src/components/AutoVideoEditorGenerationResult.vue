<script setup lang="ts">
import type { AutoVideoEditorResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ result: AutoVideoEditorResult }>()
</script>

<template>
  <div class="space-y-5" data-testid="auto-video-editor-result">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Auto Video Editor</p>
      <h2 class="mt-3 text-2xl font-bold">Edit-ready vertical video plan</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.source_summary }}</p>
      <p class="mt-4 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-sm leading-6 text-sky-100/80">
        {{ result.final_video_placeholder.message }}
      </p>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Selected video analysis</p>
        <p class="mt-3 text-sm leading-6 text-neutral-200">{{ result.selected_video_analysis_reference.note }}</p>
        <p class="mt-2 text-xs text-neutral-500">{{ formatLabel(result.selected_video_analysis_reference.type) }}</p>
      </div>
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Selected voiceover</p>
        <p class="mt-3 text-sm leading-6 text-neutral-200">{{ result.selected_voiceover_reference.note }}</p>
        <p class="mt-2 text-xs text-neutral-500">{{ formatLabel(result.selected_voiceover_reference.type) }}</p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Cost breakdown</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <article class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xl font-bold">{{ result.cost_breakdown.base_editor_cost }}</p>
          <p class="mt-1 text-xs text-neutral-500">{{ result.cost_breakdown.base_editor_note }}</p>
        </article>
        <article class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xl font-bold">{{ result.cost_breakdown.video_analysis_cost }}</p>
          <p class="mt-1 text-xs text-neutral-500">{{ result.cost_breakdown.video_analysis_note }}</p>
        </article>
        <article class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xl font-bold">{{ result.cost_breakdown.voiceover_cost }}</p>
          <p class="mt-1 text-xs text-neutral-500">{{ result.cost_breakdown.voiceover_note }}</p>
        </article>
        <article class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xl font-bold text-emerald-300">{{ result.cost_breakdown.total_credits }}</p>
          <p class="mt-1 text-xs text-neutral-500">Total internal credits</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Edit decision list</p>
      <div class="mt-4 space-y-3">
        <article v-for="item in result.edit_decision_list" :key="item.order" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ item.timestamp }} ? Decision {{ item.order }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-100">{{ item.decision }}</p>
          <p class="mt-2 text-xs leading-5 text-neutral-500">{{ item.reason }}</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Timeline</p>
      <div class="mt-4 space-y-3">
        <article v-for="item in result.timeline" :key="item.timestamp + item.layer" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ item.timestamp }} ? {{ formatLabel(item.layer) }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-100">{{ item.instruction }}</p>
          <p class="mt-2 text-xs font-medium text-neutral-500">Overlay: {{ item.text_overlay }}</p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Clip sequence</p>
        <ul class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
          <li v-for="clip in result.clip_sequence" :key="clip.clip" class="rounded-2xl bg-neutral-950/70 p-3">
            <span class="font-semibold text-neutral-100">{{ clip.clip }}:</span> {{ clip.use }}
            <span class="block text-xs text-neutral-500">Source: {{ clip.source }}</span>
          </li>
        </ul>
      </div>
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions plan</p>
        <p class="mt-3 text-sm leading-6 text-neutral-200">{{ result.captions_plan.safe_zone }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <span v-for="caption in result.captions_plan.sample_captions" :key="caption" class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-200">
            {{ caption }}
          </span>
        </div>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Transition plan</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.transition_plan" :key="item">? {{ item }}</li>
        </ul>
      </div>
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Audio mix plan</p>
        <p class="mt-3 text-sm leading-6 text-neutral-300">{{ result.audio_mix_plan.music_direction }}</p>
        <ul class="mt-3 space-y-2 text-sm leading-6 text-neutral-400">
          <li v-for="item in result.audio_mix_plan.mix_notes" :key="item">? {{ item }}</li>
        </ul>
      </div>
    </section>

    <section v-if="result.voiceover_script" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Voiceover script</p>
      <p class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-100">
        {{ result.voiceover_script }}
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Render settings</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <p v-for="(value, key) in result.render_settings" :key="key" class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          <span class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ formatLabel(String(key)) }}</span>
          {{ formatLabel(String(value)) }}
        </p>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-amber-100/80">
          <li v-for="item in result.compliance_notes" :key="item">? {{ item }}</li>
        </ul>
      </div>
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Usage notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.usage_notes" :key="item">? {{ item }}</li>
        </ul>
      </div>
    </section>
  </div>
</template>
