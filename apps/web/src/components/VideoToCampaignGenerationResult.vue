<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import type {
  CreatorGeneration,
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
  linkedVoiceoverGeneration?: CreatorGeneration | null
}>()

const projectRoute = computed(() => {
  return props.projectId === null || props.projectId === undefined ? '/dashboard' : `/projects/${String(props.projectId)}`
})
const autoVideoEditorRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || props.generationId === null || props.generationId === undefined) {
    return null
  }

  const query: Record<string, string> = {
    editMode: 'ai_video_analysis',
    analysisGenerationId: String(props.generationId),
  }

  const sourceVideoAssetId = props.result.source_video_asset_id ?? props.result.provider_metadata?.source_video_asset_id ?? null

  if (typeof sourceVideoAssetId === 'number' && Number.isFinite(sourceVideoAssetId)) {
    query.sourceVideoAssetId = String(sourceVideoAssetId)
  }

  if (props.linkedVoiceoverGeneration !== null && props.linkedVoiceoverGeneration !== undefined) {
    query.voiceoverGenerationId = String(props.linkedVoiceoverGeneration.id)
  }

  return {
    name: 'project-auto-video-editor-action',
    params: { projectId: String(props.projectId) },
    query,
  }
})
const motionAiVideoRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || props.generationId === null || props.generationId === undefined) {
    return null
  }

  return {
    name: 'project-motion-ai-video-action',
    params: { projectId: String(props.projectId) },
    query: {
      sourceMode: 'campaign_assisted',
      campaignGenerationId: String(props.generationId),
    },
  }
})
const linkedVoiceoverRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || props.linkedVoiceoverGeneration === null || props.linkedVoiceoverGeneration === undefined) {
    return null
  }

  return `/projects/${String(props.projectId)}/generations/${String(props.linkedVoiceoverGeneration.id)}`
})
const narrationScript = computed(() => props.result.narration_voice_script.trim())
const voiceoverCostLabel = computed(() => {
  const cost = props.voiceoverAction?.credit_cost

  if (typeof cost !== 'number') {
    return null
  }

  return cost === 1 ? '1 credit' : `${cost} credits`
})

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
      source_workflow: 'analyze_footage',
    },
  }
})

const providerBadge = computed(() => {
  const provider = props.result.provider_metadata?.provider

  if (provider === 'gemini') {
    return 'Gemini real video analysis'
  }

  if (provider === 'mock') {
    return 'Video analysis'
  }

  return null
})

const editPlanTitle = computed(() => props.result.edit_plan.title ?? 'Video edit plan')
const hooks = computed(() => props.result.hooks.filter((hook) => meaningfulString(hookText(hook))))
const captions = computed(() => props.result.captions.filter(meaningfulString))
const hashtags = computed(() => props.result.hashtags.filter(meaningfulString))
const ctaOptions = computed(() => props.result.cta_options.filter(meaningfulString))
const observedProductFeatures = computed(() => (props.result.observed_product_features ?? []).filter((feature) => meaningfulString(feature.feature) || meaningfulString(feature.evidence)))
const detectedProductMoments = computed(() => props.result.detected_product_moments.filter(hasMomentContent))
const sceneObservations = computed(() => props.result.scene_observations.filter(hasObservationContent))
const recommendedCuts = computed(() => props.result.recommended_cuts.filter(hasCutContent))
const editPlanTimeline = computed(() => props.result.edit_plan.timeline.filter(hasEditTimelineContent))
const hasEditPlanOverview = computed(() => [props.result.edit_plan.opening, props.result.edit_plan.middle, props.result.edit_plan.closing].some(meaningfulString))
const complianceNotes = computed(() => props.result.compliance_notes.filter(meaningfulString))
const usageNotes = computed(() => props.result.usage_notes.filter(meaningfulString))

function meaningfulString(value: string | null | undefined): value is string {
  return typeof value === 'string' && value.trim() !== ''
}

function hasTiming(item: { timestamp?: string; start_seconds?: number; end_seconds?: number }): boolean {
  return meaningfulString(item.timestamp) || typeof item.start_seconds === 'number' || typeof item.end_seconds === 'number'
}

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

function hasMomentContent(moment: VideoToCampaignMoment): boolean {
  return hasTiming(moment)
    || meaningfulString(moment.title)
    || meaningfulString(moment.moment)
    || meaningfulString(moment.description)
    || meaningfulString(moment.campaign_use)
    || meaningfulString(moment.why_it_matters)
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

function hasObservationContent(item: VideoToCampaignObservation): boolean {
  return hasTiming(item) || meaningfulString(item.scene) || meaningfulString(item.observation)
}

function cutText(item: VideoToCampaignCut): string {
  return item.cut ?? item.reason
}

function cutMeta(item: VideoToCampaignCut): string | null {
  return [item.cut ? item.reason : null, formatMeta(item.priority), formatMeta(item.visual_quality), formatMeta(item.stability), formatMeta(item.product_visibility)].filter(Boolean).join(' · ') || null
}

function hasCutContent(item: VideoToCampaignCut): boolean {
  return hasTiming(item)
    || meaningfulString(item.cut)
    || meaningfulString(item.reason)
    || meaningfulString(item.purpose)
    || meaningfulString(item.visual_reason)
    || meaningfulString(item.caption_suggestion)
}

function editInstruction(item: VideoToCampaignEditPlanItem): string {
  return item.action ?? item.instruction ?? item.purpose ?? item.visual_reason ?? 'Edit this moment.'
}

function editCaption(item: VideoToCampaignEditPlanItem): string | null {
  return item.caption ?? item.caption_suggestion ?? null
}

function hasEditTimelineContent(item: VideoToCampaignEditPlanItem): boolean {
  return hasTiming(item)
    || meaningfulString(item.action)
    || meaningfulString(item.instruction)
    || meaningfulString(item.purpose)
    || meaningfulString(item.visual_reason)
    || meaningfulString(item.caption)
    || meaningfulString(item.caption_suggestion)
}
</script>

<template>
  <div class="space-y-5" data-testid="video-to-campaign-result">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-wrap items-center gap-2">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Analyze Footage with AI · Video analysis</p>
        <span
          v-if="providerBadge"
          class="rounded-full border border-emerald-800 bg-emerald-950 px-2.5 py-1 text-[11px] font-semibold text-emerald-300"
        >
          {{ providerBadge }}
        </span>
      </div>
      <h2 class="mt-3 text-2xl font-bold">Footage analysis ready</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.video_summary }}</p>
      <p class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm leading-6 text-neutral-300">
        {{ result.campaign_angle }}
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5" data-testid="video-analysis-next-actions">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Next workflow</p>
      <h3 class="mt-2 text-xl font-semibold">Continue with this footage analysis</h3>
      <p class="mt-2 text-sm leading-6 text-neutral-400">
        Use the detected product moments and edit plan in Auto Video Editor, or turn this analysis into Motion AI creative direction. No generation starts until you confirm in the next setup screen.
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <RouterLink
          v-if="autoVideoEditorRoute"
          :to="autoVideoEditorRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-center text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          data-testid="analysis-continue-auto-editor"
        >
          Continue to Auto Video Editor
        </RouterLink>
        <RouterLink
          v-if="motionAiVideoRoute"
          :to="motionAiVideoRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-sky-800 bg-sky-950/30 px-4 text-center text-sm font-semibold text-sky-100 transition hover:border-sky-500 hover:bg-sky-900/40"
          data-testid="analysis-create-motion-video"
        >
          Create Motion AI Video
        </RouterLink>
        <RouterLink
          :to="projectRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-center text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          data-testid="analysis-return-project"
        >
          Return to project
        </RouterLink>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5" data-testid="analysis-optional-voiceover">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Optional voiceover</p>
          <h3 class="mt-2 text-xl font-semibold">Narration from this analysis</h3>
        </div>
        <RouterLink
          v-if="voiceoverRoute && narrationScript && !linkedVoiceoverRoute && voiceoverAction?.user_has_access !== false"
          :to="voiceoverRoute"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          data-testid="create-voiceover-from-video-analysis"
        >
          Create voiceover
        </RouterLink>
      </div>
      <p class="mt-2 text-sm leading-6 text-neutral-400">
        Create a voiceover from the narration prepared by this footage analysis. This is explicit and charged only when you click Create voiceover.
      </p>
      <RouterLink
        v-if="linkedVoiceoverRoute"
        :to="linkedVoiceoverRoute"
        class="mt-4 inline-flex min-h-11 items-center justify-center rounded-2xl border border-emerald-800 bg-emerald-950/40 px-4 text-sm font-semibold text-emerald-100 transition hover:border-emerald-500 hover:bg-emerald-900/50"
        data-testid="analysis-linked-voiceover"
      >
        Linked voiceover ready
      </RouterLink>
      <p v-if="voiceoverCostLabel" class="mt-3 text-xs font-semibold text-emerald-300">
        Voiceover cost: {{ voiceoverCostLabel }}
      </p>
      <p v-if="narrationScript" class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-100">
        {{ narrationScript }}
      </p>
      <p v-else class="mt-4 rounded-2xl border border-amber-900/50 bg-amber-950/20 p-3 text-sm leading-6 text-amber-100/80" data-testid="analysis-missing-voiceover-script">
        This analysis did not create a usable narration script, so voiceover generation is disabled for this result.
      </p>
    </section>

    <section v-if="hooks.length || captions.length" class="grid gap-5 lg:grid-cols-2">
      <div v-if="hooks.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hooks</p>
        <ol class="mt-4 space-y-3 text-sm leading-6 text-neutral-200">
          <li v-for="(hook, index) in hooks" :key="`${index}-${hookText(hook)}`" class="rounded-2xl bg-neutral-950/70 p-3">
            <span class="text-emerald-300">{{ index + 1 }}.</span> {{ hookText(hook) }}
            <p v-if="hookMeta(hook)" class="mt-1 text-xs leading-5 text-neutral-500">{{ hookMeta(hook) }}</p>
          </li>
        </ol>
      </div>

      <div v-if="captions.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions</p>
        <ul class="mt-4 space-y-3 text-sm leading-6 text-neutral-200">
          <li v-for="caption in captions" :key="caption" class="rounded-2xl bg-neutral-950/70 p-3">
            {{ caption }}
          </li>
        </ul>
      </div>
    </section>

    <section v-if="observedProductFeatures.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Observed product features</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <article v-for="feature in observedProductFeatures" :key="feature.feature || feature.evidence" class="rounded-2xl bg-neutral-950/70 p-4">
          <p v-if="feature.feature" class="text-sm font-semibold text-neutral-100">{{ feature.feature }}</p>
          <p v-if="feature.evidence" class="mt-2 text-sm leading-6 text-neutral-400">{{ feature.evidence }}</p>
          <p v-if="feature.confidence" class="mt-2 text-xs text-neutral-600">Confidence: {{ feature.confidence }}</p>
        </article>
      </div>
    </section>

    <section v-if="detectedProductMoments.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Detected product moments</p>
      <div class="mt-4 space-y-3">
        <article v-for="(moment, index) in detectedProductMoments" :key="`${index}-${timeRange(moment)}-${momentTitle(moment)}`" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ timeRange(moment) }}</p>
          <p class="mt-2 text-sm font-semibold text-neutral-100">{{ momentTitle(moment) }}</p>
          <p v-if="momentDescription(moment)" class="mt-2 text-sm leading-6 text-neutral-400">{{ momentDescription(moment) }}</p>
          <p v-if="momentMeta(moment)" class="mt-2 text-xs leading-5 text-neutral-500">{{ momentMeta(moment) }}</p>
        </article>
      </div>
    </section>

    <section v-if="sceneObservations.length || recommendedCuts.length" class="grid gap-5 lg:grid-cols-2">
      <div v-if="sceneObservations.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Scene observations</p>
        <div class="mt-4 space-y-3">
          <article v-for="(item, index) in sceneObservations" :key="`${index}-${observationTitle(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-sm font-semibold text-neutral-100">{{ observationTitle(item) }}</p>
            <p v-if="item.observation" class="mt-2 text-sm leading-6 text-neutral-400">{{ item.observation }}</p>
            <p v-if="observationMeta(item)" class="mt-2 text-xs text-neutral-600">{{ observationMeta(item) }}</p>
          </article>
        </div>
      </div>

      <div v-if="recommendedCuts.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Recommended cuts</p>
        <div class="mt-4 space-y-3">
          <article v-for="(item, index) in recommendedCuts" :key="`${index}-${timeRange(item)}-${cutText(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
            <p class="text-xs font-semibold text-emerald-300">{{ timeRange(item) }}</p>
            <p class="mt-2 text-sm leading-6 text-neutral-100">{{ cutText(item) }}</p>
            <p v-if="item.visual_reason" class="mt-2 text-sm leading-6 text-neutral-400">{{ item.visual_reason }}</p>
            <p v-if="cutMeta(item)" class="mt-2 text-xs leading-5 text-neutral-500">{{ cutMeta(item) }}</p>
          </article>
        </div>
      </div>
    </section>

    <section v-if="hasEditPlanOverview || editPlanTimeline.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Edit plan</p>
      <h3 class="mt-2 text-xl font-semibold">{{ editPlanTitle }}</h3>
      <div v-if="hasEditPlanOverview" class="mt-4 grid gap-3 sm:grid-cols-3">
        <p v-if="result.edit_plan.opening" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.opening }}</p>
        <p v-if="result.edit_plan.middle" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.middle }}</p>
        <p v-if="result.edit_plan.closing" class="rounded-2xl bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-300">{{ result.edit_plan.closing }}</p>
      </div>
      <div v-if="editPlanTimeline.length" class="mt-4 space-y-3">
        <article v-for="(item, index) in editPlanTimeline" :key="`${index}-${timeRange(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ timeRange(item) }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-200">{{ editInstruction(item) }}</p>
          <p v-if="editCaption(item)" class="mt-2 text-xs leading-5 text-neutral-500">Caption: {{ editCaption(item) }}</p>
        </article>
      </div>
    </section>

    <section v-if="hashtags.length || ctaOptions.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Hashtags and CTA</p>
      <div v-if="hashtags.length" class="mt-4 flex flex-wrap gap-2">
        <span v-for="hashtag in hashtags" :key="hashtag" class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-200">
          {{ hashtag }}
        </span>
      </div>
      <div v-if="ctaOptions.length" class="mt-5 grid gap-3 sm:grid-cols-3">
        <p v-for="cta in ctaOptions" :key="cta" class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-3 text-sm leading-6 text-neutral-200">
          {{ cta }}
        </p>
      </div>
    </section>

    <section v-if="complianceNotes.length || usageNotes.length" class="grid gap-5 lg:grid-cols-2">
      <div v-if="complianceNotes.length" class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-amber-100/80">
          <li v-for="item in complianceNotes" :key="item">• {{ item }}</li>
        </ul>
      </div>
      <div v-if="usageNotes.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Usage notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in usageNotes" :key="item">• {{ item }}</li>
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
