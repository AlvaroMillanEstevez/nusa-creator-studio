<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { fetchMediaBlob } from '@/services/media'
import type {
  AutoVideoEditorCaptionScheduleItem,
  AutoVideoEditorClip,
  AutoVideoEditorResult,
  AutoVideoEditorTimelineItem,
} from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  result: AutoVideoEditorResult
  generationId?: number | string | null
}>()

interface TimelineClipViewItem {
  key: string
  label: string
  timelineRange: string | null
  sourceRange: string | null
  instruction: string
  overlay: string | null
  sourceLabel: string | null
}

const result = computed(() => props.result)
const streamUrl = computed(() => result.value.final_video_stream_url ?? null)
const downloadUrl = computed(() => result.value.final_video_download_url ?? null)
const hasFinalVideo = computed(() => Boolean(streamUrl.value))
const videoUrl = ref<string | null>(null)
const isPreviewLoading = ref(false)
const isDownloadingVideo = ref(false)
const videoError = ref('')
const downloadError = ref('')
const downloadFilename = computed(() => {
  const id = props.generationId ?? result.value.final_video_asset_id ?? 'video'

  return `auto-video-editor-${String(id)}.mp4`
})
const timelineSummary = computed(() => result.value.timeline_summary ?? null)
const renderSettings = computed(() => result.value.render_settings ?? null)
const timelineSummaryItems = computed(() => {
  const summary = timelineSummary.value
  const settings = renderSettings.value

  if (!summary && !settings) {
    return []
  }

  return [
    { label: 'Final duration', value: secondsValue(firstNumber(summary?.final_duration_seconds, settings?.final_duration_seconds, settings?.actual_duration_seconds, summary?.duration_seconds)) },
    { label: 'Voiceover duration', value: secondsValue(firstNumber(summary?.voiceover_duration_seconds, settings?.voiceover_duration_seconds)) },
    { label: 'Duration adjusted for voiceover', value: booleanValue(firstBoolean(summary?.duration_adjusted_for_voiceover, settings?.duration_adjusted_for_voiceover)) },
    { label: 'Requested target', value: secondsValue(firstNumber(summary?.requested_target_duration_seconds, settings?.requested_target_duration_seconds, settings?.target_duration_seconds)) },
    { label: 'Effective target', value: secondsValue(firstNumber(summary?.effective_target_duration_seconds, settings?.effective_target_duration_seconds, settings?.target_duration_seconds)) },
    { label: 'Duration sync', value: summary?.duration_sync_strategy ? formatLabel(summary.duration_sync_strategy) : null },
    { label: 'Clips used', value: numberValue(summary?.selected_clip_count) },
    { label: 'Captions used', value: numberValue(firstNumber(summary?.caption_count, settings?.caption_count, result.value.captions_plan.caption_count)) },
    { label: 'CTA used', value: summary?.cta_used ?? null },
  ].filter((item): item is { label: string, value: string } => item.value !== null && item.value !== '')
})
const timelineClipItems = computed<TimelineClipViewItem[]>(() => {
  if (result.value.timeline.length > 0) {
    return result.value.timeline.map(timelineItemToViewItem)
  }

  return result.value.clip_sequence.map(clipSequenceItemToViewItem)
})
const postVoiceoverSummary = computed(() => {
  const summary = timelineSummary.value
  const settings = renderSettings.value
  const finalDuration = firstNumber(summary?.final_duration_seconds, settings?.final_duration_seconds, settings?.actual_duration_seconds, summary?.duration_seconds)
  const voiceoverDuration = firstNumber(summary?.voiceover_duration_seconds, settings?.voiceover_duration_seconds)

  if (finalDuration !== null && voiceoverDuration !== null && finalDuration > voiceoverDuration + 0.5) {
    return 'Extra time after voiceover used for product showcase / CTA visuals.'
  }

  return null
})
const repeatedClipWarning = computed(() => {
  const count = firstNumber(timelineSummary.value?.repeated_clip_count, renderSettings.value?.repeated_clip_count)

  if (count !== null && count > 0) {
    return `Repeated ${count} clip${count === 1 ? '' : 's'} because there were not enough unique source segments for the target duration.`
  }

  return null
})
const captionsUsed = computed(() => {
  const schedule = result.value.captions_plan.caption_schedule ?? timelineSummary.value?.caption_schedule ?? renderSettings.value?.caption_schedule ?? []
  const seen = new Set<string>()

  if (schedule.length > 0) {
    return schedule.filter((caption) => {
      const key = caption.text.trim().toLowerCase()

      if (key === '' || seen.has(key)) {
        return false
      }

      seen.add(key)
      return true
    })
  }

  return result.value.captions_plan.sample_captions
    .map((caption): AutoVideoEditorCaptionScheduleItem => ({ start: 0, length: 0, text: caption, type: 'caption' }))
    .filter((caption) => {
      const key = caption.text.trim().toLowerCase()

      if (key === '' || seen.has(key)) {
        return false
      }

      seen.add(key)
      return true
    })
})
const providerSummaryItems = computed(() => {
  const settings = renderSettings.value
  const summary = timelineSummary.value

  return [
    { label: 'Provider', value: formattedValue(settings?.provider ?? result.value.final_video_placeholder.provider) },
    { label: 'Render status', value: formattedValue(result.value.final_video_placeholder.status) },
    { label: 'Final video asset', value: result.value.final_video_asset_id ? String(result.value.final_video_asset_id) : null },
    { label: 'Resolution', value: textValue(settings?.resolution) },
    { label: 'Aspect ratio', value: textValue(settings?.aspect_ratio) },
    { label: 'Caption style', value: formattedValue(settings?.caption_style) },
    { label: 'Caption strategy', value: formattedValue(settings?.caption_visual_strategy ?? summary?.caption_visual_strategy) },
    { label: 'Source duration', value: secondsValue(firstNumber(summary?.source_duration_seconds, settings?.source_duration_seconds)) },
    { label: 'Diverse source segments', value: booleanValue(firstBoolean(summary?.diverse_source_segments_used, settings?.diverse_source_segments_used)) },
    { label: 'Repeated clips', value: numberValue(firstNumber(summary?.repeated_clip_count, settings?.repeated_clip_count)) },
    { label: 'Invalid ranges discarded', value: numberValue(firstNumber(summary?.invalid_clip_ranges_discarded, settings?.invalid_clip_ranges_discarded)) },
  ].filter((item): item is { label: string, value: string } => item.value !== null && item.value !== '')
})

function firstNumber(...values: Array<number | null | undefined>): number | null {
  return values.find((value) => typeof value === 'number' && !Number.isNaN(value)) ?? null
}

function firstBoolean(...values: Array<boolean | null | undefined>): boolean | null {
  return values.find((value) => typeof value === 'boolean') ?? null
}

function secondsValue(value: number | null | undefined): string | null {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return null
  }

  return `${Number.isInteger(value) ? value : value.toFixed(1)}s`
}

function numberValue(value: number | null | undefined): string | null {
  return typeof value === 'number' && !Number.isNaN(value) ? String(value) : null
}

function booleanValue(value: boolean | null | undefined): string | null {
  return typeof value === 'boolean' ? (value ? 'Yes' : 'No') : null
}

function textValue(value: string | null | undefined): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

function formattedValue(value: string | null | undefined): string | null {
  const text = textValue(value)

  return text === null ? null : formatLabel(text)
}

function formatRange(start: number | null | undefined, end: number | null | undefined): string | null {
  if (typeof start !== 'number' || typeof end !== 'number' || Number.isNaN(start) || Number.isNaN(end) || end <= start) {
    return null
  }

  return `${secondsValue(start)}-${secondsValue(end)}`
}

function timelineItemToViewItem(item: AutoVideoEditorTimelineItem, index: number): TimelineClipViewItem {
  const timelineStart = firstNumber(item.timeline_start_seconds, item.start_seconds)
  const timelineEnd = firstNumber(item.timeline_end_seconds, timelineStart !== null && typeof item.duration_seconds === 'number' ? timelineStart + item.duration_seconds : null)

  return {
    key: `${index}-${item.timeline_range ?? item.timestamp}-${item.layer}`,
    label: `Clip ${index + 1}`,
    timelineRange: item.timeline_range ?? item.timestamp ?? formatRange(timelineStart, timelineEnd),
    sourceRange: item.source_range ?? formatRange(item.source_start_seconds, item.source_end_seconds),
    instruction: item.instruction,
    overlay: item.text_overlay || null,
    sourceLabel: item.source ? formatLabel(item.source) : null,
  }
}

function clipSequenceItemToViewItem(item: AutoVideoEditorClip, index: number): TimelineClipViewItem {
  return {
    key: `${index}-${item.clip}`,
    label: item.clip,
    timelineRange: item.timeline_range ?? item.timestamp ?? formatRange(item.timeline_start_seconds, item.timeline_end_seconds),
    sourceRange: item.source_range ?? formatRange(item.source_start_seconds, item.source_end_seconds),
    instruction: item.use,
    overlay: item.text_overlay ?? null,
    sourceLabel: formatLabel(item.source),
  }
}

function captionRange(caption: AutoVideoEditorCaptionScheduleItem): string | null {
  if (caption.length <= 0) {
    return null
  }

  return formatRange(caption.start, caption.start + caption.length)
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

  if (!streamUrl.value) {
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
  <div class="space-y-5" data-testid="auto-video-editor-result">
    <section v-if="hasFinalVideo" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-final-preview">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Final video</p>
          <h3 class="mt-2 text-xl font-semibold">Rendered vertical video</h3>
          <p class="mt-2 text-sm leading-6 text-neutral-400">{{ result.final_video_placeholder.message }}</p>
        </div>
        <button
          v-if="downloadUrl"
          type="button"
          :disabled="isDownloadingVideo"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-wait disabled:opacity-60"
          data-testid="auto-video-download"
          @click="downloadVideo"
        >
          {{ isDownloadingVideo ? 'Preparing download...' : 'Download video' }}
        </button>
      </div>
      <p v-if="isPreviewLoading" class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4 text-sm text-neutral-400" data-testid="auto-video-preview-loading">
        Loading secure video preview...
      </p>
      <p v-else-if="videoError" role="alert" class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-100" data-testid="auto-video-preview-error">
        {{ videoError }}
      </p>
      <video
        v-else-if="videoUrl"
        class="mt-5 aspect-[9/16] w-full max-h-[70vh] rounded-[1.5rem] border border-neutral-800 bg-black object-contain"
        :src="videoUrl"
        controls
        playsinline
        preload="metadata"
        data-testid="auto-video-player"
      />
      <p v-if="downloadError" role="alert" class="mt-3 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100" data-testid="auto-video-download-error">
        {{ downloadError }}
      </p>
    </section>

    <section v-if="timelineSummaryItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-timeline-summary">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Render summary</p>
      <h3 class="mt-2 text-xl font-semibold">Voiceover-aware render plan</h3>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <article v-for="item in timelineSummaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ item.label }}</p>
          <p class="mt-1 text-sm font-semibold text-neutral-100">{{ item.value }}</p>
        </article>
      </div>
      <p v-if="postVoiceoverSummary" class="mt-4 rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-3 text-sm leading-6 text-emerald-100/80" data-testid="auto-video-post-voiceover-summary">
        {{ postVoiceoverSummary }}
      </p>
      <p v-if="repeatedClipWarning" class="mt-3 rounded-2xl border border-amber-900/50 bg-amber-950/20 p-3 text-sm leading-6 text-amber-100/80" data-testid="auto-video-repeat-warning">
        {{ repeatedClipWarning }}
      </p>
      <ul v-if="timelineSummary?.timeline_quality_warnings?.length" class="mt-4 space-y-2 text-sm leading-6 text-amber-100/80">
        <li v-for="warning in timelineSummary.timeline_quality_warnings" :key="warning">• {{ warning }}</li>
      </ul>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Auto Video Editor</p>
      <h2 class="mt-3 text-2xl font-bold">Edit-ready vertical video plan</h2>
      <p class="mt-4 text-sm leading-7 text-neutral-200">{{ result.source_summary }}</p>
      <p v-if="!hasFinalVideo" class="mt-4 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-sm leading-6 text-sky-100/80">
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
        <article class="rounded-2xl border border-emerald-800 bg-emerald-950/40 p-3">
          <p class="text-xl font-bold text-emerald-300">{{ result.cost_breakdown.total_credits }}</p>
          <p class="mt-1 text-xs text-neutral-500">Total internal credits</p>
        </article>
      </div>
    </section>

    <section v-if="timelineClipItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-timeline-clips">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Timeline / clips used</p>
      <h3 class="mt-2 text-xl font-semibold">Final edit sequence</h3>
      <div class="mt-4 space-y-3">
        <article v-for="item in timelineClipItems" :key="item.key" class="rounded-2xl bg-neutral-950/70 p-4">
          <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-emerald-300">
            <span>{{ item.label }}</span>
            <span v-if="item.timelineRange">{{ item.timelineRange }}</span>
            <span v-if="item.sourceRange" class="rounded-full border border-neutral-700 px-2 py-0.5 text-neutral-400">Source {{ item.sourceRange }}</span>
            <span v-if="item.sourceLabel" class="rounded-full border border-neutral-700 px-2 py-0.5 text-neutral-400">{{ item.sourceLabel }}</span>
          </div>
          <p class="mt-2 text-sm leading-6 text-neutral-100">{{ item.instruction }}</p>
          <p v-if="item.overlay" class="mt-2 text-xs leading-5 text-neutral-500">Overlay: {{ item.overlay }}</p>
        </article>
      </div>
    </section>

    <section v-if="captionsUsed.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-captions-used">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Captions used</p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span v-for="caption in captionsUsed" :key="`${caption.type}-${caption.start}-${caption.text}`" class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-200">
          <span v-if="captionRange(caption)" class="text-neutral-500">{{ captionRange(caption) }} · </span>{{ caption.text }}
        </span>
      </div>
      <p v-if="timelineSummary?.repeated_captions_removed" class="mt-3 text-xs leading-5 text-neutral-500">
        Removed {{ timelineSummary.repeated_captions_removed }} repeated caption{{ timelineSummary.repeated_captions_removed === 1 ? '' : 's' }} from the render schedule.
      </p>
    </section>

    <section v-if="result.voiceover_script" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Voiceover script</p>
      <p class="mt-4 whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-100">
        {{ result.voiceover_script }}
      </p>
    </section>

    <section v-if="providerSummaryItems.length" class="nusa-card-premium rounded-[2rem] p-5" data-testid="auto-video-provider-summary">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Provider summary</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <p v-for="item in providerSummaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          <span class="block text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ item.label }}</span>
          {{ item.value }}
        </p>
      </div>
    </section>

    <section v-if="result.compliance_notes.length || result.usage_notes.length" class="grid gap-5 lg:grid-cols-2">
      <div v-if="result.compliance_notes.length" class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-amber-100/80">
          <li v-for="item in result.compliance_notes" :key="item">• {{ item }}</li>
        </ul>
      </div>
      <div v-if="result.usage_notes.length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Usage notes</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in result.usage_notes" :key="item">• {{ item }}</li>
        </ul>
      </div>
    </section>
  </div>
</template>
