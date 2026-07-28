
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import { fetchMediaBlob } from '@/services/media'
import type { MotionAiVideoScene, MotionVideoResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  result: MotionVideoResult
  projectId?: number | string | null
  generationId?: number | string | null
}>()

const result = computed(() => props.result)
const finalVideoAssetId = computed(() => {
  const value = result.value.final_video_asset_id ?? result.value.rendered_video_asset?.id ?? result.value.metadata.final_video_asset_id ?? result.value.provider_metadata?.final_video_asset_id ?? null

  return typeof value === 'number' && Number.isFinite(value) ? value : null
})
const projectRoute = computed(() => {
  return props.projectId === null || props.projectId === undefined ? '/dashboard' : `/projects/${String(props.projectId)}`
})
const analyzeFootageRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || finalVideoAssetId.value === null) {
    return null
  }

  const query: Record<string, string> = {
    sourceVideoAssetId: String(finalVideoAssetId.value),
    returnWorkflow: 'motion_ai_video',
  }

  if (props.generationId !== null && props.generationId !== undefined) {
    query.fromGenerationId = String(props.generationId)
  }

  return {
    name: 'project-video-to-campaign-action',
    params: { projectId: String(props.projectId) },
    query,
  }
})
const manualEditRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || finalVideoAssetId.value === null) {
    return null
  }

  const query: Record<string, string> = {
    editMode: 'manual_edit',
    sourceVideoAssetId: String(finalVideoAssetId.value),
  }

  if (props.generationId !== null && props.generationId !== undefined) {
    query.sourceGenerationId = String(props.generationId)
  }

  return {
    name: 'project-auto-video-editor-action',
    params: { projectId: String(props.projectId) },
    query,
  }
})
const generateAnotherRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined) {
    return '/dashboard'
  }

  return {
    name: 'project-motion-ai-video-action',
    params: { projectId: String(props.projectId) },
  }
})
const streamUrl = computed(() => result.value.final_video_stream_url ?? result.value.rendered_video_asset?.stream_url ?? null)
const downloadUrl = computed(() => result.value.final_video_download_url ?? result.value.rendered_video_asset?.download_url ?? null)
const hasFinalVideo = computed(() => Boolean(streamUrl.value))
const providerName = computed(() => String(result.value.provider_metadata?.provider ?? result.value.metadata.provider ?? result.value.provider_readiness.provider ?? 'mock'))
const isRealRender = computed(() => hasFinalVideo.value || result.value.metadata.mode === 'real' || providerName.value === 'google_veo')
const videoUrl = ref<string | null>(null)
const isPreviewLoading = ref(false)
const isDownloadingVideo = ref(false)
const videoError = ref('')
const downloadError = ref('')

const effectiveSourceMode = computed(() =>
  stringMeta('effective_request_mode') ??
  stringMeta('source_mode') ??
  result.value.motion_summary.source_mode ??
  result.value.motion_summary.source_type,
)
const captionsPrepared = computed(() =>
  boolMeta('captions_requested') ?? (result.value.script_plan.caption_lines.length > 0),
)
const voiceoverTimingUsed = computed(() =>
  result.value.asset_plan.voiceover_generation_id !== null ||
  boolMeta('voiceover_context_used') === true ||
  boolMeta('voiceover_requested') === true,
)
const captionLines = computed(() => {
  if (!captionsPrepared.value) {
    return []
  }

  return uniqueStrings(result.value.script_plan.caption_lines)
})
const primaryCtaSource = computed(() => stringMeta('primary_cta_source') ?? 'default_suggestion')
const primaryCtaLabel = computed(() => primaryCtaSource.value === 'user_provided' ? 'CTA' : 'Suggested CTA')
const sceneItems = computed(() =>
  result.value.scene_plan.map((scene) => ({
    ...scene,
    caption: captionsPrepared.value ? scene.overlay_or_caption : '',
  })),
)
const userExclusions = computed(() => stringMeta('user_negative_prompt') ?? stringMeta('user_exclusions'))
const systemConstraintsApplied = computed(() => Boolean(stringMeta('system_visual_constraints') ?? stringMeta('system_negative_constraints')))
const summaryItems = computed(() => [
  { label: 'Source', value: sourceLabel(effectiveSourceMode.value) },
  { label: 'Duration', value: `${result.value.motion_summary.duration}s` },
  { label: 'Aspect ratio', value: formatLabel(result.value.motion_summary.aspect_ratio) },
  { label: 'Visual style', value: formatLabel(result.value.motion_summary.visual_style) },
  { label: 'Camera movement', value: formatLabel(result.value.motion_summary.motion_style) },
  { label: 'Captions prepared', value: captionsPrepared.value ? 'Yes, for post-production' : 'No' },
  { label: 'Voiceover timing used', value: voiceoverTimingUsed.value ? 'Yes, pacing only' : 'No' },
])
const diagnosticsItems = computed(() => {
  const items = [
    { label: 'Provider', value: formatLabel(providerName.value) },
    { label: 'Model', value: stringMeta('model') },
    { label: 'Render status', value: formatLabel(stringMeta('provider_render_status') ?? stringMeta('response_status') ?? (hasFinalVideo.value ? 'done' : 'plan_only')) },
    { label: 'Effective request mode', value: formatLabel(stringMeta('effective_request_mode') ?? stringMeta('provider_request_shape') ?? effectiveSourceMode.value) },
    { label: 'Configured request mode', value: formatLabel(stringMeta('configured_request_mode') ?? stringMeta('request_mode') ?? undefined) },
    { label: 'Final video asset', value: result.value.final_video_asset_id ? String(result.value.final_video_asset_id) : null },
  ]

  return items.filter((item): item is { label: string; value: string } => Boolean(item.value))
})

function stringMeta(key: string): string | null {
  const fromMetadata = result.value.metadata[key]
  const fromProvider = result.value.provider_metadata?.[key]
  const value = fromMetadata ?? fromProvider

  return typeof value === 'string' && value.trim() !== '' ? value : null
}

function boolMeta(key: string): boolean | null {
  const fromMetadata = result.value.metadata[key]
  const fromProvider = result.value.provider_metadata?.[key]
  const value = fromMetadata ?? fromProvider

  return typeof value === 'boolean' ? value : null
}

function uniqueStrings(values: string[]): string[] {
  return Array.from(new Set(values.map((value) => value.trim()).filter(Boolean)))
}

function sourceLabel(value: string | null | undefined): string {
  if (value === 'campaign_assisted') {
    return 'Saved campaign'
  }

  if (value === 'image_first_frame') {
    return 'Product image used as starting frame'
  }

  if (value === 'image_context') {
    return 'Product image used as workspace context'
  }

  return 'Text description'
}

function sceneRange(scene: MotionAiVideoScene): string {
  return `${scene.start_seconds}–${scene.end_seconds}s`
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
    videoError.value = 'Could not load the secure motion video preview.'
    return
  }

  isPreviewLoading.value = true

  try {
    const blob = playableVideoBlob(await fetchMediaBlob(streamUrl.value))
    videoUrl.value = URL.createObjectURL(blob)
  } catch {
    videoError.value = 'Could not load the secure motion video preview.'
  } finally {
    isPreviewLoading.value = false
  }
}

async function downloadVideo(): Promise<void> {
  downloadError.value = ''

  if (!downloadUrl.value) {
    return
  }

  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
    downloadError.value = 'Could not prepare the secure video download.'
    return
  }

  isDownloadingVideo.value = true

  try {
    const blob = playableVideoBlob(await fetchMediaBlob(downloadUrl.value))
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = url
    link.download = result.value.rendered_video_asset?.original_name ?? `motion-ai-video-${result.value.final_video_asset_id ?? 'clip'}.mp4`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'Could not prepare the secure video download.'
  } finally {
    isDownloadingVideo.value = false
  }
}

watch(streamUrl, () => {
  void loadVideoPreview()
})

onMounted(() => {
  void loadVideoPreview()
})

onBeforeUnmount(() => {
  revokeVideoUrl()
})
</script>

<template>
  <div class="space-y-5" data-testid="motion-ai-video-result">
    <section v-if="hasFinalVideo" class="nusa-card-premium rounded-[2rem] p-5" data-testid="motion-final-preview">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Generated motion clip</p>
          <h2 class="mt-2 text-xl font-semibold">MP4 preview ready</h2>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            This is the base visual footage. Captions, CTA overlays and voiceover are added later during post-production.
          </p>
        </div>
        <button
          v-if="downloadUrl"
          type="button"
          :disabled="isDownloadingVideo"
          class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-wait disabled:opacity-60"
          data-testid="motion-video-download"
          @click="downloadVideo"
        >
          {{ isDownloadingVideo ? 'Preparing download...' : 'Download MP4' }}
        </button>
      </div>

      <p v-if="isPreviewLoading" class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4 text-sm text-neutral-400" data-testid="motion-video-preview-loading">
        Loading secure motion video preview...
      </p>
      <p v-else-if="videoError" role="alert" class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-100" data-testid="motion-video-preview-error">
        {{ videoError }}
      </p>
      <video
        v-else-if="videoUrl"
        class="mt-5 aspect-[9/16] w-full max-h-[70vh] rounded-[1.5rem] border border-neutral-800 bg-black object-contain"
        :src="videoUrl"
        controls
        playsinline
        preload="metadata"
        data-testid="motion-video-player"
      />
      <p v-if="downloadError" role="alert" class="mt-3 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100" data-testid="motion-video-download-error">
        {{ downloadError }}
      </p>
    </section>

    <section v-else class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Render plan</p>
      <h2 class="mt-2 text-xl font-semibold">Video preview is not available for this generation</h2>
      <p class="mt-2 text-sm leading-6 text-neutral-400">
        This historical or mock result contains a motion plan, not a stored MP4 preview.
      </p>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5" data-testid="motion-next-actions">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Next workflow</p>
      <h2 class="mt-2 text-xl font-semibold">Reuse this generated MP4</h2>
      <p class="mt-2 text-sm leading-6 text-neutral-400">
        The generated video is saved as a private project media asset. You can analyze it like uploaded footage, edit it manually, or create another version. None of these actions starts a provider call automatically.
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <RouterLink
          v-if="analyzeFootageRoute"
          :to="analyzeFootageRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-center text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          data-testid="motion-analyze-video"
        >
          Analyze this video with AI
        </RouterLink>
        <RouterLink
          v-if="manualEditRoute"
          :to="manualEditRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-sky-800 bg-sky-950/30 px-4 text-center text-sm font-semibold text-sky-100 transition hover:border-sky-500 hover:bg-sky-900/40"
          data-testid="motion-edit-video-manually"
        >
          Edit video manually
        </RouterLink>
        <RouterLink
          :to="generateAnotherRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-center text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          data-testid="motion-generate-another"
        >
          Generate another version
        </RouterLink>
        <RouterLink
          :to="projectRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-center text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          data-testid="motion-return-project"
        >
          Return to project
        </RouterLink>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Motion AI Video</p>
          <h2 class="mt-3 text-xl font-bold">{{ result.motion_summary.creative_angle }}</h2>
        </div>
        <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="isRealRender ? 'border-emerald-800 bg-emerald-950/60 text-emerald-200' : 'border-amber-800 bg-amber-950/60 text-amber-200'">
          {{ isRealRender ? 'Video footage ready' : 'Plan only' }}
        </span>
      </div>

      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in summaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">{{ item.label }}</p>
          <p class="mt-1 text-sm font-semibold">{{ item.value }}</p>
        </div>
      </div>

      <p class="mt-4 text-sm leading-7 text-neutral-300">
        Nusa generated or planned visual-first product footage. Text overlays, exact captions, CTA cards and final narration are treated as post-production work, not guaranteed pixels inside the Veo clip.
      </p>
    </section>

    <section v-if="sceneItems.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Timeline notes</p>
      <h2 class="mt-2 text-xl font-semibold">Visual sequence</h2>
      <div class="mt-4 space-y-3">
        <article v-for="scene in sceneItems" :key="scene.scene_number" class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4">
          <p class="text-xs font-semibold text-emerald-300">{{ sceneRange(scene) }} · Scene {{ scene.scene_number }}</p>
          <p class="mt-3 text-sm leading-6 text-neutral-200">{{ scene.visual_direction }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">Product action: {{ scene.product_action }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">Camera: {{ scene.camera_direction }}</p>
          <p v-if="scene.caption" class="mt-2 text-xs font-semibold text-neutral-300">
            Suggested post-production caption: {{ scene.caption }}
          </p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div v-if="voiceoverTimingUsed" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Voiceover timing</p>
        <h2 class="mt-2 text-xl font-semibold">Pacing guidance</h2>
        <p class="mt-4 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
          {{ result.script_plan.voiceover_ready_script }}
        </p>
        <p class="mt-3 text-xs leading-5 text-neutral-500">
          This is pacing guidance only. The final audio is added in post-production.
        </p>
      </div>

      <div v-if="captionLines.length || result.script_plan.CTA_line" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Post-production text</p>
        <h2 class="mt-2 text-xl font-semibold">Caption and CTA suggestions</h2>
        <div v-if="captionLines.length" class="mt-4">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">Suggested captions for post-production</p>
          <ul class="mt-3 space-y-2 text-sm leading-6 text-neutral-300">
            <li v-for="caption in captionLines" :key="caption">• {{ caption }}</li>
          </ul>
        </div>
        <p v-if="result.script_plan.CTA_line" class="mt-4 rounded-2xl border border-emerald-900/60 bg-emerald-950/20 p-3 text-sm font-semibold text-emerald-100">
          {{ primaryCtaLabel }}: {{ result.script_plan.CTA_line }}
        </p>
      </div>
    </section>

    <section v-if="userExclusions || systemConstraintsApplied" class="rounded-3xl border border-neutral-800 bg-neutral-950/50 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">Safety and visual integrity</p>
      <p v-if="userExclusions" class="mt-3 text-sm leading-6 text-neutral-300">
        User things to avoid: {{ userExclusions }}
      </p>
      <p v-if="systemConstraintsApplied" class="mt-3 text-sm leading-6 text-neutral-500">
        Nusa also applied internal visual-integrity constraints. These are not treated as user-entered instructions.
      </p>
    </section>

    <section v-if="result.compliance_notes.length" class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Compliance notes</p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in result.compliance_notes" :key="item">• {{ item }}</li>
      </ul>
    </section>

    <details class="nusa-card-premium rounded-[2rem] p-5" data-testid="motion-internal-diagnostics">
      <summary class="cursor-pointer text-sm font-semibold text-neutral-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300">
        Internal diagnostics
      </summary>
      <p class="mt-3 text-xs leading-5 text-neutral-500">
        Safe provider metadata for debugging. No API keys, private paths, base64 or raw provider URLs are shown.
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <p v-for="item in diagnosticsItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          <span class="block text-xs text-neutral-500">{{ item.label }}</span>
          <span class="mt-1 block font-semibold text-neutral-100">{{ item.value }}</span>
        </p>
      </div>
    </details>
  </div>
</template>
