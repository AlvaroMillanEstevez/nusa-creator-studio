<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateFashionTryOn, getCreatorProject, getFashionTryOnCapabilities, getProjectActions } from '@/services/projects'
import {
  fashionTryOnCategoryOptions,
  fashionTryOnGarmentPhotoTypeOptions,
  fashionTryOnPackageOptions,
  fashionTryOnPersonSourceOptions,
  fashionTryOnStandardModeOptions,
  fashionTryOnTierOptions,
  type CreatorProject,
  type FashionTryOnCategory,
  type FashionTryOnGarmentPhotoType,
  type FashionTryOnPackageKey,
  type FashionTryOnPersonSource,
  type FashionTryOnProviderCapabilities,
  type FashionTryOnStandardMode,
  type FashionTryOnTier,
  type GenerateFashionTryOnPayload,
  type ProjectAction,
} from '@/types/project'
import { focusFirstInvalidField } from '@/utils/forms'
import { positiveInt } from '@/utils/workflowLineage'

type FashionField =
  | 'package_key'
  | 'person_source'
  | 'tier'
  | 'person_image_asset_id'
  | 'product_image_asset_id'
  | 'person_image_permission_confirmed'

type FashionFieldErrors = Partial<Record<FashionField, string>>

const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const fetchedCapabilities = ref<FashionTryOnProviderCapabilities | null>(null)
const formElement = ref<HTMLFormElement | null>(null)
const packageKey = ref<FashionTryOnPackageKey>('single_result')
const personSource = ref<FashionTryOnPersonSource>('user_upload')
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
const fieldErrors = ref<FashionFieldErrors>({})

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
const providerLabel = computed(() => isMockMode.value ? t('fashionTryOn.providerMock') : t('fashionTryOn.providerFashnStandard'))
const selectedTierOption = computed(() => fashionTryOnTierOptions.find((option) => option.value === tier.value) ?? fashionTryOnTierOptions[0])
const activePackages = computed(() => capabilities.value?.active_packages ?? ['single_result'])
const activePersonSources = computed(() => capabilities.value?.active_person_sources ?? ['user_upload'])
const packageChoices = computed(() => fashionTryOnPackageOptions.map((option) => ({
  ...option,
  isEnabled: activePackages.value.includes(option.value),
})))
const personSourceChoices = computed(() => fashionTryOnPersonSourceOptions.map((option) => ({
  ...option,
  isEnabled: activePersonSources.value.includes(option.value),
})))
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
const firstValidationMessage = computed(() => Object.values(fieldErrors.value)[0] ?? '')
const formBlockingReason = computed(() => {
  if (isSubmitting.value) {
    return t('fashionTryOn.submittingAlready')
  }

  if (isUpgradeRequired.value) {
    return ''
  }

  return firstValidationMessage.value
})
const generateButtonLabel = computed(() => {
  if (isSubmitting.value) {
    return t('fashionTryOn.submitting')
  }

  if (isUpgradeRequired.value) {
    return t('fashionTryOn.viewPlanOptions')
  }

  return tier.value === 'premium' ? t('fashionTryOn.generatePremium') : t('fashionTryOn.generateStandard')
})

function isFashionTryOnCapabilities(value: unknown): value is FashionTryOnProviderCapabilities {
  return typeof value === 'object' && value !== null && 'standard_supported' in value && 'premium_supported' in value
}

function isPackageEnabled(value: FashionTryOnPackageKey): boolean {
  return activePackages.value.includes(value)
}

function isPersonSourceEnabled(value: FashionTryOnPersonSource): boolean {
  return activePersonSources.value.includes(value)
}

function selectPackage(value: FashionTryOnPackageKey): void {
  formError.value = ''
  fieldErrors.value = {}

  if (!isPackageEnabled(value)) {
    return
  }

  packageKey.value = value
}

function selectPersonSource(value: FashionTryOnPersonSource): void {
  formError.value = ''
  fieldErrors.value = {}

  if (!isPersonSourceEnabled(value)) {
    return
  }

  personSource.value = value
}

function selectTier(value: FashionTryOnTier): void {
  formError.value = ''
  fieldErrors.value = {}

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
    loadError.value = error instanceof ApiError ? error.message : t('fashionTryOn.loadFailed')
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

function validateForm(): boolean {
  const errors: FashionFieldErrors = {}

  if (!isPackageEnabled(packageKey.value)) {
    errors.package_key = t('fashionTryOn.errors.packageUnavailable')
  }

  if (!isPersonSourceEnabled(personSource.value)) {
    errors.person_source = t('fashionTryOn.errors.personSourceUnavailable')
  }

  if (tier.value === 'standard' && !standardEnabled.value) {
    errors.tier = t('fashionTryOn.errors.standardUnavailable')
  }

  if (tier.value === 'premium' && !premiumEnabled.value) {
    errors.tier = t('fashionTryOn.errors.premiumDisabled')
  }

  if (personSource.value === 'user_upload' && personImageAssetId.value === null) {
    errors.person_image_asset_id = t('fashionTryOn.errors.personPhotoRequired')
  }

  if (productImageAssetId.value === null) {
    errors.product_image_asset_id = t('fashionTryOn.errors.productPhotoRequired')
  }

  if (personSource.value === 'user_upload' && !consentConfirmed.value) {
    errors.person_image_permission_confirmed = t('fashionTryOn.errors.consentRequired')
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
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

  if (!validateForm()) {
    formError.value = firstValidationMessage.value
    void focusFirstInvalidField(formElement.value)
    return
  }

  if (personImageAssetId.value === null || productImageAssetId.value === null) {
    formError.value = t('fashionTryOn.errors.imagesRequired')
    void focusFirstInvalidField(formElement.value)
    return
  }

  isSubmitting.value = true

  const payload: GenerateFashionTryOnPayload = {
    package_key: packageKey.value,
    person_source: personSource.value,
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
      formError.value = t('fashionTryOn.errors.insufficientCredits')
    } else {
      formError.value = error instanceof ApiError ? error.message : t('fashionTryOn.errors.generateFailed')
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
        <p class="mt-5 text-sm text-neutral-500">{{ t('fashionTryOn.loading') }}</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">{{ t('fashionTryOn.unavailable') }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ t('fashionTryOn.retryTitle') }}</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button type="button" class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950" @click="loadPage">
          {{ t('common.tryAgain') }}
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300">
            {{ t('projectActions.backToWorkspace') }}
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ t('fashionTryOn.title') }}</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">
            {{ t('fashionTryOn.body') }}
          </p>
          <p class="mt-4 max-w-2xl rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-4 text-sm leading-6 text-emerald-100/80">
            {{ providerLabel }} · {{ t('fashionTryOn.oneOutputImage') }} · {{ creditCost }} {{ creditCost === 1 ? t('projectActions.oneCredit') : t('projectActions.manyCredits', { credits: creditCost }) }}.
          </p>
        </header>

        <form ref="formElement" class="mt-8 space-y-5" novalidate @submit.prevent="submit">
          <section class="nusa-card-premium rounded-[2rem] p-5" :data-invalid="Boolean(fieldErrors.package_key)">
            <fieldset>
              <legend>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">1 · {{ t('fashionTryOn.packageStep') }}</span>
                <span class="mt-2 block text-xl font-semibold text-white">{{ t('fashionTryOn.packageTitle') }}</span>
              </legend>
              <div class="mt-5 grid gap-3 sm:grid-cols-2" role="radiogroup" :aria-label="t('fashionTryOn.packageTitle')">
                <button
                  v-for="option in packageChoices"
                  :key="option.value"
                  type="button"
                  role="radio"
                  :aria-checked="packageKey === option.value"
                  :disabled="!option.isEnabled"
                  class="rounded-[1.5rem] border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-55"
                  :class="packageKey === option.value ? 'border-emerald-400 bg-emerald-950/30' : 'border-neutral-800 bg-neutral-950/60 hover:border-neutral-600'"
                  :data-testid="`fashion-package-${option.value}`"
                  @click="selectPackage(option.value)"
                >
                  <span class="flex items-start justify-between gap-3">
                    <span>
                      <span class="block text-base font-semibold text-white">{{ t('fashionTryOn.packages.' + option.value + '.label') }}</span>
                      <span class="mt-2 block text-sm leading-6 text-neutral-400">{{ t('fashionTryOn.packages.' + option.value + '.description') }}</span>
                    </span>
                    <span class="rounded-full border border-neutral-700 px-3 py-1 text-xs font-semibold text-neutral-300">
                      {{ option.isEnabled ? t('fashionTryOn.availableNow') : t('common.comingSoon') }}
                    </span>
                  </span>
                </button>
              </div>
              <p v-if="fieldErrors.package_key" id="fashion-package-error" class="mt-3 text-sm text-rose-400">{{ fieldErrors.package_key }}</p>
            </fieldset>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5" :data-invalid="Boolean(fieldErrors.tier)">
            <fieldset>
              <legend>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">2 · {{ t('fashionTryOn.qualityStep') }}</span>
                <span class="mt-2 block text-xl font-semibold text-white">{{ t('fashionTryOn.qualityTitle') }}</span>
              </legend>
              <p class="mt-2 text-sm leading-6 text-neutral-400">
                {{ t('fashionTryOn.qualityBody') }}
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
                      {{ option.value === 'premium' && !premiumEnabled ? t('fashionTryOn.premiumValidation') : tier === option.value ? t('common.selected') : t('common.choose') }}
                    </span>
                  </span>
                </button>
              </div>
              <p v-if="fieldErrors.tier" id="fashion-tier-error" class="mt-3 text-sm text-rose-400">{{ fieldErrors.tier }}</p>
            </fieldset>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5" :data-invalid="Boolean(fieldErrors.person_source)">
            <fieldset>
              <legend>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">3 · {{ t('fashionTryOn.personSourceStep') }}</span>
                <span class="mt-2 block text-xl font-semibold text-white">{{ t('fashionTryOn.personSourceTitle') }}</span>
              </legend>
              <div class="mt-5 grid gap-3 sm:grid-cols-3" role="radiogroup" :aria-label="t('fashionTryOn.personSourceTitle')">
                <button
                  v-for="option in personSourceChoices"
                  :key="option.value"
                  type="button"
                  role="radio"
                  :aria-checked="personSource === option.value"
                  :disabled="!option.isEnabled"
                  class="rounded-[1.5rem] border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-55"
                  :class="personSource === option.value ? 'border-emerald-400 bg-emerald-950/30' : 'border-neutral-800 bg-neutral-950/60 hover:border-neutral-600'"
                  :data-testid="`fashion-person-source-${option.value}`"
                  @click="selectPersonSource(option.value)"
                >
                  <span class="block text-sm font-semibold text-white">{{ t('fashionTryOn.personSources.' + option.value + '.label') }}</span>
                  <span class="mt-2 block text-xs leading-5 text-neutral-400">{{ t('fashionTryOn.personSources.' + option.value + '.description') }}</span>
                  <span class="mt-3 inline-flex rounded-full border border-neutral-700 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ option.isEnabled ? t('fashionTryOn.availableNow') : t('common.comingSoon') }}
                  </span>
                </button>
              </div>
              <p v-if="fieldErrors.person_source" id="fashion-person-source-error" class="mt-3 text-sm text-rose-400">{{ fieldErrors.person_source }}</p>
            </fieldset>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5" :data-invalid="Boolean(fieldErrors.person_image_asset_id)">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">4 · {{ t('fashionTryOn.personPhotoStep') }}</p>
            <ProjectAssetInput
              v-model="personImageAssetId"
              :project-id="projectId"
              accepted-types="image"
              usage-role="model_reference"
              :title="t('fashionTryOn.personPhotoTitle')"
              :helper-text="t('fashionTryOn.personPhotoHelp')"
              required
              auto-select-first
              :disabled="isSubmitting"
              data-testid="fashion-person-asset-input"
            />
            <p v-if="fieldErrors.person_image_asset_id" id="fashion-person-image-error" class="mt-3 text-sm text-rose-400">{{ fieldErrors.person_image_asset_id }}</p>

            <details class="mt-4 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
              <summary class="cursor-pointer text-sm font-semibold text-neutral-200">{{ t('fashionTryOn.personGuidanceTitle') }}</summary>
              <div class="mt-4 grid gap-4 text-sm leading-6 sm:grid-cols-2">
                <div>
                  <p class="font-semibold text-emerald-200">{{ t('fashionTryOn.goodPersonPhoto') }}</p>
                  <ul class="mt-2 space-y-1 text-neutral-400">
                    <li v-for="item in ['onePerson', 'torsoVisible', 'clearLighting', 'clothingAreaVisible', 'frontPose']" :key="item">&bull; {{ t('fashionTryOn.personGuidance.good.' + item) }}</li>
                  </ul>
                </div>
                <div>
                  <p class="font-semibold text-amber-200">{{ t('fashionTryOn.avoidPersonPhoto') }}</p>
                  <ul class="mt-2 space-y-1 text-neutral-400">
                    <li v-for="item in ['faceOnly', 'extremeCrop', 'bodyHidden', 'multiplePeople', 'blurred']" :key="item">&bull; {{ t('fashionTryOn.personGuidance.avoid.' + item) }}</li>
                  </ul>
                </div>
              </div>
            </details>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5" :data-invalid="Boolean(fieldErrors.product_image_asset_id)">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">5 · {{ t('fashionTryOn.productPhotoStep') }}</p>
            <ProjectAssetInput
              v-model="productImageAssetId"
              :project-id="projectId"
              accepted-types="image"
              usage-role="garment_reference"
              :title="t('fashionTryOn.productPhotoTitle')"
              :helper-text="t('fashionTryOn.productPhotoHelp')"
              required
              :disabled="isSubmitting"
              data-testid="fashion-product-asset-input"
            />
            <p v-if="fieldErrors.product_image_asset_id" id="fashion-product-image-error" class="mt-3 text-sm text-rose-400">{{ fieldErrors.product_image_asset_id }}</p>
          </section>

          <section v-if="tier === 'standard'" class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">6 · {{ t('fashionTryOn.categoryStep') }}</p>
            <h2 class="mt-2 text-xl font-semibold">{{ t('fashionTryOn.categoryTitle') }}</h2>
            <label class="mt-5 block" for="fashion-category">
              <span class="nusa-label">{{ t('fashionTryOn.categoryLabel') }}</span>
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
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">7 · {{ t('fashionTryOn.optionalStep') }}</span>
                <span class="mt-2 block text-xl font-semibold text-white">{{ t('fashionTryOn.optionalTitle') }}</span>
              </span>
              <span class="rounded-full border border-neutral-700 px-3 py-1 text-sm font-semibold text-neutral-300">
                {{ advancedOpen ? t('common.hide') : t('common.show') }}
              </span>
            </button>

            <div v-if="advancedOpen" id="fashion-advanced-settings" class="mt-5 grid gap-4 sm:grid-cols-2">
              <label class="block">
                <span class="nusa-label">{{ t('fashionTryOn.garmentPhotoType') }}</span>
                <select v-model="garmentPhotoType" class="nusa-select mt-2" data-testid="fashion-garment-photo-type-select">
                  <option v-for="option in garmentPhotoTypeChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="block">
                <span class="nusa-label">{{ t('fashionTryOn.standardQuality') }}</span>
                <select v-model="standardMode" class="nusa-select mt-2" data-testid="fashion-standard-mode-select">
                  <option v-for="option in standardModeChoices" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
            </div>

            <p class="mt-4 text-xs leading-5 text-neutral-500">
              {{ t('fashionTryOn.serverControls') }}
            </p>
          </section>

          <section class="rounded-[2rem] border border-amber-900/60 bg-amber-950/20 p-5" :data-invalid="Boolean(fieldErrors.person_image_permission_confirmed)">
            <label class="flex items-start gap-3 text-sm leading-6 text-amber-100/90">
              <input
                v-model="consentConfirmed"
                type="checkbox"
                class="mt-1 size-4 accent-emerald-400"
                data-testid="fashion-consent-checkbox"
                :disabled="isSubmitting"
                :aria-invalid="Boolean(fieldErrors.person_image_permission_confirmed)"
                :aria-describedby="fieldErrors.person_image_permission_confirmed ? 'fashion-consent-error' : undefined"
              >
              <span>
                <span class="block font-semibold text-amber-100">8 · {{ t('fashionTryOn.permissionTitle') }}</span>
                <span class="mt-1 block">{{ t('fashionTryOn.permissionBody') }}</span>
              </span>
            </label>
            <p v-if="fieldErrors.person_image_permission_confirmed" id="fashion-consent-error" class="mt-3 text-sm text-rose-300">{{ fieldErrors.person_image_permission_confirmed }}</p>
          </section>

          <section class="nusa-card-premium rounded-[2rem] p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">9 · {{ t('fashionTryOn.costStep') }}</p>
                <h2 class="mt-2 text-xl font-semibold">{{ selectedTierOption.label }} · {{ t('fashionTryOn.packages.' + packageKey + '.label') }}</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-400">
                  {{ t('fashionTryOn.costBody') }}
                </p>
              </div>
              <span class="w-fit rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-300">
                {{ creditCost }} {{ creditCost === 1 ? t('projectActions.oneCredit') : t('projectActions.manyCredits', { credits: creditCost }) }}
              </span>
            </div>

            <p v-if="formError" role="alert" class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm leading-6 text-rose-100" data-testid="fashion-form-error">
              {{ formError }}
            </p>

            <button
              type="submit"
              class="mt-5 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 text-sm font-black transition"
              :class="isSubmitting ? 'cursor-not-allowed border border-neutral-800 text-neutral-500' : 'bg-white text-neutral-950 hover:bg-emerald-300'"
              :disabled="isSubmitting"
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
