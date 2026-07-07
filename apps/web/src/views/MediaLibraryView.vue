<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { deleteMediaAsset, getMediaAssetStats, listMediaAssets } from '@/services/media'
import type { MediaAsset, MediaAssetType, MediaAssetUsageRole } from '@/types/media'
import { t } from '@/i18n'
import { formatDate, formatLabel } from '@/utils/formatters'

const assets = ref<MediaAsset[]>([])
const stats = ref<Awaited<ReturnType<typeof getMediaAssetStats>>['data'] | null>(null)
const isLoading = ref(true)
const isFiltering = ref(false)
const deletingId = ref<number | null>(null)
const error = ref('')
const successMessage = ref('')
const search = ref('')
const selectedType = ref<MediaAssetType | ''>('')
const selectedUsageRole = ref<MediaAssetUsageRole | ''>('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalAssets = ref(0)

const typeOptions: Array<{ value: MediaAssetType; labelKey: string }> = [
  { value: 'image', labelKey: 'media.images' },
  { value: 'video', labelKey: 'media.videos' },
  { value: 'audio', labelKey: 'media.audio' },
  { value: 'document', labelKey: 'media.documents' },
  { value: 'other', labelKey: 'common.other' },
]

const usageRoleOptions: Array<{ value: MediaAssetUsageRole; labelKey: string }> = [
  { value: 'general', labelKey: 'media.roles.general' },
  { value: 'product_image', labelKey: 'media.roles.productImage' },
  { value: 'source_video', labelKey: 'media.roles.sourceVideo' },
  { value: 'audio_track', labelKey: 'media.roles.audioTrack' },
  { value: 'model_reference', labelKey: 'media.roles.modelReference' },
  { value: 'garment_reference', labelKey: 'media.roles.garmentReference' },
  { value: 'background_reference', labelKey: 'media.roles.backgroundReference' },
  { value: 'brand_reference', labelKey: 'media.roles.brandReference' },
]

const summaryCards = computed(() => [
  { label: t('media.totalAssets'), value: stats.value?.total_assets ?? 0 },
  { label: t('media.storageUsed'), value: stats.value?.total_size_label ?? '0 B' },
  { label: t('media.images'), value: stats.value?.images_count ?? 0 },
  { label: t('media.videos'), value: stats.value?.videos_count ?? 0 },
  { label: t('media.audio'), value: stats.value?.audio_count ?? 0 },
  { label: t('media.documents'), value: stats.value?.documents_count ?? 0 },
])

const hasActiveFilters = computed(
  () => search.value.trim() !== '' || selectedType.value !== '' || selectedUsageRole.value !== '',
)

function friendlyError(requestError: unknown, fallback: string): string {
  return requestError instanceof ApiError ? requestError.message : fallback
}

async function loadStats(): Promise<void> {
  const response = await getMediaAssetStats()
  stats.value = response.data
}

async function loadAssets(page = 1): Promise<void> {
  const response = await listMediaAssets({
    search: search.value,
    type: selectedType.value || undefined,
    usage_role: selectedUsageRole.value || undefined,
    page,
    per_page: 12,
  })

  assets.value = response.data
  currentPage.value = response.meta?.current_page ?? page
  lastPage.value = response.meta?.last_page ?? 1
  totalAssets.value = response.meta?.total ?? response.data.length
}

async function loadLibrary(): Promise<void> {
  isLoading.value = true
  error.value = ''
  successMessage.value = ''

  try {
    await Promise.all([loadStats(), loadAssets(1)])
  } catch (requestError: unknown) {
    error.value = friendlyError(
      requestError,
      t('errors.mediaLoad'),
    )
  } finally {
    isLoading.value = false
  }
}

async function applyFilters(): Promise<void> {
  isFiltering.value = true
  error.value = ''
  successMessage.value = ''

  try {
    await loadAssets(1)
  } catch (requestError: unknown) {
    error.value = friendlyError(
      requestError,
      t('errors.mediaFilter'),
    )
  } finally {
    isFiltering.value = false
  }
}

async function clearFilters(): Promise<void> {
  search.value = ''
  selectedType.value = ''
  selectedUsageRole.value = ''
  await applyFilters()
}

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > lastPage.value || page === currentPage.value) {
    return
  }

  isFiltering.value = true
  error.value = ''

  try {
    await loadAssets(page)
  } catch (requestError: unknown) {
    error.value = friendlyError(requestError, t('errors.mediaPage'))
  } finally {
    isFiltering.value = false
  }
}

async function deleteAsset(asset: MediaAsset): Promise<void> {
  const name = asset.original_name ?? t('media.thisAsset')

  if (!window.confirm(t('media.deleteConfirm', { name }))) {
    return
  }

  deletingId.value = asset.id
  error.value = ''
  successMessage.value = ''

  try {
    await deleteMediaAsset(asset.id)
    assets.value = assets.value.filter((item) => item.id !== asset.id)
    totalAssets.value = Math.max(0, totalAssets.value - 1)
    await loadStats()
    successMessage.value = t('media.deleted')
  } catch (requestError: unknown) {
    error.value = friendlyError(
      requestError,
      t('errors.mediaDelete'),
    )
  } finally {
    deletingId.value = null
  }
}

function assetIcon(type: MediaAssetType): string {
  return {
    image: t('media.assetIcons.image'),
    video: t('media.assetIcons.video'),
    audio: t('media.assetIcons.audio'),
    document: t('media.assetIcons.document'),
    other: t('media.assetIcons.other'),
  }[type]
}

onMounted(loadLibrary)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-7xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">{{ t('media.kicker') }}</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight">{{ t('media.title') }}</h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
          {{ t('media.body') }}
        </p>
      </header>

      <section v-if="isLoading" class="mt-9 space-y-5" aria-live="polite">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div
            v-for="index in 6"
            :key="index"
            class="h-24 animate-pulse rounded-2xl bg-neutral-900"
          />
        </div>
        <div class="h-48 animate-pulse rounded-3xl bg-neutral-900" />
        <p class="text-sm text-neutral-500">{{ t('media.loading') }}</p>
      </section>

      <section
        v-else-if="error && assets.length === 0"
        class="mt-9 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5"
      >
        <p class="text-sm font-medium text-rose-300">{{ t('media.unavailable') }}</p>
        <h2 class="mt-2 text-xl font-semibold">{{ t('media.retryTitle') }}</h2>
        <p role="alert" class="mt-3 text-sm leading-6 text-rose-100/70">{{ error }}</p>
        <button
          type="button"
          class="mt-5 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300"
          @click="loadLibrary"
        >
          {{ t('common.tryAgain') }}
        </button>
      </section>

      <template v-else>
        <section class="mt-9 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <article
            v-for="card in summaryCards"
            :key="card.label"
            class="nusa-card-soft rounded-2xl p-4"
          >
            <p class="text-2xl font-bold text-neutral-100">{{ card.value }}</p>
            <p class="mt-2 text-xs leading-5 text-neutral-500">{{ card.label }}</p>
          </article>
        </section>

        <section class="nusa-form-card mt-8 rounded-[2rem] p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                {{ t('media.filters') }}
              </p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('media.findAssets') }}</h2>
            </div>
            <p class="text-xs text-neutral-500">{{ t('media.matchingAssets', { count: totalAssets }) }}</p>
          </div>

          <form class="mt-5 grid min-w-0 gap-3 sm:grid-cols-3" @submit.prevent="applyFilters">
            <label class="sm:col-span-3 lg:col-span-1">
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                {{ t('media.search') }}
              </span>
              <input
                v-model="search"
                name="search"
                type="search"
                :placeholder="t('media.searchPlaceholder')"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-700 bg-neutral-950 px-4 text-sm text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500"
              />
            </label>

            <label>
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                {{ t('media.type') }}
              </span>
              <select
                v-model="selectedType"
                name="type"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-700 bg-neutral-950 px-4 text-sm text-white outline-none transition focus:border-emerald-500"
              >
                <option value="">{{ t('media.allTypes') }}</option>
                <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                  {{ t(option.labelKey) }}
                </option>
              </select>
            </label>

            <label>
              <span class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                {{ t('media.usageRole') }}
              </span>
              <select
                v-model="selectedUsageRole"
                name="usage_role"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-700 bg-neutral-950 px-4 text-sm text-white outline-none transition focus:border-emerald-500"
              >
                <option value="">{{ t('media.allRoles') }}</option>
                <option
                  v-for="option in usageRoleOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ t(option.labelKey) }}
                </option>
              </select>
            </label>

            <div class="grid min-w-0 gap-3 sm:col-span-3 sm:flex">
              <button
                type="submit"
                :disabled="isFiltering"
                class="min-h-12 w-full rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:opacity-60 sm:flex-1"
              >
                {{ isFiltering ? t('media.filtering') : t('common.applyFilters') }}
              </button>
              <button
                type="button"
                :disabled="isFiltering || !hasActiveFilters"
                class="min-h-12 w-full rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                @click="clearFilters"
              >
                {{ t('common.clear') }}
              </button>
            </div>
          </form>
        </section>

        <p
          v-if="successMessage"
          role="status"
          class="mt-5 rounded-2xl border border-emerald-900/60 bg-emerald-950/30 p-4 text-sm text-emerald-100"
        >
          {{ successMessage }}
        </p>

        <p
          v-if="error && assets.length > 0"
          role="alert"
          class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-100"
        >
          {{ error }}
        </p>

        <section class="mt-8">
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                {{ t('media.uploadedMedia') }}
              </p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('media.assets') }}</h2>
            </div>
            <p v-if="isFiltering" class="text-xs text-neutral-500">{{ t('media.refreshing') }}</p>
          </div>

          <div
            v-if="assets.length === 0"
            class="nusa-card-soft mt-5 rounded-[2rem] border-dashed p-6 text-center"
          >
            <p class="text-lg font-semibold">{{ t('media.emptyTitle') }}</p>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
              {{ t('media.emptyBody') }}
            </p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
              <RouterLink
                to="/tools"
                class="flex min-h-12 items-center justify-center rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
              >
                {{ t('media.browseTools') }}
              </RouterLink>
              <RouterLink
                to="/dashboard"
                class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
              >
                {{ t('media.backDashboard') }}
              </RouterLink>
            </div>
          </div>

          <div v-else class="mt-5 grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-2">
            <article
              v-for="asset in assets"
              :key="asset.id"
              :data-testid="'media-library-asset-' + asset.id"
              class="nusa-card-soft min-w-0 max-w-full overflow-hidden rounded-[2rem] p-4 sm:p-5"
            >
              <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                <div
                  class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-neutral-700 bg-neutral-950 text-xs font-semibold text-emerald-300 sm:size-14"
                >
                  {{ assetIcon(asset.type) }}
                </div>
                <div class="min-w-0 max-w-full flex-1 overflow-hidden">
                  <h3 class="max-w-full break-words text-base font-semibold leading-6 text-neutral-100">
                    {{ asset.original_name ?? t('media.uploadedAsset') }}
                  </h3>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <span
                      class="rounded-full border border-emerald-900/70 bg-emerald-950/40 px-3 py-1 text-xs font-semibold text-emerald-300"
                    >
                      {{ formatLabel(asset.type) }}
                    </span>
                    <span
                      v-if="asset.usage_role"
                      class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-medium text-neutral-300"
                    >
                      {{ formatLabel(asset.usage_role) }}
                    </span>
                  </div>
                </div>
              </div>

              <dl class="mt-5 grid min-w-0 grid-cols-1 gap-3 border-t border-neutral-800 pt-4 text-sm sm:grid-cols-2">
                <div class="min-w-0">
                  <dt class="text-xs uppercase tracking-[0.14em] text-neutral-600">{{ t('media.size') }}</dt>
                  <dd class="mt-1 text-neutral-300">
                    {{ asset.size_label ?? t('media.sizeUnavailable') }}
                  </dd>
                </div>
                <div class="min-w-0">
                  <dt class="text-xs uppercase tracking-[0.14em] text-neutral-600">{{ t('media.uploaded') }}</dt>
                  <dd class="mt-1 text-neutral-300">{{ formatDate(asset.created_at) }}</dd>
                </div>
                <div v-if="asset.project" class="min-w-0 sm:col-span-2">
                  <dt class="text-xs uppercase tracking-[0.14em] text-neutral-600">{{ t('media.project') }}</dt>
                  <dd class="mt-1 max-w-full break-words text-neutral-300">
                    {{ asset.project.title }} · {{ formatLabel(asset.project.type) }}
                  </dd>
                </div>
              </dl>

              <button
                type="button"
                :disabled="deletingId === asset.id"
                class="mt-5 min-h-11 w-full rounded-2xl border border-rose-900/60 px-4 text-sm font-semibold text-rose-300 transition hover:bg-rose-950/40 disabled:cursor-wait disabled:opacity-60"
                @click="deleteAsset(asset)"
              >
                {{ deletingId === asset.id ? t('media.deleting') : t('media.deleteAsset') }}
              </button>
            </article>
          </div>
        </section>

        <div v-if="lastPage > 1" class="mt-8 flex items-center justify-between gap-3">
          <button
            type="button"
            :disabled="currentPage <= 1 || isFiltering"
            class="min-h-11 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800 disabled:opacity-50"
            @click="goToPage(currentPage - 1)"
          >
            {{ t('media.previous') }}
          </button>
          <p class="text-xs text-neutral-500">{{ t('media.pageOf', { current: currentPage, last: lastPage }) }}</p>
          <button
            type="button"
            :disabled="currentPage >= lastPage || isFiltering"
            class="min-h-11 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800 disabled:opacity-50"
            @click="goToPage(currentPage + 1)"
          >
            {{ t('media.next') }}
          </button>
        </div>
      </template>
    </div>
  </main>
</template>
