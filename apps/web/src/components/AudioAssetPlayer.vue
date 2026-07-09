<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { t } from '@/i18n'
import { fetchMediaBlob } from '@/services/media'
import type { MediaAsset } from '@/types/media'

const props = defineProps<{
  mediaAsset?: MediaAsset | null
  streamUrl?: string | null
  downloadUrl?: string | null
  title?: string | null
  compact?: boolean
}>()

const audioUrl = ref<string | null>(null)
const audioElement = ref<HTMLAudioElement | null>(null)
const isLoading = ref(false)
const isDownloading = ref(false)
const isPlaying = ref(false)
const error = ref('')

const streamUrl = computed(() => props.streamUrl ?? props.mediaAsset?.stream_url ?? null)
const downloadUrl = computed(() => props.downloadUrl ?? props.mediaAsset?.download_url ?? null)
const displayTitle = computed(
  () => props.title ?? props.mediaAsset?.original_name ?? t('voiceover.generatedAudio'),
)
const downloadName = computed(
  () => props.mediaAsset?.original_name ?? props.title ?? 'nusa-audio.mp3',
)

function revokeAudioUrl(): void {
  if (audioUrl.value && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
    URL.revokeObjectURL(audioUrl.value)
  }

  audioUrl.value = null
  isPlaying.value = false
}

async function loadAudio(): Promise<void> {
  revokeAudioUrl()
  error.value = ''

  if (!streamUrl.value) {
    error.value = t('media.audioUnavailable')
    return
  }

  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
    error.value = t('media.couldNotLoadAudio')
    return
  }

  isLoading.value = true

  try {
    const blob = await fetchMediaBlob(streamUrl.value)
    audioUrl.value = URL.createObjectURL(blob)
  } catch {
    error.value = t('media.couldNotLoadAudio')
  } finally {
    isLoading.value = false
  }
}

async function downloadAudio(): Promise<void> {
  if (!downloadUrl.value) {
    return
  }

  isDownloading.value = true
  error.value = ''

  try {
    const blob = await fetchMediaBlob(downloadUrl.value)
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = downloadName.value
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(objectUrl)
  } catch {
    error.value = t('media.couldNotLoadAudio')
  } finally {
    isDownloading.value = false
  }
}

async function togglePlayback(): Promise<void> {
  const audio = audioElement.value

  if (!audio) {
    return
  }

  if (audio.paused) {
    await audio.play()
    isPlaying.value = true
  } else {
    audio.pause()
    isPlaying.value = false
  }
}

function markPlaying(): void {
  isPlaying.value = true
}

function markStopped(): void {
  isPlaying.value = false
}

watch(streamUrl, () => {
  void loadAudio()
}, { immediate: true })

onBeforeUnmount(() => {
  revokeAudioUrl()
})
</script>

<template>
  <section
    data-testid="audio-asset-player"
    :data-compact="compact ? 'true' : undefined"
    :class="compact
      ? 'rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-3'
      : 'rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-4'
    "
  >
    <template v-if="compact">
      <div class="flex min-w-0 items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('campaignResult.compactAudioPreview') }}
          </p>
          <h3 class="mt-1 truncate text-sm font-semibold text-neutral-100">
            {{ displayTitle }}
          </h3>
        </div>

        <button
          type="button"
          :disabled="isLoading || !!error || !audioUrl"
          class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-sm font-black text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-800 disabled:text-neutral-500"
          :aria-label="isPlaying ? t('media.pauseAudio') : t('media.playAudio')"
          @click="togglePlayback"
        >
          {{ isPlaying ? '❚❚' : '▶' }}
        </button>
      </div>

      <p v-if="isLoading" class="mt-3 text-xs text-neutral-400" aria-live="polite">
        {{ t('media.loadingAudio') }}
      </p>
      <p v-else-if="error" role="alert" class="mt-3 text-xs text-rose-200">
        {{ error }}
      </p>

      <audio
        v-if="audioUrl"
        ref="audioElement"
        :src="audioUrl"
        class="hidden"
        data-testid="compact-audio-controls"
        @play="markPlaying"
        @pause="markStopped"
        @ended="markStopped"
      >
        {{ t('media.audioUnavailable') }}
      </audio>
    </template>

    <template v-else>
      <div class="flex min-w-0 items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('media.playAudio') }}
          </p>
          <h3 class="mt-2 break-words text-sm font-semibold text-neutral-100">
            {{ displayTitle }}
          </h3>
          <p class="mt-2 text-xs leading-5 text-neutral-500">
            {{ t('media.privateMediaFile') }}
          </p>
        </div>
        <span class="shrink-0 rounded-full border border-emerald-900/60 px-3 py-1 text-xs text-emerald-300">
          {{ t('media.audio') }}
        </span>
      </div>

      <p v-if="isLoading" class="mt-4 text-sm text-neutral-400" aria-live="polite">
        {{ t('media.loadingAudio') }}
      </p>

      <p v-else-if="error" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100">
        {{ error }}
      </p>

      <audio
        v-else-if="audioUrl"
        controls
        :src="audioUrl"
        class="mt-4 w-full"
        data-testid="audio-controls"
      >
        {{ t('media.audioUnavailable') }}
      </audio>

      <button
        v-if="downloadUrl"
        type="button"
        :disabled="isDownloading"
        class="mt-4 min-h-11 w-full rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-100 transition hover:border-emerald-700 hover:bg-emerald-950/30 disabled:cursor-wait disabled:opacity-60"
        @click="downloadAudio"
      >
        {{ isDownloading ? t('media.loadingAudio') : t('media.downloadAudio') }}
      </button>
    </template>
  </section>
</template>