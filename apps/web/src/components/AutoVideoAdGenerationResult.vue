<script setup lang="ts">
import type { AutoVideoAdResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{
  result: AutoVideoAdResult
}>()

function secondsRange(start: number, end: number): string {
  return `${start}s-${end}s`
}
</script>

<template>
  <article data-testid="auto-video-ad-result" class="space-y-5">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Auto Video Ads
          </p>
          <h2 class="mt-2 text-2xl font-bold">Paid ad creative plan</h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-neutral-400">
            {{ result.ad_summary.creative_angle }}
          </p>
        </div>
        <span class="rounded-full border border-sky-800 bg-sky-950 px-3 py-1 text-xs font-semibold text-sky-200">
          {{ formatLabel(result.ad_summary.visual_source_type) }} ad
        </span>
      </div>

      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
          <p class="text-xs text-neutral-500">Platform</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ formatLabel(result.ad_summary.platform) }}</p>
        </div>
        <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
          <p class="text-xs text-neutral-500">Format</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ formatLabel(result.ad_summary.format) }}</p>
        </div>
        <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
          <p class="text-xs text-neutral-500">Duration</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ result.ad_summary.duration }}s</p>
        </div>
        <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
          <p class="text-xs text-neutral-500">Goal</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ formatLabel(result.ad_summary.ad_goal) }}</p>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-amber-900/60 bg-amber-950/20 p-4">
        <p class="text-sm font-semibold text-amber-100">Final MP4 rendering coming next</p>
        <p class="mt-2 text-sm leading-6 text-amber-100/80">
          This is an ad plan. Final MP4 rendering will be added in the next beta step.
        </p>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
      <div class="nusa-card-soft rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hook options</p>
        <div class="mt-4 space-y-3">
          <article
            v-for="hook in result.hook_options"
            :key="hook.text"
            class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4"
          >
            <p class="text-sm font-semibold text-white">{{ hook.text }}</p>
            <p class="mt-2 text-xs leading-5 text-neutral-500">{{ hook.why_it_works }}</p>
            <span class="mt-3 inline-flex rounded-full border border-neutral-700 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
              {{ formatLabel(hook.style) }}
            </span>
          </article>
        </div>
      </div>

      <div class="nusa-card-soft rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Ad script</p>
        <div class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
          <p><span class="font-semibold text-neutral-100">Opening:</span> {{ result.ad_script.opening_hook }}</p>
          <p><span class="font-semibold text-neutral-100">Context:</span> {{ result.ad_script.problem_or_context }}</p>
          <div>
            <p class="font-semibold text-neutral-100">Benefit sequence</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-neutral-400">
              <li v-for="line in result.ad_script.product_benefit_sequence" :key="line">{{ line }}</li>
            </ul>
          </div>
          <p><span class="font-semibold text-neutral-100">Proof/demo:</span> {{ result.ad_script.proof_or_demo_line }}</p>
          <p><span class="font-semibold text-neutral-100">CTA:</span> {{ result.ad_script.CTA }}</p>
        </div>
        <div class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">Voiceover-ready script</p>
          <p class="mt-2 text-sm leading-6 text-neutral-300">{{ result.ad_script.voiceover_ready_script }}</p>
        </div>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Storyboard timeline</p>
      <div class="mt-5 space-y-3">
        <article
          v-for="scene in result.storyboard"
          :key="scene.scene_number"
          class="grid gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4 sm:grid-cols-[7rem_1fr]"
        >
          <div>
            <p class="text-sm font-semibold text-white">{{ secondsRange(scene.start_seconds, scene.end_seconds) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Scene {{ scene.scene_number }}</p>
          </div>
          <div class="min-w-0">
            <p class="break-words text-sm font-semibold text-neutral-100">{{ scene.visual_direction }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-400">{{ scene.narration_line }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="rounded-full border border-neutral-700 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                {{ formatLabel(scene.asset_role) }}
              </span>
              <span class="rounded-full border border-emerald-800 bg-emerald-950 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">
                {{ scene.overlay_text }}
              </span>
            </div>
            <p class="mt-2 text-xs leading-5 text-neutral-500">{{ scene.transition_note }}</p>
          </div>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-soft rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Overlay plan</p>
        <div class="mt-4 space-y-3">
          <article
            v-for="overlay in result.overlay_plan"
            :key="overlay.text + overlay.timing"
            class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-semibold text-white">{{ overlay.text }}</p>
              <span class="text-xs text-neutral-500">{{ overlay.timing }}</span>
            </div>
            <p class="mt-2 text-xs leading-5 text-neutral-500">{{ overlay.purpose }}</p>
            <p class="mt-2 text-xs text-neutral-400">{{ overlay.placement }} · {{ overlay.style }}</p>
          </article>
        </div>
      </div>

      <div class="space-y-5">
        <section class="nusa-card-soft rounded-[2rem] p-5">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions</p>
          <div class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
            <p><span class="font-semibold text-neutral-100">Short:</span> {{ result.caption_variants.short_caption }}</p>
            <p><span class="font-semibold text-neutral-100">Direct response:</span> {{ result.caption_variants.direct_response_caption }}</p>
            <p><span class="font-semibold text-neutral-100">Soft sell:</span> {{ result.caption_variants.soft_sell_caption }}</p>
          </div>
        </section>

        <section class="nusa-card-soft rounded-[2rem] p-5">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">CTA options</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="cta in result.cta_options"
              :key="cta"
              class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1.5 text-xs font-semibold text-neutral-300"
            >
              {{ cta }}
            </span>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="tag in result.hashtags"
              :key="tag"
              class="rounded-full border border-neutral-800 bg-neutral-950/60 px-3 py-1.5 text-xs text-neutral-400"
            >
              {{ tag }}
            </span>
          </div>
        </section>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-soft rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Compliance notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="note in result.compliance_notes" :key="note" class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3">
            {{ note }}
          </li>
        </ul>
      </div>

      <div class="nusa-card-soft rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Future render plan</p>
        <div class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
          <p><span class="font-semibold text-neutral-100">Renderer:</span> {{ result.render_plan.renderer }}</p>
          <p><span class="font-semibold text-neutral-100">Timeline:</span> {{ result.render_plan.timeline_strategy }}</p>
          <p><span class="font-semibold text-neutral-100">Overlay:</span> {{ result.render_plan.overlay_style }}</p>
          <p><span class="font-semibold text-neutral-100">End card:</span> {{ result.render_plan.end_card_strategy }}</p>
        </div>
        <div v-if="result.render_plan.missing_items.length" class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">Still needed</p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-neutral-400">
            <li v-for="item in result.render_plan.missing_items" :key="item">{{ item }}</li>
          </ul>
        </div>
      </div>
    </section>
  </article>
</template>
