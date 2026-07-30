<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import { useTranslations } from '@/i18n'
import { fetchMediaBlob } from '@/services/media'
import { useAuthStore } from '@/stores/auth'
import type { FashionTryOnRenderedImageAsset, FashionTryOnResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  result: FashionTryOnResult
  projectId?: number | string | null
  generationId?: number | string | null
}>()

interface SummaryItem {
  label: string
  value: string | number
}

interface NormalizedTryOnImage extends FashionTryOnRenderedImageAsset {
  id: number
  output_index: number | null
}

const { t } = useTranslations()
const auth = useAuthStore()
const imageUrl = ref<string | null>(null)
const imageError = ref('')
const downloadError = ref('')
const isPreviewLoading = ref(false)
const isDownloading = ref(false)

const normalizedImages = computed<NormalizedTryOnImage[]>(() => normalizeRenderedImages(props.result))
const selectedImage = computed(() => normalizedImages.value[0] ?? null)
const packageKey = computed(() => stringValue(
  props.result.package_key
    ?? props.result.provider_metadata?.package_key
    ?? props.result.generation_summary?.package_key,
  'single_result',
))
const personSource = computed(() => stringValue(
  props.result.person_source
    ?? props.result.provider_metadata?.person_source
    ?? props.result.generation_summary?.person_source,
  'user_upload',
))
const outputCount = computed(() => {
  const summaryCount = numberValue(props.result.generation_summary?.output_count)
    ?? numberValue(props.result.provider_metadata?.output_count)

  return summaryCount ?? normalizedImages.value.length
})
const outputCountLabel = computed(() => {
  if (outputCount.value === 1) {
    return t('fashionTryOn.result.oneImageOutput')
  }

  return t('fashionTryOn.result.manyImageOutputs', { count: outputCount.value })
})
const finalImageAssetId = computed(() => selectedImage.value?.id ?? numberValue(props.result.final_image_asset_id))
const streamUrl = computed(() => selectedImage.value?.stream_url ?? props.result.final_image_stream_url ?? null)
const downloadUrl = computed(() => selectedImage.value?.download_url ?? props.result.final_image_download_url ?? null)
const hasRenderedImage = computed(() => props.result.generated_image.status === 'rendered' && normalizedImages.value.length > 0)
const isMockResult = computed(() => props.result.generated_image.provider === 'mock' || props.result.generated_image.status !== 'rendered')
const projectRoute = computed(() => props.projectId === null || props.projectId === undefined ? '/dashboard' : `/projects/${String(props.projectId)}`)
const generateAnotherRoute = computed(() => props.projectId === null || props.projectId === undefined
  ? '/dashboard'
  : { name: 'project-fashion-try-on-action', params: { projectId: String(props.projectId) } })
const animateRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || finalImageAssetId.value === null || isMockResult.value) {
    return null
  }

  const query: Record<string, string> = {
    sourceMode: 'image_first_frame',
    productImageAssetId: String(finalImageAssetId.value),
  }

  if (props.generationId !== null && props.generationId !== undefined) {
    query.sourceGenerationId = String(props.generationId)
  }

  return {
    name: 'project-motion-ai-video-action',
    params: { projectId: String(props.projectId) },
    query,
  }
})
const summaryItems = computed<SummaryItem[]>(() => [
  { label: t('fashionTryOn.result.package'), value: packageLabel(packageKey.value) },
  { label: t('fashionTryOn.result.personSource'), value: personSourceLabel(personSource.value) },
  { label: 'Quality', value: props.result.generation_summary?.quality ?? props.result.tier },
  { label: 'Category', value: props.result.generation_summary?.category ?? props.result.category },
  { label: 'Person photo', value: assetLabel(props.result.selected_person_image) },
  { label: 'Product photo', value: assetLabel(props.result.selected_product_image) },
  { label: 'Output count', value: outputCount.value > 0 ? outputCount.value : null },
  { label: 'Output format', value: props.result.generation_summary?.output_format ?? selectedImage.value?.mime_type ?? props.result.rendered_image_asset?.mime_type },
].filter((item): item is SummaryItem => item.value !== null && item.value !== undefined && item.value !== ''))
const canViewDiagnostics = computed(() => {
  const user = auth.user

  return user?.role === 'admin' || user?.role === 'internal_creator' || user?.is_unlimited === true || user?.plan_key === 'internal_unlimited'
})
const diagnosticsItems = computed<SummaryItem[]>(() => {
  const metadata = props.result.provider_metadata ?? {}

  return [
    { label: 'Provider', value: metadata.provider },
    { label: 'Model', value: metadata.provider_model },
    { label: 'Prediction ID', value: metadata.provider_prediction_id },
    { label: 'Provider status', value: metadata.provider_status },
    { label: 'Provider credits used', value: metadata.provider_credits_used },
    { label: 'Provider credits source', value: metadata.provider_credits_source },
    { label: 'Estimated provider cost', value: metadata.estimated_provider_cost_usd },
    { label: 'Actual provider cost', value: metadata.actual_provider_cost_usd ?? 'Unavailable' },
    { label: 'Provider latency', value: metadata.provider_latency_ms ? `${metadata.provider_latency_ms}ms` : null },
    { label: 'Package', value: packageKey.value },
    { label: 'Person source', value: personSource.value },
    { label: 'Final image asset', value: finalImageAssetId.value },
  ].filter((item): item is SummaryItem => item.value !== null && item.value !== undefined && item.value !== '')
})

function numericId(value: unknown): number | null {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return null
  }

  return value
}

function numberValue(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value
  }

  return null
}

function stringValue(value: unknown, fallback: string): string {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback
}

function normalizeRenderedImages(result: FashionTryOnResult): NormalizedTryOnImage[] {
  const images: NormalizedTryOnImage[] = []
  const seen = new Set<number>()
  const pushAsset = (asset: FashionTryOnRenderedImageAsset | null | undefined): void => {
    const id = numericId(asset?.id)

    if (asset === null || asset === undefined || id === null || seen.has(id)) {
      return
    }

    const isPrimary = id === result.final_image_asset_id
    seen.add(id)
    images.push({
      ...asset,
      id,
      stream_url: asset.stream_url ?? (isPrimary ? result.final_image_stream_url ?? null : null),
      download_url: asset.download_url ?? (isPrimary ? result.final_image_download_url ?? null : null),
      output_index: numberValue(asset.output_index),
    })
  }

  if (Array.isArray(result.rendered_image_assets)) {
    result.rendered_image_assets.forEach(pushAsset)
  }

  pushAsset(result.rendered_image_asset)

  const historicalId = numericId(result.final_image_asset_id)
  if (historicalId !== null && !seen.has(historicalId)) {
    images.push({
      id: historicalId,
      stream_url: result.final_image_stream_url ?? null,
      download_url: result.final_image_download_url ?? null,
      mime_type: result.rendered_image_asset?.mime_type ?? null,
      size_bytes: result.rendered_image_asset?.size_bytes ?? null,
      output_index: 0,
    })
  }

  return images.sort((a, b) => (a.output_index ?? 0) - (b.output_index ?? 0))
}

function packageLabel(value: string): string {
  const knownLabels: Record<string, string> = {
    single_result: t('fashionTryOn.packages.single_result.label'),
    variations: t('fashionTryOn.packages.variations.label'),
    catalog_pack: t('fashionTryOn.packages.catalog_pack.label'),
    catalog_video_pack: t('fashionTryOn.packages.catalog_video_pack.label'),
  }

  return knownLabels[value] ?? formatLabel(value)
}

function personSourceLabel(value: string): string {
  const knownLabels: Record<string, string> = {
    user_upload: t('fashionTryOn.result.uploadedPhoto'),
    nusa_ai_model: t('fashionTryOn.personSources.nusa_ai_model.label'),
    custom_ai_model: t('fashionTryOn.personSources.custom_ai_model.label'),
  }

  return knownLabels[value] ?? formatLabel(value)
}

function assetLabel(asset: FashionTryOnResult['selected_person_image']): string | null {
  if (!asset) {
    return null
  }

  return asset.original_name ?? `Asset #${asset.id}`
}

function safeList(values: string[] | undefined): string[] {
  return Array.isArray(values) ? values.filter((item) => item.trim() !== '') : []
}

function revokePreview(): void {
  if (imageUrl.value !== null) {
    URL.revokeObjectURL(imageUrl.value)
    imageUrl.value = null
  }
}

async function loadPreview(): Promise<void> {
  revokePreview()
  imageError.value = ''

  if (!hasRenderedImage.value || streamUrl.value === null) {
    return
  }

  isPreviewLoading.value = true

  try {
    const blob = await fetchMediaBlob(streamUrl.value)
    imageUrl.value = URL.createObjectURL(blob)
  } catch {
    imageError.value = 'Could not load the secure image preview.'
  } finally {
    isPreviewLoading.value = false
  }
}

async function downloadImage(): Promise<void> {
  downloadError.value = ''

  if (downloadUrl.value === null) {
    downloadError.value = 'Could not prepare the secure image download.'
    return
  }

  isDownloading.value = true

  try {
    const blob = await fetchMediaBlob(downloadUrl.value)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `fashion-try-on-${finalImageAssetId.value ?? 'image'}.png`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'Could not prepare the secure image download.'
  } finally {
    isDownloading.value = false
  }
}

watch(streamUrl, () => {
  void loadPreview()
})
onMounted(() => {
  void loadPreview()
})
onBeforeUnmount(revokePreview)
</script>

<template>
  <div class="space-y-5" data-testid="fashion-try-on-result">
    <section v-if="hasRenderedImage" class="nusa-card-premium overflow-hidden rounded-[2rem] p-5" data-testid="fashion-real-result">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">AI Fashion Try-On</p>
          <h2 class="mt-3 text-2xl font-bold">{{ result.try_on_title }}</h2>
          <p class="mt-3 text-sm leading-7 text-neutral-300">{{ result.concept_summary }}</p>
        </div>
        <span class="w-fit rounded-full border border-emerald-800 bg-emerald-950/60 px-3 py-1 text-xs font-semibold text-emerald-200">
          {{ formatLabel(result.tier ?? 'standard') }} &middot; {{ outputCountLabel }}
        </span>
      </div>

      <div class="mt-5 overflow-hidden rounded-[1.75rem] border border-neutral-800 bg-neutral-950/70">
        <div v-if="isPreviewLoading" class="flex min-h-80 items-center justify-center text-sm text-neutral-500" aria-live="polite">
          Loading secure image preview...
        </div>
        <img v-else-if="imageUrl" :src="imageUrl" alt="Generated AI Fashion Try-On result" class="max-h-[720px] w-full object-contain" data-testid="fashion-result-image">
        <div v-else class="flex min-h-80 items-center justify-center p-6 text-center text-sm leading-6 text-neutral-500">
          Secure image preview is not available right now. You can still try downloading it if the asset exists.
        </div>
      </div>
      <p v-if="imageError" role="alert" class="mt-3 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100">
        {{ imageError }}
      </p>

      <div v-if="normalizedImages.length > 1" class="mt-4 grid gap-3 sm:grid-cols-2" data-testid="fashion-rendered-image-assets">
        <div v-for="image in normalizedImages" :key="image.id" class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
          <p class="font-semibold text-neutral-100">Result {{ (image.output_index ?? 0) + 1 }}</p>
          <p class="mt-1 text-xs text-neutral-500">Asset #{{ image.id }}</p>
        </div>
      </div>

      <div class="mt-5 rounded-[1.75rem] border border-neutral-800 bg-neutral-950/50 p-4" data-testid="fashion-direct-actions">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-400">{{ t('fashionTryOn.result.directActions') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <button
            v-if="downloadUrl"
            type="button"
            class="flex min-h-12 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isDownloading"
            data-testid="fashion-download-image"
            @click="downloadImage"
          >
            {{ isDownloading ? 'Preparing download...' : 'Download image' }}
          </button>
          <RouterLink :to="generateAnotherRoute" class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-100 transition hover:border-emerald-500 hover:bg-emerald-950/30">
            Generate another version
          </RouterLink>
          <RouterLink :to="projectRoute" class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-100 transition hover:border-neutral-500 hover:bg-neutral-800">
            Return to project
          </RouterLink>
        </div>
      </div>
    </section>

    <section v-else class="rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5" data-testid="fashion-mock-result">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">Try-on preparation complete</p>
      <h2 class="mt-3 text-xl font-bold">{{ result.try_on_title }}</h2>
      <p class="mt-4 text-sm leading-7 text-sky-100/80">{{ result.generated_image.message }}</p>
      <p class="mt-3 text-sm leading-7 text-neutral-300">{{ result.concept_summary }}</p>
      <p class="mt-4 rounded-2xl border border-sky-800/60 bg-neutral-950/50 p-4 text-sm leading-6 text-sky-100/80">
        No fashion image was generated in mock mode. Use this as a readiness plan before enabling a real provider.
      </p>
      <RouterLink :to="generateAnotherRoute" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-2xl border border-sky-700 px-4 text-sm font-semibold text-sky-100 transition hover:border-sky-400 hover:bg-sky-950/40">
        Generate another version
      </RouterLink>
    </section>

    <section v-if="summaryItems.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Summary</p>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2">
        <div v-for="item in summaryItems" :key="item.label" class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
          <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">{{ item.label }}</dt>
          <dd class="mt-2 break-words text-sm font-semibold text-neutral-100">{{ item.value }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="animateRoute" class="rounded-[2rem] border border-sky-800/70 bg-sky-950/20 p-5" data-testid="fashion-motion-continuation">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-300">{{ t('fashionTryOn.result.separateWorkflow') }}</p>
          <h2 class="mt-2 text-xl font-bold text-sky-50">{{ t('fashionTryOn.result.continuePremiumVideo') }}</h2>
          <p class="mt-3 text-sm leading-7 text-sky-100/80">{{ t('fashionTryOn.result.premiumVideoCopy') }}</p>
        </div>
        <RouterLink
          :to="animateRoute"
          class="flex min-h-12 shrink-0 items-center justify-center rounded-2xl bg-sky-300 px-4 text-center text-sm font-semibold text-sky-950 transition hover:bg-sky-200"
          data-testid="fashion-continue-motion"
        >
          {{ t('fashionTryOn.result.continueMotion') }}
        </RouterLink>
      </div>
      <ul class="mt-4 grid gap-2 text-sm leading-6 text-sky-100/80 sm:grid-cols-2">
        <li>&bull; {{ t('fashionTryOn.result.additionalCredits') }}</li>
        <li>&bull; {{ t('fashionTryOn.result.noVideoIncluded') }}</li>
        <li>&bull; {{ t('fashionTryOn.result.reviewSettings') }}</li>
        <li>&bull; {{ t('fashionTryOn.result.noAutoGeneration') }}</li>
      </ul>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div v-if="safeList(result.composition_plan).length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Composition plan</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in safeList(result.composition_plan)" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div v-if="safeList(result.image_quality_recommendations).length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Image quality recommendations</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in safeList(result.image_quality_recommendations)" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
      <div v-if="safeList(result.quality_checklist).length" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Quality checklist</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in safeList(result.quality_checklist)" :key="item">&check; {{ item }}</li>
        </ul>
      </div>
      <div v-if="safeList(result.suggested_next_actions).length && isMockResult" class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Suggested next actions</p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="item in safeList(result.suggested_next_actions)" :key="item">&bull; {{ item }}</li>
        </ul>
      </div>
    </section>

    <section v-if="safeList(result.compliance_notes).length" class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">Responsible-use notes</p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in safeList(result.compliance_notes)" :key="item">&bull; {{ item }}</li>
      </ul>
    </section>

    <p v-if="downloadError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100">
      {{ downloadError }}
    </p>

    <details v-if="canViewDiagnostics && diagnosticsItems.length" class="rounded-3xl border border-neutral-800 bg-neutral-950/60 p-5" data-testid="fashion-internal-diagnostics">
      <summary class="cursor-pointer text-sm font-semibold text-neutral-200">Internal provider diagnostics</summary>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2">
        <div v-for="item in diagnosticsItems" :key="item.label" class="rounded-2xl border border-neutral-800 bg-neutral-950 p-3">
          <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">{{ item.label }}</dt>
          <dd class="mt-1 break-words text-sm text-neutral-200">{{ item.value }}</dd>
        </div>
      </dl>
    </details>
  </div>
</template>
