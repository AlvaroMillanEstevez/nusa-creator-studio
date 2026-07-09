<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import type { ProjectAction, VideoToCampaignResult } from '@/types/project'

const props = defineProps<{
  result: VideoToCampaignResult
  projectId?: number | string | null
  generationId?: number | string | null
  voiceoverAction?: ProjectAction | null
}>()

const voiceoverRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || props.generationId === null || props.generationId === undefined) {
    return null
  }

  return {
    name: 'project-voiceover-action',
    params: { projectId: String(props.projectId) },
    query: {
      source_generation_id: String(props.generationId),
      return_to: `/projects/${String(props.projectId)}/generations/${String(props.generationId)}`,
    },
  }
})
</script>

<template>
  <div class="space-y-5" data-testid="video-to-campaign-result">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Video-to-Campaign</p>
      <h2 class="mt-3 text-2xl font-bold">Campaign from source video</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.video_summary }}</p>
      <p class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm leading-6 text-neutral-300">
        {{ result.campaign_angle }}
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Narration voice</p>
          <h3 class="mt-2 text-xl font-semibold">Voiceover-ready script</h3>
        </div>
        <RouterLink
          v-if="voiceoverRoute && voiceoverAction?.user_has_access !== false"
          :to="voiceoverRoute"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          data-testid="create-voiceover-from-video-campaign"
        >
          Create voiceover from this campaign
        </RouterLink>
      </div>
      <p class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-100">
        {{ result.narration_voice_script }}
      </p>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hooks</p>
        <ol class="mt-4 space-y-3 text-sm leading-6 text-neutral-200">
          <li v-for="(hook, index) in result.hooks" :key="hook" class="rounded-2xl bg-neutral-950/70 p-3">
            <span class="text-emerald-300">{{ index + 1 }}.</span> {{ hook }}
          </li>
        </ol>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions</p>
        <ul class="mt-4 space-y-3 text-sm leading-6 text-neutral-200">
          <li v-for="caption in result.captions" :key="caption" class="rounded-2xl bg-neutral-950/70 p-3">
            {{ caption }}
          </li>
        </ul>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Detected product moments</p>
      <div class="mt-4 space-y-3">
        <article v-for="moment in result.detected_product_moments" :key="moment.timestamp" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ moment.timestamp }}</p>
          <p class="mt-2 text-sm font-semibold text-neutral-100">{{ moment.moment }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">{{ moment.campaign_use }}</p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Scene observations</p>
        <div class="mt-4 space-y-3">
          <article v-for="item in result.scene_observations" :key="item.scene" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-sm font-semibold text-neutral-100">{{ item.scene }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-400">{{ item.observation }}</p>
            <p class="mt-2 text-xs text-neutral-600">Confidence: {{ item.confidence }}</p>
          </article>
        </div>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Recommended cuts</p>
        <div class="mt-4 space-y-3">
          <article v-for="item in result.recommended_cuts" :key="item.timestamp" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-xs font-semibold text-emerald-300">{{ item.timestamp }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-100">{{ item.cut }}</p>
            <p class="mt-2 text-xs leading-5 text-neutral-500">{{ item.reason }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Edit plan</p>
      <h3 class="mt-2 text-xl font-semibold">{{ result.edit_plan.title }}</h3>
      <div class="mt-4 space-y-3">
        <article v-for="item in result.edit_plan.timeline" :key="item.timestamp" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ item.timestamp }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-200">{{ item.action }}</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hashtags and CTA</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span v-for="hashtag in result.hashtags" :key="hashtag" class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-200">
          {{ hashtag }}
        </span>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <p v-for="cta in result.cta_options" :key="cta" class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-200">
          {{ cta }}
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
