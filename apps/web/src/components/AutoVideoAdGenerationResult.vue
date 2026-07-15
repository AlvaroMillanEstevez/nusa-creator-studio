<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { fetchMediaBlob } from '@/services/media'
import type { AutoVideoAdCaptionScheduleItem, AutoVideoAdResult, AutoVideoAdTimelineItem } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  result: AutoVideoAdResult
}>()

const result = computed(() => props.result)
const streamUrl = computed(() => result.value.final_video_stream_url ?? result.value.rendered_video_asset?.stream_url ?? null)
const downloadUrl = computed(() => result.value.final_video_download_url ?? result.value.rendered_video_asset?.download_url ?? null)
const hasRenderedVideo = computed(() => Boolean(streamUrl.value) && (result.value.metadata.render_available || result.value.render_plan.render_available === true))
const videoUrl = ref<string | null>(null)
const isPreviewLoading = ref(false)
const isDownloadingVideo = ref(false)
const videoError = ref('')
const downloadError = ref('')
const downloadFilename = computed(() => result.value.rendered_video_asset?.original_name ?? `auto-video-ad-${result.value.final_video_asset_id ?? 'video'}.mp4`)
const missingRenderItems = computed(() => result.value.render_plan.missing_items ?? [])
const timelineItems = computed(() => result.value.timeline ?? [])
const captionSchedule = computed(() => result.value.captions_plan ?? result.value.provider_metadata?.caption_schedule ?? [])
const renderSummaryItems = computed(() => {
  const metadata = result.value.metadata
  const provider = result.value.provider_metadata

  return [
    { label: 'Final duration', value: secondsValue(numberValue(metadata.final_duration_seconds) ?? numberValue(provider?.final_duration_seconds)) },
    { label: 'Clips used', value: numberText(numberValue(provider?.render_settings?.selected_clip_count) ?? numberValue(provider?.selected_clip_count) ?? timelineItems.value.length) },
    { label: 'Captions used', value: numberText(captionSchedule.value.length) },
    { label: 'CTA used', value: textValue(provider?.render_settings?.cta_used) ?? result.value.ad_summary.main_cta },
  ].filter((item): item is { label: string, value: string } => item.value !== null && item.value !== '')
})
const providerSummaryItems = computed(() => {
  const provider = result.value.provider_metadata

  return [
    { label: 'Provider', value: textValue(provider?.provider) ?? textValue(result.value.metadata.provider) },
    { label: 'Render status', value: textValue(provider?.provider_render_status) ?? textValue(result.value.metadata.provider_render_status) },
    { label: 'Final video asset', value: result.value.final_video_asset_id ? String(result.value.final_video_asset_id) : null },
    { label: 'Visual source', value: formatLabel(result.value.ad_summary.visual_source_type) },
    { label: 'Repeated clips', value: numberText(numberValue(provider?.repeated_clip_count)) },
    { label: 'Diverse source segments', value: booleanText(booleanValue(provider?.diverse_source_segments_used)) },
  ].filter((item): item is { label: string, value: string } => item.value !== null && item.value !== '')
})

function secondsRange(start: number, end: number): string {
  return `${start}s-${end}s`
}

function timelineRange(item: AutoVideoAdTimelineItem): string {
  if (item.timeline_range) {
    return item.timeline_range
  }

  if (typeof item.timeline_start_seconds === 'number' && typeof item.timeline_end_seconds === 'number') {
    return secondsRange(item.timeline_start_seconds, item.timeline_end_seconds)
  }

  return item.timestamp ?? 'Timeline clip'
}

function captionRange(caption: AutoVideoAdCaptionScheduleItem): string | null {
  if (typeof caption.start !== 'number' || typeof caption.length !== 'number' || caption.length <= 0) {
    return null
  }

  return secondsRange(caption.start, Number((caption.start + caption.length).toFixed(1)))
}

function numberValue(value: unknown): number | null {
  return typeof value === 'number' && !Number.isNaN(value) ? value : null
}

function booleanValue(value: unknown): boolean | null {
  return typeof value === 'boolean' ? value : null
}

function textValue(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

function secondsValue(value: number | null): string | null {
  if (value === null) {
    return null
  }

  return `${Number.isInteger(value) ? value : value.toFixed(1)}s`
}

function numberText(value: number | null): string | null {
  return value === null ? null : String(value)
}

function booleanText(value: boolean | null): string | null {
  return value === null ? null : (value ? 'Yes' : 'No')
}

function revokeVideoUrl(): void {
  if (videoUrl.value && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
    URL.revokeObjectURL(videoUrl.value)
  }

  videoUrl.value = null
}

function playableVideoBlob(blob: Blob): Blob {
  const type = blob.type.toLowerCase()

  if (type.includes('text/html')) {
    throw new Error('Private media request returned HTML instead of a video file.')
  }

  return type === 'video/mp4' ? blob : new Blob([blob], { type: 'video/mp4' })
}

async function loadVideoPreview(): Promise<void> {
  revokeVideoUrl()
  videoError.value = ''

  if (!streamUrl.value || !hasRenderedVideo.value) {
    return
  }

  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
    videoError.value = 'Could not load the secure video preview.'
    return
  }

  isPreviewLoading.value = true

  try {
    const blob = playableVideoBlob(await fetchMediaBlob(streamUrl.value))
    videoUrl.value = URL.createObjectURL(blob)
  } catch {
    videoError.value = 'Could not load the secure video preview.'
  } finally {
    isPreviewLoading.value = false
  }
}

async function downloadVideo(): Promise<void> {
  if (!downloadUrl.value) {
    return
  }

  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
    downloadError.value = 'Could not download the video file.'
    return
  }

  isDownloadingVideo.value = true
  downloadError.value = ''

  try {
    const blob = playableVideoBlob(await fetchMediaBlob(downloadUrl.value))
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = downloadFilename.value
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(objectUrl)
  } catch {
    downloadError.value = 'Could not download the video file.'
  } finally {
    isDownloadingVideo.value = false
  }
}

watch(streamUrl, () => {
  void loadVideoPreview()
}, { immediate: true })

onBeforeUnmount(() => {
  revokeVideoUrl()
})
</script>

<template>
  <article data-testid="auto-video-ad-result" class="space-y-5">
    <section v-if="hasRenderedVideo" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-ad-final-preview">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Final video</p>
          <h2 class="mt-2 text-2xl font-bold">Rendered auto video ad</h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-neutral-400">
            Your paid social ad MP4 is stored privately and delivered through secure preview/download routes.
          </p>
        </div>
        <button
          v-if="downloadUrl"
          type="button"
          :disabled="isDownloadingVideo"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-wait disabled:opacity-60"
          data-testid="auto-video-ad-download"
          @click="downloadVideo"
        >
          {{ isDownloadingVideo ? 'Preparing download...' : 'Download video' }}
        </button>
      </div>
      <p v-if="isPreviewLoading" class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4 text-sm text-neutral-400" data-testid="auto-video-ad-preview-loading">
        Loading secure video preview...
      </p>
      <p v-else-if="videoError" role="alert" class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-100" data-testid="auto-video-ad-preview-error">
        {{ videoError }}
      </p>
      <video
        v-else-if="videoUrl"
        class="mt-5 aspect-[9/16] w-full max-h-[70vh] rounded-[1.5rem] border border-neutral-800 bg-black object-contain"
        :src="videoUrl"
        controls
        playsinline
        preload="metadata"
        data-testid="auto-video-ad-player"
      />
      <p v-if="downloadError" role="alert" class="mt-3 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100" data-testid="auto-video-ad-download-error">
        {{ downloadError }}
      </p>
    </section>

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

      <div v-if="!hasRenderedVideo" class="mt-4 rounded-2xl border border-amber-900/60 bg-amber-950/20 p-4">
        <p class="text-sm font-semibold text-amber-100">Final MP4 rendering coming next</p>
        <p class="mt-2 text-sm leading-6 text-amber-100/80">
          This is a mock/planning result. Enable the Shotstack beta provider to render a final MP4.
        </p>
      </div>
    </section>

    <section v-if="renderSummaryItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-ad-render-summary">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Render summary</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="item in renderSummaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ item.label }}</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ item.value }}</p>
        </article>
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

    <section v-if="timelineItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-ad-timeline-clips">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Timeline / clips used</p>
      <div class="mt-4 space-y-3">
        <article v-for="item in timelineItems" :key="`${item.order}-${timelineRange(item)}`" class="rounded-2xl bg-neutral-950/70 p-4">
          <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-emerald-300">
            <span>Clip {{ item.order }}</span>
            <span>{{ timelineRange(item) }}</span>
            <span v-if="item.source_range" class="rounded-full border border-neutral-700 px-2 py-0.5 text-neutral-400">Source {{ item.source_range }}</span>
            <span class="rounded-full border border-neutral-700 px-2 py-0.5 text-neutral-400">{{ formatLabel(item.source) }}</span>
          </div>
          <p class="mt-2 text-sm leading-6 text-neutral-100">{{ item.instruction }}</p>
          <p v-if="item.text_overlay" class="mt-2 text-xs leading-5 text-neutral-500">Overlay: {{ item.text_overlay }}</p>
        </article>
      </div>
    </section>

    <section v-if="captionSchedule.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-ad-captions-used">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions used</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span v-for="caption in captionSchedule" :key="`${caption.type}-${caption.start}-${caption.text}`" class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-200">
          <span v-if="captionRange(caption)" class="text-neutral-500">{{ captionRange(caption) }} · </span>{{ caption.text }}
        </span>
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
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ hasRenderedVideo ? 'Render plan' : 'Future render plan' }}</p>
        <div class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
          <p><span class="font-semibold text-neutral-100">Renderer:</span> {{ result.render_plan.renderer }}</p>
          <p><span class="font-semibold text-neutral-100">Timeline:</span> {{ result.render_plan.timeline_strategy }}</p>
          <p><span class="font-semibold text-neutral-100">Overlay:</span> {{ result.render_plan.overlay_style }}</p>
          <p><span class="font-semibold text-neutral-100">End card:</span> {{ result.render_plan.end_card_strategy }}</p>
        </div>
        <div v-if="missingRenderItems.length" class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">Still needed</p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-neutral-400">
            <li v-for="item in missingRenderItems" :key="item">{{ item }}</li>
          </ul>
        </div>
      </div>
    </section>

    <section v-if="providerSummaryItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-ad-provider-summary">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Provider summary</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <p v-for="item in providerSummaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          <span class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ item.label }}</span>
          {{ item.value }}
        </p>
      </div>
    </section>
  </article>
</template>