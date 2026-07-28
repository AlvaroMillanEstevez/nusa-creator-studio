<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { listProjectMediaAssets, uploadMediaAsset } from '@/services/media'
import type { MediaAsset, MediaAssetType, MediaAssetUsageRole } from '@/types/media'
import { MEDIA_MAX_SIZE_BYTES, validateMediaFile } from '@/utils/mediaValidation'
import { mediaAssetOriginLabel } from '@/utils/workflowLineage'

type AcceptedAssetTypes = 'image' | 'video' | 'audio' | 'mixed'

const props = withDefaults(defineProps<{
  projectId: number | string
  modelValue: number | null
  acceptedTypes: AcceptedAssetTypes
  usageRole: MediaAssetUsageRole
  title: string
  helperText?: string
  required?: boolean
  allowUpload?: boolean
  allowExisting?: boolean
  autoSelectFirst?: boolean
  disabled?: boolean
}>(), {
  helperText: '',
  required: false,
  allowUpload: true,
  allowExisting: true,
  autoSelectFirst: false,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  uploaded: [asset: MediaAsset]
  assetsChanged: [assets: MediaAsset[]]
}>()

const { t } = useTranslations()

const assets = ref<MediaAsset[]>([])
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const isLoading = ref(false)
const isUploading = ref(false)
const errorMessage = ref('')

const acceptedMediaTypes = computed<MediaAssetType[]>(() => {
  if (props.acceptedTypes === 'mixed') {
    return ['image', 'video', 'audio']
  }

  return [props.acceptedTypes]
})

const filteredAssets = computed(() =>
  assets.value.filter((asset) => acceptedMediaTypes.value.includes(asset.type)),
)

const selectedAsset = computed(() =>
  filteredAssets.value.find((asset) => asset.id === props.modelValue) ?? null,
)

const acceptAttribute = computed(() => {
  if (props.acceptedTypes === 'image') {
    return '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'
  }

  if (props.acceptedTypes === 'video') {
    return '.mp4,.mov,.webm,video/mp4,video/quicktime,video/webm'
  }

  if (props.acceptedTypes === 'audio') {
    return '.mp3,.wav,.m4a,audio/mpeg,audio/wav,audio/mp4'
  }

  return '.jpg,.jpeg,.png,.webp,.mp4,.mov,.webm,.mp3,.wav,.m4a'
})

const uploadType = computed<MediaAssetType>(() => {
  if (props.acceptedTypes !== 'mixed') {
    return props.acceptedTypes
  }

  return selectedFile.value === null ? 'other' : typeFromFile(selectedFile.value)
})

function typeFromFile(file: File): MediaAssetType {
  if (file.type.startsWith('image/')) {
    return 'image'
  }

  if (file.type.startsWith('video/')) {
    return 'video'
  }

  if (file.type.startsWith('audio/')) {
    return 'audio'
  }

  return 'other'
}

function typeIsAccepted(file: File): boolean {
  const inferredType = typeFromFile(file)

  return acceptedMediaTypes.value.includes(inferredType)
}

function typeLabel(type: MediaAssetType): string {
  if (type === 'image') {
    return t('projectAssetInput.image')
  }

  if (type === 'video') {
    return t('projectAssetInput.video')
  }

  if (type === 'audio') {
    return t('projectAssetInput.audio')
  }

  return t('projectAssetInput.asset')
}

function assetIcon(type: MediaAssetType): string {
  if (type === 'image') {
    return 'IMG'
  }

  if (type === 'video') {
    return 'VID'
  }

  if (type === 'audio') {
    return 'AUD'
  }

  return 'AST'
}

function dateLabel(value: string | null): string {
  if (!value) {
    return t('projectAssetInput.dateUnavailable')
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return t('projectAssetInput.dateUnavailable')
  }

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function maybeAutoSelect(): void {
  if (!props.autoSelectFirst || props.modelValue !== null || filteredAssets.value.length === 0) {
    return
  }

  const firstAsset = filteredAssets.value[0]

  if (firstAsset) {
    emit('update:modelValue', firstAsset.id)
  }
}

async function loadAssets(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await listProjectMediaAssets(props.projectId)
    assets.value = Array.isArray(response.data) ? response.data : []
    emit('assetsChanged', assets.value)
    maybeAutoSelect()
  } catch (error: unknown) {
    errorMessage.value =
      error instanceof ApiError ? error.message : t('projectAssetInput.loadFailed')
  } finally {
    isLoading.value = false
  }
}

function selectAsset(assetId: number): void {
  if (props.disabled) {
    return
  }

  emit('update:modelValue', assetId)
}

function chooseFile(event: Event): void {
  errorMessage.value = ''
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) {
    selectedFile.value = null
    return
  }

  const validationError = validateMediaFile(file)

  if (validationError) {
    errorMessage.value = validationError
    selectedFile.value = null
    input.value = ''
    return
  }

  if (!typeIsAccepted(file)) {
    errorMessage.value = t('projectAssetInput.wrongType')
    selectedFile.value = null
    input.value = ''
    return
  }

  selectedFile.value = file
}

async function uploadSelectedFile(): Promise<void> {
  if (props.disabled) {
    return
  }

  if (selectedFile.value === null) {
    errorMessage.value = t('projectAssetInput.chooseFileFirst')
    return
  }

  isUploading.value = true
  errorMessage.value = ''

  try {
    const asset = await uploadMediaAsset(
      selectedFile.value,
      props.projectId,
      props.usageRole,
      uploadType.value,
    )

    assets.value = [asset, ...assets.value.filter((item) => item.id !== asset.id)]
    selectedFile.value = null
    emit('update:modelValue', asset.id)
    emit('uploaded', asset)
    emit('assetsChanged', assets.value)

    if (fileInput.value) {
      fileInput.value.value = ''
    }
  } catch (error: unknown) {
    errorMessage.value =
      error instanceof ApiError ? error.message : t('projectAssetInput.uploadFailed')
  } finally {
    isUploading.value = false
  }
}

watch(() => [props.projectId, props.acceptedTypes], () => {
  void loadAssets()
})

onMounted(loadAssets)
</script>

<template>
  <section
    class="nusa-card-soft rounded-[1.5rem] border-dashed p-4"
    data-testid="project-asset-input"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-neutral-100">
          {{ title }}
          <span v-if="required" class="text-emerald-300">*</span>
        </p>
        <p v-if="helperText" class="mt-1 text-xs leading-5 text-neutral-500">{{ helperText }}</p>
      </div>
      <span
        v-if="selectedAsset"
        class="rounded-full border border-emerald-800 bg-emerald-950/60 px-3 py-1 text-xs font-semibold text-emerald-200"
      >
        {{ t('projectAssetInput.selected') }}
      </span>
    </div>

    <div v-if="allowExisting" class="mt-4">
      <p class="nusa-label">{{ t('projectAssetInput.selectFromProject') }}</p>

      <div v-if="isLoading" class="mt-3 space-y-2" aria-live="polite">
        <div class="h-16 animate-pulse rounded-2xl bg-neutral-900/80" />
        <div class="h-16 animate-pulse rounded-2xl bg-neutral-900/80" />
      </div>

      <div v-else-if="filteredAssets.length" class="mt-3 grid gap-2">
        <button
          v-for="asset in filteredAssets"
          :key="asset.id"
          type="button"
          class="flex w-full min-w-0 items-start gap-3 rounded-2xl border p-3 text-left transition"
          :class="asset.id === modelValue ? 'border-emerald-500 bg-emerald-950/30' : 'border-neutral-800 bg-neutral-950/60 hover:border-neutral-600'"
          :disabled="disabled"
          :data-testid="'project-asset-option-' + asset.id"
          @click="selectAsset(asset.id)"
        >
          <span class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-neutral-800 bg-neutral-900 text-[10px] font-black tracking-wider text-neutral-400">
            {{ assetIcon(asset.type) }}
          </span>
          <span class="min-w-0 flex-1">
            <span class="block break-words text-sm font-semibold text-neutral-100">
              {{ asset.original_name ?? typeLabel(asset.type) + ' #' + asset.id }}
            </span>
            <span class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs text-neutral-500">
              <span>{{ typeLabel(asset.type) }}</span>
              <span v-if="asset.size_label">{{ asset.size_label }}</span>
              <span>{{ mediaAssetOriginLabel(asset) }}</span>
              <span>{{ dateLabel(asset.created_at) }}</span>
            </span>
          </span>
        </button>
      </div>

      <div
        v-else
        class="mt-3 rounded-2xl border border-dashed border-neutral-800 bg-neutral-950/50 p-4 text-sm leading-6 text-neutral-500"
        data-testid="project-asset-empty"
      >
        {{ t('projectAssetInput.noProjectAssets') }}
      </div>
    </div>

    <div v-if="allowUpload" class="mt-4">
      <p class="nusa-label">{{ t('projectAssetInput.uploadNewAsset') }}</p>
      <label class="mt-2 flex cursor-pointer flex-col items-center rounded-2xl border border-dashed border-neutral-800 bg-neutral-950/60 px-4 py-6 text-center transition hover:border-emerald-500/60">
        <span class="text-sm font-semibold text-neutral-200">{{ t('projectAssetInput.chooseFile') }}</span>
        <span class="mt-1 text-xs leading-5 text-neutral-500">
          {{ t('projectAssetInput.maxSize', { size: MEDIA_MAX_SIZE_BYTES / 1024 / 1024 }) }}
        </span>
        <input
          ref="fileInput"
          class="sr-only"
          type="file"
          :accept="acceptAttribute"
          :disabled="disabled || isUploading"
          data-testid="project-asset-file"
          @change="chooseFile"
        >
      </label>

      <div v-if="selectedFile" class="mt-3 rounded-xl border border-neutral-800 bg-neutral-950/70 p-3 text-sm">
        <p class="break-words font-medium text-neutral-100">{{ selectedFile.name }}</p>
        <p class="mt-1 text-xs text-neutral-500">
          {{ (selectedFile.size / 1024 / 1024).toFixed(1) }} MB · {{ typeLabel(uploadType) }}
        </p>
      </div>

      <button
        type="button"
        class="mt-3 min-h-11 w-full rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400"
        :disabled="disabled || isUploading || selectedFile === null"
        data-testid="project-asset-upload"
        @click="uploadSelectedFile"
      >
        {{ isUploading ? t('projectAssetInput.uploading') : t('projectAssetInput.uploadAndUse') }}
      </button>
    </div>

    <p
      v-if="errorMessage"
      role="alert"
      class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200"
    >
      {{ errorMessage }}
    </p>
  </section>
</template>
