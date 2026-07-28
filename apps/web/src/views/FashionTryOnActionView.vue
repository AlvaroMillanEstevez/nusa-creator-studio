<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { ApiError } from '@/services/api'
import { generateFashionTryOn, getCreatorProject, getFashionTryOnCapabilities, getProjectActions } from '@/services/projects'
import {
  fashionTryOnCategoryOptions,
  fashionTryOnGarmentPhotoTypeOptions,
  fashionTryOnStandardModeOptions,
  fashionTryOnTierOptions,
  type CreatorProject,
  type FashionTryOnCategory,
  type FashionTryOnGarmentPhotoType,
  type FashionTryOnProviderCapabilities,
  type FashionTryOnStandardMode,
  type FashionTryOnTier,
  type GenerateFashionTryOnPayload,
  type ProjectAction,
} from '@/types/project'
import { positiveInt } from '@/utils/workflowLineage'

const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const fetchedCapabilities = ref<FashionTryOnProviderCapabilities | null>(null)
const tier = ref<FashionTryOnTier>('standard')
const personImageAssetId = ref<number | null>(null)
const productImageAssetId = ref<number | null>(null)
const category = ref<FashionTryOnCategory>('auto')
const garmentPhotoType = ref<FashionTryOnGarmentPhotoType>('auto')
const standardMode = ref<FashionTryOnStandardMode>('balanced')
const consentConfirmed = ref(false)
const advancedOpen = ref(false)
const isLoading = ref(true)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const fashionAction = computed(() => actions.value.find((item) => item.key === 'start_fashion_try_on') ?? null)
const actionCapabilities = computed(() => {
  const value = fashionAction.value?.provider_capabilities

  return isFashionTryOnCapabilities(value) ? value : null
})
const capabilities = computed(() => actionCapabilities.value ?? fetchedCapabilities.value)
const creditCost = computed(() => fashionAction.value?.credit_cost ?? 2)
const isUpgradeRequired = computed(() => fashionAction.value?.upgrade_required === true || fashionAction.value?.action_mode === 'upgrade')
const standardEnabled = computed(() => capabilities.value?.standard_enabled !== false)
const premiumEnabled = computed(() => capabilities.value?.premium_enabled === true)
const isMockMode = computed(() => capabilities.value?.mock_mode !== false)
const providerLabel = computed(() => isMockMode.value ? 'Mock preview plan' : 'FASHN Standard')
const selectedTierOption = computed(() => fashionTryOnTierOptions.find((option) => option.value === tier.value) ?? fashionTryOnTierOptions[0])
const categoryChoices = computed(() => {
  const allowed = capabilities.value?.standard_categories ?? []

  return fashionTryOnCategoryOptions.filter((option) => allowed.length === 0 || allowed.includes(option.value))
})
const garmentPhotoTypeChoices = computed(() => {
  const allowed = capabilities.value?.standard_garment_photo_types ?? []

  return fashionTryOnGarmentPhotoTypeOptions.filter((option) => allowed.length === 0 || allowed.includes(option.value))
})
const standardModeChoices = computed(() => {
  const allowed = capabilities.value?.standard_modes ?? []

  return fashionTryOnStandardModeOptions.filter((option) => allowed.length === 0 || allowed.includes(option.value))
})
const formBlockingReason = computed(() => {
  if (isSubmitting.value) {
    return 'Generation is already being submitted.'
  }

  if (isUpgradeRequired.value) {
    return ''
  }

  if (tier.value === 'standard' && !standardEnabled.value) {
    return 'Standard Fashion Try-On is temporarily unavailable.'
  }

  if (tier.value === 'premium' && !premiumEnabled.value) {
    return 'Premium is coming after Standard validation.'
  }

  if (personImageAssetId.value === null) {
    return 'Choose or upload one person photo first.'
  }

  if (productImageAssetId.value === null) {
    return 'Choose or upload one clothing/product photo first.'
  }

  if (!consentConfirmed.value) {
    return 'Confirm that you have permission to use this person photo.'
  }

  return ''
})
const canSubmit = computed(() => formBlockingReason.value === '')
const generateButtonLabel = computed(() => {
  if (isSubmitting.value) {
    return 'Submitting try-on...'
  }

  if (isUpgradeRequired.value) {
    return 'View plan options'
  }

  return tier.value === 'premium' ? 'Generate Premium Try-On' : 'Generate Standard Try-On'
})

function isFashionTryOnCapabilities(value: unknown): value is FashionTryOnProviderCapabilities {
  return typeof value === 'object' && value !== null && 'standard_supported' in value && 'premium_supported' in value
}

function selectTier(value: FashionTryOnTier): void {
  formError.value = ''

  if (value === 'premium' && !premiumEnabled.value) {
    tier.value = 'standard'
    return
  }

  tier.value = value
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, actionResponse, capabilityResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      getProjectActions(projectId.value),
      getFashionTryOnCapabilities(),
    ])

    project.value = loadedProject
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
    fetchedCapabilities.value = capabilityResponse.data
    applyQueryPreselection()
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : 'We could not load AI Fashion Try-On setup.'
  } finally {
    isLoading.value = false
  }
}

function applyQueryPreselection(): void {
  const personId = positiveInt(Array.isArray(route.query.personImageAssetId) ? route.query.personImageAssetId[0] : route.query.personImageAssetId)
  const productId = positiveInt(Array.isArray(route.query.productImageAssetId) ? route.query.productImageAssetId[0] : route.query.productImageAssetId)

  if (personId !== null) {
    personImageAssetId.value = personId
  }

  if (productId !== null) {
    productImageAssetId.value = productId
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isSubmitting.value) {
    return
  }

  if (isUpgradeRequired.value) {
    await router.push(fashionAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (!canSubmit.value) {
    formError.value = formBlockingReason.value
    return
  }

  if (personImageAssetId.value === null || productImageAssetId.value === null) {
    formError.value = 'Choose both required images first.'
    return
  }

  isSubmitting.value = true

  const payload: GenerateFashionTryOnPayload = {
    tier: tier.value,
    person_image_asset_id: personImageAssetId.value,
    product_image_asset_id: productImageAssetId.value,
    category: tier.value === 'standard' ? category.value : null,
    garment_photo_type: tier.value === 'standard' ? garmentPhotoType.value : null,
    standard_mode: tier.value === 'standard' ? standardMode.value : null,
    quality_preset: null,
    prompt: null,
    person_image_permission_confirmed: consentConfirmed.value,
  }

  try {
    const response = await generateFashionTryOn(projectId.value, payload)
    await router.push(`/projects/${projectId.value}/generations/${response.data.id}`)
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      formError.value = 'You do not have enough credits to generate this try-on.'
    } else {
      formError.value = error instanceof ApiError ? error.message : 'We could not generate this try-on.'
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadPage)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-4xl px-5 pb-28 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-4 w-36 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-8 h-80 animate-pulse rounded-[2rem] bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">Loading AI Fashion Try-On setup...</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">AI Fashion Try-On unavailable</p>
        <h1 class="mt-3 text-3xl font-bold">This setup needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button type="button" class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950" @click="loadPage">
          Try again
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300">
            ← Back to workspace
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">AI Fashion Try-On</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            Combine one person photo and one clothing or wearable product image. Standard is ready for honest mock mode and controlled internal FASHN Standard validation.
          </p>
          <p class="mt-4 max-w-2xl rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-4 text-sm leading-6 text-emerald-100/80">
            {{ providerLabel }} · one output image · {{ creditCost }} {{ creditCost === 1 ? 'credit' : 'credits' }}.
          </p>
        </header>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
          <section class="nusa-card-premium rounded-[2rem] p-5">
            <fieldset>
              <legend>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">1 · Quality</span>
                <span class="mt-2 block text-xl font-semibold text-white">Choose quality tier</span>
              </legend>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                Standard uses the validated first provider path. Premium is prepared in code but stays unavailable until tryon-max is manually validated.
              </p>

              <div class="mt-5 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Fashion Try-On quality tier">
                <button
                  v-for="option in fashionTryOnTierOptions"
                  :key="option.value"
                  type="button"
                  role="radio"
                  :aria-checked="tier === option.value"
                  :disabled="option.value === 'premium' && !premiumEnabled"
                  class="rounded-[1.5rem] border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-55"
                  :class="tier === option.value ? 'border-emerald-400 bg-emerald-950/30' : 'border-neutral-800 bg-neutral-950/60 hover:border-neutral-600'"
                  :data-testid="`fashion-tier-${option.value}`"
                  @click="selectTier(option.value)"
                >
                  <span class="flex items-start justify-between gap-3">
                    <span>
                      <span class="block text-lg font-semibold text-white">{{ option.label }}</span>
                      <span class="mt-2 block text-sm leading-6 text-neutral-400">{{ option.description }}</span>
                    </span>
                    <span class="rounded-full border border-neutral-700 px-3 py-1 text-xs font-semibold text-neutral-300">
                      {{ option.value === 'premium' && !premiumEnabled ? 'Coming next' : tier === option.value ? 'Selected' : 'Choose' }}
                    </span>
                  </span>
                </button>
              </div>
            </fieldset>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">2 · Person photo</p>
            <ProjectAssetInput
              v-model="personImageAssetId"
              :project-id="projectId"
              accepted-types="image"
              usage-role="model_reference"
              title="Person photo *"
              helper-text="Use a clear photo where the person and outfit area are visible."
              required
              auto-select-first
              :disabled="isSubmitting"
              data-testid="fashion-person-asset-input"
            />
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">3 · Clothing/product photo</p>
            <ProjectAssetInput
              v-model="productImageAssetId"
              :project-id="projectId"
              accepted-types="image"
              usage-role="garment_reference"
              title="Clothing photo *"
              helper-text="Use one clear garment photo, ideally flat-lay, ghost mannequin or a clean product image."
              required
              :disabled="isSubmitting"
              data-testid="fashion-product-asset-input"
            />
          </section>

          <section v-if="tier === 'standard'" class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">4 · Product category</p>
            <h2 class="mt-2 text-xl font-semibold">Help the provider classify the garment</h2>
            <label class="mt-5 block" for="fashion-category">
              <span class="nusa-label">Category</span>
              <select id="fashion-category" v-model="category" class="nusa-select mt-2" data-testid="fashion-category-select">
                <option v-for="option in categoryChoices" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </label>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-4 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300"
              :aria-expanded="advancedOpen"
              aria-controls="fashion-advanced-settings"
              data-testid="fashion-advanced-toggle"
              @click="advancedOpen = !advancedOpen"
            >
              <span>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">5 · Optional settings</span>
                <span class="mt-2 block text-xl font-semibold text-white">Fine-tune Standard inputs</span>
              </span>
              <span class="rounded-full border border-neutral-700 px-3 py-1 text-sm font-semibold text-neutral-300">
                {{ advancedOpen ? 'Hide' : 'Show' }}
              </span>
            </button>

            <div v-if="advancedOpen" id="fashion-advanced-settings" class="mt-5 grid gap-4 sm:grid-cols-2">
              <label class="block">
                <span class="nusa-label">Garment photo type</span>
                <select v-model="garmentPhotoType" class="nusa-select mt-2" data-testid="fashion-garment-photo-type-select">
                  <option v-for="option in garmentPhotoTypeChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="nusa-label">Standard quality</span>
                <select v-model="standardMode" class="nusa-select mt-2" data-testid="fashion-standard-mode-select">
                  <option v-for="option in standardModeChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
            </div>

            <p class="mt-4 text-xs leading-5 text-neutral-500">
              Nusa controls provider model, moderation and output count server-side. The UI cannot submit arbitrary model names.
            </p>
          </section>

          <section class="rounded-[2rem] border border-amber-900/60 bg-amber-950/20 p-5">
            <label class="flex items-start gap-3 text-sm leading-6 text-amber-100/90">
              <input v-model="consentConfirmed" type="checkbox" class="mt-1 size-4 accent-emerald-400" data-testid="fashion-consent-checkbox" :disabled="isSubmitting">
              <span>
                <span class="block font-semibold text-amber-100">Permission confirmation *</span>
                <span class="mt-1 block">I confirm that I have permission to use this person’s photo for AI fashion try-on.</span>
              </span>
            </label>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">6 · Cost and generate</p>
                <h2 class="mt-2 text-xl font-semibold">{{ selectedTierOption.label }} Try-On</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-400">
                  One explicit click creates one generation. Standard outputs exactly one image; mock mode creates a preparation plan only.
                </p>
              </div>
              <span class="w-fit rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-300">
                {{ creditCost }} {{ creditCost === 1 ? 'credit' : 'credits' }}
              </span>
            </div>

            <p v-if="formError" role="alert" class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm leading-6 text-rose-100" data-testid="fashion-form-error">
              {{ formError }}
            </p>

            <button
              type="submit"
              class="mt-5 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 text-sm font-black transition"
              :class="canSubmit || isUpgradeRequired ? 'bg-white text-neutral-950 hover:bg-emerald-300' : 'cursor-not-allowed border border-neutral-800 text-neutral-500'"
              :disabled="isSubmitting || (!canSubmit && !isUpgradeRequired)"
              data-testid="fashion-generate-button"
            >
              <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
              {{ generateButtonLabel }}
            </button>
            <p v-if="formBlockingReason && !isUpgradeRequired" class="mt-3 text-xs leading-5 text-neutral-500" data-testid="fashion-blocking-reason">
              {{ formBlockingReason }}
            </p>
          </section>
        </form>
      </template>
    </div>
  </main>
</template>
