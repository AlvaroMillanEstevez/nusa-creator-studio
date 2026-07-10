<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import type {
  ProjectAction,
  VideoToCampaignCut,
  VideoToCampaignEditPlanItem,
  VideoToCampaignHookResult,
  VideoToCampaignMoment,
  VideoToCampaignObservation,
  VideoToCampaignResult,
} from '@/types/project'

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

const providerBadge = computed(() => {
  const provider = props.result.provider_metadata?.provider

  if (provider === 'gemini') {
    return 'Gemini real video analysis'
  }

  if (provider === 'mock') {
    return 'Mock preview'
  }

  return null
})

const editPlanTitle = computed(() => props.result.edit_plan.title ?? 'Video edit plan')

function secondsLabel(value: number | undefined): string | null {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return null
  }

  return `${Number.isInteger(value) ? value : value.toFixed(1)}s`
}

function timeRange(item: { timestamp?: string; start_seconds?: number; end_seconds?: number }): string {
  if (item.timestamp) {
    return item.timestamp
  }

  const start = secondsLabel(item.start_seconds)
  const end = secondsLabel(item.end_seconds)

  if (start && end) {
    return `${start}-${end}`
  }

  if (start) {
    return `${start}+`
  }

  return 'Timing TBD'
}

function formatMeta(value: string | undefined): string | null {
  return value ? value.replace(/_/g, ' ') : null
}

function hookText(hook: VideoToCampaignHookResult): string {
  return typeof hook === 'string' ? hook : hook.hook
}

function hookMeta(hook: VideoToCampaignHookResult): string | null {
  if (typeof hook === 'string') {
    return null
  }

  return [formatMeta(hook.style), hook.best_for].filter(Boolean).join(' · ') || null
}

function momentTitle(moment: VideoToCampaignMoment): string {
  return moment.title ?? moment.moment ?? 'Product moment'
}

function momentDescription(moment: VideoToCampaignMoment): string {
  return moment.description ?? moment.campaign_use ?? moment.why_it_matters ?? ''
}

function momentMeta(moment: VideoToCampaignMoment): string | null {
  return [moment.why_it_matters, formatMeta(moment.visual_quality)].filter(Boolean).join(' · ') || null
}

function observationTitle(item: VideoToCampaignObservation): string {
  return item.scene ?? timeRange(item)
}

function observationMeta(item: VideoToCampaignObservation): string | null {
  if (item.confidence) {
    return `Confidence: ${item.confidence}`
  }

  if (typeof item.usable_for_edit === 'boolean') {
    return item.usable_for_edit ? 'Usable for edit' : 'Context only'
  }

  return null
}

function cutText(item: VideoToCampaignCut): string {
  return item.cut ?? item.reason
}

function cutMeta(item: VideoToCampaignCut): string | null {
  return [item.cut ? item.reason : null, formatMeta(item.priority)].filter(Boolean).join(' · ') || null
}

function editInstruction(item: VideoToCampaignEditPlanItem): string {
  return item.action ?? item.instruction ?? 'Edit this moment.'
}

function editCaption(item: VideoToCampaignEditPlanItem): string | null {
  return item.caption ?? null
}
</script>

<template>
  <div class="space-y-5" data-testid="video-to-campaign-result">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-wrap items-center gap-2">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Video-to-Campaign</p>
        <span
          v-if="providerBadge"
          class="rounded-full border border-emerald-800 bg-emerald-950 px-2.5 py-1 text-[11px] font-semibold text-emerald-300"
        >
          {{ providerBadge }}
        </span>
      </div>
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
          <li v-for="(hook, index) in result.hooks" :key="`${index}-${hookText(hook)}`" class="rounded-2xl bg-neutral-950/70 p-3">
            <span class="text-emerald-300">{{ index + 1 }}.</span> {{ hookText(hook) }}
            <p v-if="hookMeta(hook)" class="mt-1 text-xs leading-5 text-neutral-500">{{ hookMeta(hook) }}</p>
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

    <section v-if="result.observed_product_features?.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Observed product features</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <article v-for="feature in result.observed_product_features" :key="feature.feature" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-sm font-semibold text-neutral-100">{{ feature.feature }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">{{ feature.evidence }}</p>
          <p class="mt-2 text-xs text-neutral-600">Confidence: {{ feature.confidence }}</p>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Detected product moments</p>
      <div class="mt-4 space-y-3">
        <article v-for="(moment, index) in result.detected_product_moments" :key="`${index}-${timeRange(moment)}-${momentTitle(moment)}`" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ timeRange(moment) }}</p>
          <p class="mt-2 text-sm font-semibold text-neutral-100">{{ momentTitle(moment) }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">{{ momentDescription(moment) }}</p>
          <p v-if="momentMeta(moment)" class="mt-2 text-xs leading-5 text-neutral-500">{{ momentMeta(moment) }}</p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Scene observations</p>
        <div class="mt-4 space-y-3">
          <article v-for="(item, index) in result.scene_observations" :key="`${index}-${observationTitle(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-sm font-semibold text-neutral-100">{{ observationTitle(item) }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-400">{{ item.observation }}</p>
            <p v-if="observationMeta(item)" class="mt-2 text-xs text-neutral-600">{{ observationMeta(item) }}</p>
          </article>
        </div>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Recommended cuts</p>
        <div class="mt-4 space-y-3">
          <article v-for="(item, index) in result.recommended_cuts" :key="`${index}-${timeRange(item)}-${cutText(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-xs font-semibold text-emerald-300">{{ timeRange(item) }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-100">{{ cutText(item) }}</p>
            <p v-if="cutMeta(item)" class="mt-2 text-xs leading-5 text-neutral-500">{{ cutMeta(item) }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Edit plan</p>
      <h3 class="mt-2 text-xl font-semibold">{{ editPlanTitle }}</h3>
      <div v-if="result.edit_plan.opening || result.edit_plan.middle || result.edit_plan.closing" class="mt-4 grid gap-3 sm:grid-cols-3">
        <p v-if="result.edit_plan.opening" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.opening }}</p>
        <p v-if="result.edit_plan.middle" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.middle }}</p>
        <p v-if="result.edit_plan.closing" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.closing }}</p>
      </div>
      <div class="mt-4 space-y-3">
        <article v-for="(item, index) in result.edit_plan.timeline" :key="`${index}-${timeRange(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ timeRange(item) }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-200">{{ editInstruction(item) }}</p>
          <p v-if="editCaption(item)" class="mt-2 text-xs leading-5 text-neutral-500">Caption: {{ editCaption(item) }}</p>
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
          <li v-for="item in result.compliance_notes" :key="item">• {{ item }}</li>
        </ul>
      </div>
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Usage notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.usage_notes" :key="item">• {{ item }}</li>
        </ul>
      </div>
    </section>

    <section v-if="result.missing_information?.length || result.unverified_claims?.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Review before publishing</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div v-if="result.missing_information?.length">
          <h4 class="text-sm font-semibold text-neutral-100">Missing information</h4>
          <ul class="mt-3 space-y-2 text-sm leading-6 text-neutral-400">
            <li v-for="item in result.missing_information" :key="item">• {{ item }}</li>
          </ul>
        </div>
        <div v-if="result.unverified_claims?.length">
          <h4 class="text-sm font-semibold text-neutral-100">Unverified claims</h4>
          <ul class="mt-3 space-y-2 text-sm leading-6 text-neutral-400">
            <li v-for="item in result.unverified_claims" :key="item">• {{ item }}</li>
          </ul>
        </div>
      </div>
    </section>
  </div>
</template>
