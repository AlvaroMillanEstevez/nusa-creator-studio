<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import { fetchMediaBlob } from '@/services/media'
import { useAuthStore } from '@/stores/auth'
import type { FashionTryOnResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  result: FashionTryOnResult
  projectId?: number | string | null
  generationId?: number | string | null
}>()

const auth = useAuthStore()
const imageUrl = ref<string | null>(null)
const imageError = ref('')
const downloadError = ref('')
const isPreviewLoading = ref(false)
const isDownloading = ref(false)

const finalImageAssetId = computed(() => {
  const value = props.result.final_image_asset_id ?? props.result.rendered_image_asset?.id ?? null

  return typeof value === 'number' && Number.isFinite(value) ? value : null
})
const streamUrl = computed(() => props.result.final_image_stream_url ?? props.result.rendered_image_asset?.stream_url ?? null)
const downloadUrl = computed(() => props.result.final_image_download_url ?? props.result.rendered_image_asset?.download_url ?? null)
const hasRenderedImage = computed(() => props.result.generated_image.status === 'rendered' && streamUrl.value !== null)
const isMockResult = computed(() => props.result.generated_image.provider === 'mock' || !hasRenderedImage.value)
const projectRoute = computed(() => props.projectId === null || props.projectId === undefined ? '/dashboard' : `/projects/${String(props.projectId)}`)
const generateAnotherRoute = computed(() => props.projectId === null || props.projectId === undefined
  ? '/dashboard'
  : { name: 'project-fashion-try-on-action', params: { projectId: String(props.projectId) } })
const animateRoute = computed(() => {
  if (props.projectId === null || props.projectId === undefined || finalImageAssetId.value === null) {
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
const summaryItems = computed(() => [
  { label: 'Quality', value: props.result.generation_summary?.quality ?? props.result.tier },
  { label: 'Category', value: props.result.generation_summary?.category ?? props.result.category },
  { label: 'Person photo', value: assetLabel(props.result.selected_person_image) },
  { label: 'Product photo', value: assetLabel(props.result.selected_product_image) },
  { label: 'Output format', value: props.result.generation_summary?.output_format ?? props.result.rendered_image_asset?.mime_type },
].filter((item): item is { label: string; value: string | number } => item.value !== null && item.value !== undefined && item.value !== ''))
const canViewDiagnostics = computed(() => {
  const user = auth.user

  return user?.role === 'admin' || user?.role === 'internal_creator' || user?.is_unlimited === true || user?.plan_key === 'internal_unlimited'
})
const diagnosticsItems = computed(() => {
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
    { label: 'Final image asset', value: finalImageAssetId.value },
  ].filter((item): item is { label: string; value: string | number } => item.value !== null && item.value !== undefined && item.value !== '')
})

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

watch(streamUrl, loadPreview)
onMounted(loadPreview)
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
          {{ formatLabel(result.tier ?? 'standard') }}
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

      <div class="mt-5 grid gap-3 sm:grid-cols-3">
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
        <RouterLink
          v-if="animateRoute"
          :to="animateRoute"
          class="flex min-h-12 items-center justify-center rounded-2xl border border-sky-800 bg-sky-950/30 px-4 text-center text-sm font-semibold text-sky-100 transition hover:border-sky-500 hover:bg-sky-900/40"
          data-testid="fashion-animate-motion"
        >
          Animate with Motion AI Video
        </RouterLink>
        <RouterLink :to="projectRoute" class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-100 transition hover:border-neutral-500 hover:bg-neutral-800">
          Return to project
        </RouterLink>
      </div>
      <p v-if="downloadError" role="alert" class="mt-3 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-100">
        {{ downloadError }}
      </p>
    </section>

    <section v-else class="rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5" data-testid="fashion-mock-result">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">Try-on preparation complete</p>
      <h2 class="mt-3 text-xl font-bold">{{ result.try_on_title }}</h2>
      <p class="mt-4 text-sm leading-7 text-sky-100/80">{{ result.generated_image.message }}</p>
      <p class="mt-3 text-sm leading-7 text-neutral-300">{{ result.concept_summary }}</p>
      <p class="mt-4 rounded-2xl border border-sky-800/60 bg-neutral-950/50 p-4 text-sm leading-6 text-sky-100/80">
        No fashion image was generated in mock mode. Use this as a readiness plan before enabling a real provider.
      </p>
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
          <li v-for="item in safeList(result.quality_checklist)" :key="item">✓ {{ item }}</li>
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
