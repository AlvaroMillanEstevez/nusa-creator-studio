<script setup lang="ts">
import type { FashionTryOnResult } from '@/types/project'

defineProps<{ result: FashionTryOnResult }>()
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Try-on concept</p>
      <h2 class="mt-3 text-xl font-bold">{{ result.try_on_title }}</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.concept_summary }}</p>
    </section>

    <section class="rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">Generated image placeholder</p>
      <p class="mt-3 text-sm leading-7 text-sky-100/80">{{ result.generated_image.message }}</p>
      <p class="mt-3 text-xs text-sky-300">
        Provider: {{ result.generated_image.provider }} · Status: {{ result.generated_image.status }}
      </p>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">References</p>
        <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.model_reference_summary }}</p>
        <p class="mt-3 text-sm leading-7 text-neutral-200">{{ result.garment_reference_summary }}</p>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Composition plan</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.composition_plan" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Pose and framing</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.pose_and_framing_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Garment fit notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.garment_fit_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Background and lighting</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.background_and_lighting_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Platform usage</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.platform_usage_notes" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">API-ready prompt</p>
      <p class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
        {{ result.api_ready_prompt }}
      </p>
      <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">Negative prompt</p>
      <p class="mt-2 text-sm leading-7 text-neutral-300">{{ result.negative_prompt }}</p>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Quality checklist</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.quality_checklist" :key="item">✓ {{ item }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Recommended assets</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.recommended_assets" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in result.compliance_notes" :key="item">&bull; {{ item }}</li>
      </ul>
    </section>
  </div>
</template>