<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import AudioAssetPlayer from '@/components/AudioAssetPlayer.vue'
import { ApiError } from '@/services/api'
import { deleteMediaAsset, listProjectMediaAssets, uploadMediaAsset } from '@/services/media'
import type { MediaAsset, MediaAssetUsageRole } from '@/types/media'
import type { ProjectType } from '@/types/project'
import { formatDate, formatLabel } from '@/utils/formatters'
import {
  MEDIA_ACCEPT,
  MEDIA_MAX_SIZE_BYTES,
  SUPPORTED_MEDIA_MESSAGE,
  validateMediaFile,
} from '@/utils/mediaValidation'

const props = defineProps<{
  projectId: number | string
  projectType?: ProjectType
}>()

const emit = defineEmits<{
  assetsChanged: [assets: MediaAsset[]]
}>()

const assets = ref<MediaAsset[]>([])
const selectedFile = ref<File | null>(null)
const selectedUsageRole = ref<MediaAssetUsageRole>(
  props.projectType === 'motion_ai_video' ? 'product_image' : 'model_reference',
)
const fileInput = ref<HTMLInputElement | null>(null)
const isLoading = ref(true)
const isUploading = ref(false)
const deletingId = ref<number | null>(null)
const loadError = ref('')
const uploadError = ref('')
const success = ref('')

const isFashionTryOnProject = computed(() => props.projectType === 'fashion_try_on')
const isMotionVideoProject = computed(() => props.projectType === 'motion_ai_video')
const usesAssetRoles = computed(() => isFashionTryOnProject.value || isMotionVideoProject.value)
const roleOptions = computed<Array<{ value: MediaAssetUsageRole; label: string }>>(() => {
  if (isMotionVideoProject.value) {
    return [
      { value: 'product_image', label: 'Product image' },
      { value: 'source_video', label: 'Source video' },
      { value: 'background_reference', label: 'Background reference' },
      { value: 'audio_track', label: 'Audio track' },
      { value: 'brand_reference', label: 'Brand reference' },
      { value: 'general', label: 'General' },
    ]
  }

  return [
    { value: 'model_reference', label: 'Model reference' },
    { value: 'garment_reference', label: 'Garment reference' },
    { value: 'general', label: 'General' },
  ]
})

function setAssets(nextAssets: MediaAsset[]): void {
  assets.value = nextAssets
  emit('assetsChanged', assets.value)
}

function friendlyError(error: unknown, fallback: string): string {
  if (error instanceof ApiError && error.status === 422) {
    return error.message === 'Please check the form and try again.' ? SUPPORTED_MEDIA_MESSAGE : error.message
  }

  return error instanceof ApiError ? error.message : fallback
}

async function loadAssets(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const response = await listProjectMediaAssets(props.projectId)
    setAssets(Array.isArray(response.data) ? response.data : [])
  } catch (error: unknown) {
    loadError.value = friendlyError(error, 'We could not load project assets.')
  } finally {
    isLoading.value = false
  }
}

function chooseFile(event: Event): void {
  uploadError.value = ''
  success.value = ''

  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) {
    selectedFile.value = null
    return
  }

  const validationError = validateMediaFile(file)

  if (validationError) {
    uploadError.value = validationError
    selectedFile.value = null
    input.value = ''
    return
  }

  selectedFile.value = file
}

async function uploadSelectedFile(): Promise<void> {
  if (!selectedFile.value) {
    uploadError.value = 'Choose a supported file before uploading.'
    return
  }

  isUploading.value = true
  uploadError.value = ''
  success.value = ''

  try {
    const asset = await uploadMediaAsset(
      selectedFile.value,
      props.projectId,
      usesAssetRoles.value ? selectedUsageRole.value : undefined,
    )
    setAssets([asset, ...assets.value.filter((item) => item.id !== asset.id)])
    selectedFile.value = null

    if (fileInput.value) {
      fileInput.value.value = ''
    }

    success.value = usesAssetRoles.value
      ? 'Asset uploaded with its project role.'
      : 'Asset uploaded and saved to this project.'
  } catch (error: unknown) {
    uploadError.value = friendlyError(error, 'We could not upload this asset. Please try again.')
  } finally {
    isUploading.value = false
  }
}

async function removeAsset(asset: MediaAsset): Promise<void> {
  deletingId.value = asset.id
  loadError.value = ''
  success.value = ''

  try {
    await deleteMediaAsset(asset.id)
    setAssets(assets.value.filter((item) => item.id !== asset.id))
    success.value = 'Asset deleted.'
  } catch (error: unknown) {
    loadError.value = friendlyError(error, 'We could not delete this asset.')
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  void loadAssets()
})
</script>

<template>
  <section data-testid="project-assets" class="nusa-card-soft mt-5 rounded-[2rem] p-5">
    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Project assets</p>
    <h2 class="mt-2 text-xl font-semibold">Upload source files</h2>
    <template v-if="isFashionTryOnProject">
      <p class="mt-3 text-sm leading-6 text-neutral-400">
        Upload a model/person reference image and a garment/product reference image before generating.
      </p>
      <div class="mt-4 grid gap-2 text-sm text-neutral-300">
        <p class="nusa-card-soft rounded-2xl p-3">Upload a model/person reference image.</p>
        <p class="nusa-card-soft rounded-2xl p-3">Upload a garment/product reference image.</p>
      </div>
    </template>
    <template v-else-if="isMotionVideoProject">
      <p class="mt-3 text-sm leading-6 text-neutral-400">
        Upload a product image, source video, background reference or brand reference before generating.
      </p>
      <div class="mt-4 grid gap-2 text-sm text-neutral-300">
        <p class="nusa-card-soft rounded-2xl p-3">Upload a product image, source video, background reference or brand reference.</p>
        <p class="nusa-card-soft rounded-2xl p-3">Optional: upload an audio track for future video sync.</p>
      </div>
    </template>
    <p v-else class="mt-3 text-sm leading-6 text-neutral-400">
      Upload product images, short clips, audio notes or documents.
    </p>

    <div v-if="usesAssetRoles" class="mt-5">
      <label for="asset-role" class="nusa-label">Asset role</label>
      <select id="asset-role" v-model="selectedUsageRole" name="usage_role" class="nusa-select mt-2">
        <option v-for="option in roleOptions" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
    </div>

    <label class="nusa-card-soft mt-5 flex cursor-pointer flex-col items-center rounded-2xl border-dashed px-4 py-7 text-center transition hover:border-cyan-300/40">
      <span class="nusa-label">Choose a project asset</span>
      <span class="mt-2 text-xs leading-5 text-neutral-500">
        JPG, PNG, WEBP, MP4, MOV, WEBM, MP3, WAV, M4A, PDF, TXT or CSV · max
        {{ MEDIA_MAX_SIZE_BYTES / 1024 / 1024 }} MB
      </span>
      <input ref="fileInput" name="media_file" type="file" :accept="MEDIA_ACCEPT" class="sr-only" @change="chooseFile" />
    </label>

    <div v-if="selectedFile" class="nusa-card-soft mt-3 rounded-xl p-3 text-sm">
      <p class="truncate font-medium text-neutral-200">{{ selectedFile.name }}</p>
      <p class="mt-1 text-xs text-neutral-500">{{ (selectedFile.size / 1024 / 1024).toFixed(1) }} MB selected</p>
      <p v-if="usesAssetRoles" class="mt-1 text-xs text-emerald-300">Role: {{ formatLabel(selectedUsageRole) }}</p>
    </div>

    <div v-if="uploadError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">
      {{ uploadError }}
    </div>
    <div v-if="loadError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">
      {{ loadError }}
    </div>
    <p v-if="success" role="status" class="mt-4 text-sm font-medium text-emerald-300">{{ success }}</p>

    <button type="button" :disabled="isUploading || !selectedFile" class="mt-4 min-h-12 w-full rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400" @click="uploadSelectedFile">
      {{ isUploading ? 'Uploading securely...' : 'Upload asset' }}
    </button>

    <p class="mt-4 text-xs leading-5 text-neutral-500">
      Assets are saved with this project. Future AI workflows will use them for video, try-on and editing.
    </p>

    <div class="mt-6 border-t border-neutral-800 pt-5">
      <p v-if="isLoading" class="text-sm text-neutral-500">Loading project assets...</p>

      <div v-else-if="assets.length === 0" class="rounded-2xl border border-dashed border-neutral-800 p-4 text-sm text-neutral-500">
        No assets saved to this project yet.
      </div>

      <div v-else class="space-y-3">
        <article v-for="asset in assets" :key="asset.id" :data-testid="'media-asset-' + asset.id" class="nusa-card-soft rounded-2xl p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-neutral-200">{{ asset.original_name ?? 'Uploaded asset' }}</p>
              <p class="mt-1 text-xs text-neutral-500">
                {{ formatLabel(asset.type) }} · {{ asset.size_label ?? 'Size unavailable' }}
              </p>
              <p v-if="asset.usage_role" class="mt-1 text-xs text-emerald-300">{{ formatLabel(asset.usage_role) }}</p>
              <p class="mt-1 text-xs text-neutral-600">{{ formatDate(asset.created_at) }}</p>
            </div>
            <button type="button" :disabled="deletingId === asset.id" class="shrink-0 rounded-lg border border-rose-900/60 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-950/40 disabled:opacity-50" @click="removeAsset(asset)">
              {{ deletingId === asset.id ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
          <AudioAssetPlayer
            v-if="asset.type === 'audio' && asset.stream_url"
            :media-asset="asset"
            :title="asset.original_name ?? 'Generated audio'"
            class="mt-4"
          />
        </article>
      </div>
    </div>
  </section>
</template>