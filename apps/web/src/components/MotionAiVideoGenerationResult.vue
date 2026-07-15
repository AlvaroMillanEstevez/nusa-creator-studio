<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { fetchMediaBlob } from '@/services/media'
import type { MotionVideoResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{ result: MotionVideoResult }>()

const result = computed(() => props.result)
const streamUrl = computed(() => result.value.final_video_stream_url ?? result.value.rendered_video_asset?.stream_url ?? null)
const downloadUrl = computed(() => result.value.final_video_download_url ?? result.value.rendered_video_asset?.download_url ?? null)
const hasFinalVideo = computed(() => Boolean(streamUrl.value))
const isRealRender = computed(() => hasFinalVideo.value || result.value.metadata.mode === 'real' || result.value.provider_readiness.provider === 'google_veo')
const videoUrl = ref<string | null>(null)
const isPreviewLoading = ref(false)
const isDownloadingVideo = ref(false)
const videoError = ref('')
const downloadError = ref('')

const summaryItems = computed(() => [
  { label: 'Platform', value: formatLabel(result.value.motion_summary.platform) },
  { label: 'Duration', value: `${result.value.motion_summary.duration}s` },
  { label: 'Aspect ratio', value: formatLabel(result.value.motion_summary.aspect_ratio) },
  { label: 'Source', value: formatLabel(result.value.motion_summary.source_type) },
])

const providerSummaryItems = computed(() => {
  const metadata = result.value.provider_metadata

  return [
    { label: 'Provider', value: formatLabel(metadata?.provider ?? result.value.provider_readiness.provider) },
    { label: 'Model', value: metadata?.model ? String(metadata.model) : result.value.metadata.model ?? null },
    { label: 'Mode', value: formatLabel(metadata?.mode ?? result.value.metadata.mode ?? 'mock') },
    { label: 'Render status', value: formatLabel(metadata?.response_status ?? result.value.metadata.provider_render_status ?? (hasFinalVideo.value ? 'done' : 'plan_only')) },
    { label: 'Final video asset', value: result.value.final_video_asset_id ? String(result.value.final_video_asset_id) : null },
  ].filter((item): item is { label: string; value: string } => item.value !== null && item.value !== '')
})

function joinedFutureInputs(inputs: string[]): string {
  return inputs.map((input) => formatLabel(input)).join(', ')
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
          <h2 class="mt-2 text-xl font-semibold">Visual-first MP4 ready</h2>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            This is a base product motion clip. Add voiceover, captions or final polish in a later editing step.
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

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Motion AI Video
          </p>
          <h2 class="mt-3 text-xl font-bold">{{ result.motion_summary.creative_angle }}</h2>
        </div>
        <span
          class="rounded-full border px-3 py-1 text-xs font-semibold"
          :class="isRealRender ? 'border-emerald-800 bg-emerald-950/60 text-emerald-200' : 'border-amber-800 bg-amber-950/60 text-amber-200'"
        >
          {{ isRealRender ? 'Motion clip ready' : 'Render plan' }}
        </span>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in summaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">{{ item.label }}</p>
          <p class="mt-1 text-sm font-semibold">{{ item.value }}</p>
        </div>
      </div>
      <p class="mt-4 text-sm leading-7 text-neutral-300">
        <span v-if="isRealRender">
          Real provider mode generated a visual-first product motion clip. Native provider audio is not treated as the primary workflow output.
        </span>
        <span v-else>
          This mock beta result is a structured motion plan. It does not include a rendered MP4 yet.
        </span>
      </p>
    </section>

    <section class="grid gap-5 lg:grid-cols-[1fr_0.8fr]">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Creative direction
        </p>
        <p class="mt-4 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
          {{ result.generation_prompt.main_prompt }}
        </p>
        <dl class="mt-4 grid gap-3 text-sm text-neutral-300">
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Camera motion</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.camera_motion }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Lighting</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.lighting_style }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Negative prompt</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.negative_prompt }}</dd>
          </div>
        </dl>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Source context used
        </p>
        <dl class="mt-4 space-y-3 text-sm">
          <div>
            <dt class="text-neutral-500">Product image</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              Asset #{{ result.asset_plan.product_image_asset_id }}
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Scenario image</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.scenario_image_asset_id">
                Asset #{{ result.asset_plan.scenario_image_asset_id }}
              </span>
              <span v-else>Not selected</span>
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Campaign source</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.campaign_generation_id">
                Generation #{{ result.asset_plan.campaign_generation_id }}
              </span>
              <span v-else>Project brief</span>
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Voiceover source</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.voiceover_generation_id">
                Generation #{{ result.asset_plan.voiceover_generation_id }} used as visual pacing guidance
              </span>
              <span v-else>Not selected</span>
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <section v-if="result.scene_plan.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Scene plan timeline
      </p>
      <div class="mt-4 space-y-3">
        <article
          v-for="scene in result.scene_plan"
          :key="scene.scene_number"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
        >
          <p class="text-xs font-semibold text-emerald-300">
            {{ scene.start_seconds }}–{{ scene.end_seconds }}s · Scene {{ scene.scene_number }}
          </p>
          <p class="mt-3 text-sm leading-6 text-neutral-200">{{ scene.visual_direction }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            Product action: {{ scene.product_action }}
          </p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            Camera: {{ scene.camera_direction }}
          </p>
          <p class="mt-2 text-xs font-semibold text-neutral-300">
            Caption: {{ scene.overlay_or_caption }}
          </p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Script / voiceover guidance
        </p>
        <p class="mt-4 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
          {{ result.script_plan.voiceover_ready_script }}
        </p>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Captions / CTA
        </p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="caption in result.script_plan.caption_lines" :key="caption">
            • {{ caption }}
          </li>
        </ul>
        <p class="mt-4 rounded-2xl border border-emerald-900/60 bg-emerald-950/20 p-3 text-sm font-semibold text-emerald-100">
          CTA: {{ result.script_plan.CTA_line }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Provider summary
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <p v-for="item in providerSummaryItems" :key="item.label" class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          <span class="block text-xs text-neutral-500">{{ item.label }}</span>
          <span class="mt-1 block font-semibold text-neutral-100">{{ item.value }}</span>
        </p>
      </div>
      <p v-if="!isRealRender" class="mt-4 text-sm leading-7 text-neutral-400">
        Future provider inputs:
        {{ joinedFutureInputs(result.provider_readiness.future_provider_inputs) }}.
      </p>
      <p class="mt-4 rounded-2xl border border-amber-900/50 bg-amber-950/20 p-4 text-sm leading-6 text-amber-100/80">
        <span v-if="isRealRender">
          This clip is stored privately and served through Nusa secure media routes. Final voiceover/music/caption compositing is a later step.
        </span>
        <span v-else>
          Real motion video rendering is available only when the configured beta provider is enabled for the current user.
        </span>
      </p>
    </section>

    <section v-if="result.compliance_notes.length" class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">
        Compliance notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in result.compliance_notes" :key="item">• {{ item }}</li>
      </ul>
    </section>
  </div>
</template>