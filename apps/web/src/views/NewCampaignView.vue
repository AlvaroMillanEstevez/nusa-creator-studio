<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { uploadMediaAsset } from '@/services/media'
import { createCampaignProject, updateProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  campaignToneOptions,
  type CampaignFormPayload,
  type CampaignInputMode,
} from '@/types/project'
import { MEDIA_MAX_SIZE_BYTES, validateMediaFile } from '@/utils/mediaValidation'

type FormErrorKey = keyof CampaignFormPayload | 'product_image'
type FormErrors = Partial<Record<FormErrorKey, string>>
type ContentKitInputMode = CampaignInputMode

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const imageAssetError = ref('')
const fieldErrors = ref<FormErrors>({})
const inputMode = ref<ContentKitInputMode>('brief_only')
const selectedImageFile = ref<File | null>(null)
const imageFileInput = ref<HTMLInputElement | null>(null)

const form = reactive<CampaignFormPayload>({
  product_name: '',
  product_description: '',
  target_audience: '',
  language: 'english',
  tone: 'natural',
})

const submitLabel = computed(() => {
  if (inputMode.value === 'video_analysis') {
    return 'Continue to video analysis'
  }

  return 'Generate Product Content Kit'
})

function validateForm(): boolean {
  const errors: FormErrors = {}

  if (!form.product_name.trim()) {
    errors.product_name = 'Add the product name.'
  } else if (form.product_name.trim().length > 255) {
    errors.product_name = 'Keep the product name under 255 characters.'
  }

  if (!form.product_description.trim()) {
    errors.product_description = 'Describe the product and its main benefit.'
  }

  if (!form.target_audience.trim()) {
    errors.target_audience = 'Describe who should see this content.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

function chooseProductImage(event: Event): void {
  imageAssetError.value = ''
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) {
    selectedImageFile.value = null
    return
  }

  const validationError = validateMediaFile(file)

  if (validationError) {
    imageAssetError.value = validationError
    selectedImageFile.value = null
    input.value = ''
    return
  }

  if (!file.type.startsWith('image/')) {
    imageAssetError.value = 'Choose a JPG, PNG or WEBP product image.'
    selectedImageFile.value = null
    input.value = ''
    return
  }

  selectedImageFile.value = file
}

function clearProductImage(): void {
  selectedImageFile.value = null
  imageAssetError.value = ''

  if (imageFileInput.value) {
    imageFileInput.value.value = ''
  }
}

function campaignPayload(): CampaignFormPayload {
  return {
    campaign_input_mode: inputMode.value,
    product_name: form.product_name.trim(),
    product_description: form.product_description.trim(),
    target_audience: form.target_audience.trim(),
    language: form.language,
    tone: form.tone,
  }
}

async function submitCampaign(): Promise<void> {
  submitError.value = ''
  imageAssetError.value = ''

  if (!validateForm()) {
    return
  }

  if (inputMode.value === 'image_assisted' && selectedImageFile.value === null) {
    imageAssetError.value = 'Choose a product image or switch to Brief only.'
    return
  }

  isSubmitting.value = true

  try {
    const basePayload = campaignPayload()
    const project = await createCampaignProject(basePayload)

    if (inputMode.value === 'video_analysis') {
      await router.push({ name: 'project-video-to-campaign-action', params: { projectId: project.id } })
      return
    }

    if (inputMode.value === 'image_assisted' && selectedImageFile.value !== null) {
      const asset = await uploadMediaAsset(selectedImageFile.value, project.id, 'product_image', 'image')
      const inputPayload: CampaignFormPayload = {
        ...basePayload,
        source_image_asset_id: asset.id,
        product_image_asset_id: asset.id,
        product_image_asset_name: asset.original_name ?? selectedImageFile.value.name,
      }

      await updateProject(project.id, { input_payload: inputPayload })
    }

    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the Product Content Kit. Check the API connection and try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-3xl px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="max-w-2xl">
        <p class="text-sm font-medium text-emerald-400">Product Content Kit</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Turn one product into a reusable content kit.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Start from a brief, a product image, or raw product video. Nusa saves everything into one reusable project workspace.
        </p>
      </section>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitCampaign">
        <fieldset>
          <legend class="nusa-label">Input mode</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <label
              class="nusa-choice flex cursor-pointer items-start gap-3 text-left"
              :class="inputMode === 'brief_only' ? 'nusa-choice-active' : 'nusa-choice-idle'"
            >
              <input v-model="inputMode" class="mt-1 accent-emerald-400" type="radio" value="brief_only" />
              <span>
                <span class="block font-semibold text-neutral-100">Brief only</span>
                <span class="mt-1 block text-xs leading-5 text-neutral-500">Best when you already know the product, audience and angle.</span>
              </span>
            </label>
            <label
              class="nusa-choice flex cursor-pointer items-start gap-3 text-left"
              :class="inputMode === 'image_assisted' ? 'nusa-choice-active' : 'nusa-choice-idle'"
            >
              <input v-model="inputMode" class="mt-1 accent-emerald-400" type="radio" value="image_assisted" />
              <span>
                <span class="block font-semibold text-neutral-100">Product image</span>
                <span class="mt-1 block text-xs leading-5 text-neutral-500">Image-assisted campaign context. This is not deep visual analysis yet.</span>
              </span>
            </label>
            <label
              class="nusa-choice flex cursor-pointer items-start gap-3 text-left"
              :class="inputMode === 'video_analysis' ? 'nusa-choice-active' : 'nusa-choice-idle'"
            >
              <input v-model="inputMode" class="mt-1 accent-emerald-400" type="radio" value="video_analysis" />
              <span>
                <span class="block font-semibold text-neutral-100">Product video analysis</span>
                <span class="mt-1 block text-xs leading-5 text-neutral-500">Analyze product footage for campaign ideas and edit-plan data.</span>
              </span>
            </label>
          </div>
          <p class="mt-3 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-xs leading-5 text-sky-100/80">
            Brief and image-assisted kits create campaign content. Product video analysis can also feed Auto Video Editor when usable cuts are generated.
          </p>
        </fieldset>

        <section v-if="inputMode === 'image_assisted'" class="rounded-3xl border border-neutral-800 bg-neutral-950/50 p-4" data-testid="campaign-image-upload-panel">
          <p class="text-sm font-semibold text-neutral-100">Upload or choose product image</p>
          <p class="mt-1 text-xs leading-5 text-neutral-500">
            Upload a clear product image directly into this new project. Nusa uses it as honest image-assisted campaign context.
          </p>
          <label class="mt-4 flex cursor-pointer flex-col items-center rounded-2xl border border-dashed border-neutral-800 bg-neutral-950/70 px-4 py-6 text-center transition hover:border-emerald-500/60">
            <span class="text-sm font-semibold text-neutral-200">Upload product image</span>
            <span class="mt-1 text-xs leading-5 text-neutral-500">JPG, PNG or WEBP ? max {{ MEDIA_MAX_SIZE_BYTES / 1024 / 1024 }} MB</span>
            <input
              ref="imageFileInput"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              class="sr-only"
              data-testid="campaign-product-image-file"
              @change="chooseProductImage"
            >
          </label>
          <div v-if="selectedImageFile" class="mt-3 rounded-xl border border-neutral-800 bg-neutral-950/70 p-3 text-sm">
            <p class="break-words font-medium text-neutral-100">{{ selectedImageFile.name }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ (selectedImageFile.size / 1024 / 1024).toFixed(1) }} MB selected</p>
            <button type="button" class="mt-2 text-xs font-semibold text-emerald-300 hover:text-emerald-200" @click="clearProductImage">
              Remove image
            </button>
          </div>
          <p v-if="imageAssetError" role="alert" class="mt-3 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">
            {{ imageAssetError }}
          </p>
        </section>

        <section v-if="inputMode === 'video_analysis'" class="rounded-3xl border border-cyan-900/50 bg-cyan-950/20 p-4" data-testid="campaign-video-analysis-panel">
          <p class="text-sm font-semibold text-cyan-100">Product video analysis</p>
          <p class="mt-2 text-xs leading-5 text-cyan-100/75">
            This creates the project first, then opens Product Content Kit ? Video analysis where you can upload or choose the product video inside the workflow form.
          </p>
        </section>

        <div>
          <label for="product-name" class="nusa-label">
            Product name
          </label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            autocomplete="off"
            maxlength="255"
            placeholder="e.g. Portable smoothie blender"
            :aria-invalid="Boolean(fieldErrors.product_name)"
            :aria-describedby="fieldErrors.product_name ? 'product-name-error' : undefined"
            class="nusa-input mt-2"
          />
          <p
            v-if="fieldErrors.product_name"
            id="product-name-error"
            class="mt-2 text-sm text-rose-400"
          >
            {{ fieldErrors.product_name }}
          </p>
        </div>

        <div>
          <label for="product-description" class="nusa-label">
            Product description
          </label>
          <textarea
            id="product-description"
            v-model="form.product_description"
            name="product_description"
            rows="5"
            placeholder="What does it do, and why would someone want it?"
            :aria-invalid="Boolean(fieldErrors.product_description)"
            :aria-describedby="
              fieldErrors.product_description ? 'product-description-error' : undefined
            "
            class="nusa-textarea mt-2"
          />
          <p
            v-if="fieldErrors.product_description"
            id="product-description-error"
            class="mt-2 text-sm text-rose-400"
          >
            {{ fieldErrors.product_description }}
          </p>
        </div>

        <div>
          <label for="target-audience" class="nusa-label">
            Target audience
          </label>
          <textarea
            id="target-audience"
            v-model="form.target_audience"
            name="target_audience"
            rows="3"
            placeholder="e.g. Busy students who want healthy breakfasts"
            :aria-invalid="Boolean(fieldErrors.target_audience)"
            :aria-describedby="fieldErrors.target_audience ? 'target-audience-error' : undefined"
            class="nusa-textarea mt-2"
          />
          <p
            v-if="fieldErrors.target_audience"
            id="target-audience-error"
            class="mt-2 text-sm text-rose-400"
          >
            {{ fieldErrors.target_audience }}
          </p>
        </div>

        <div>
          <label for="language" class="nusa-label">Language</label>
          <select
            id="language"
            v-model="form.language"
            name="language"
            class="nusa-select mt-2"
          >
            <option
              v-for="option in campaignLanguageOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <fieldset>
          <legend class="nusa-label">Tone</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label
              v-for="option in campaignToneOptions"
              :key="option.value"
              class="nusa-choice flex items-center"
              :class="
                form.tone === option.value
                  ? 'nusa-choice-active'
                  : 'nusa-choice-idle'
              "
            >
              <input
                v-model="form.tone"
                class="sr-only"
                type="radio"
                name="tone"
                :value="option.value"
              />
              {{ option.label }}
            </label>
          </div>
        </fieldset>

        <div
          v-if="submitError"
          role="alert"
          class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200"
        >
          {{ submitError }}
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span
            v-if="isSubmitting"
            class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
            aria-hidden="true"
          />
          {{ isSubmitting ? 'Creating content kit...' : submitLabel }}
        </button>
      </form>
    </div>
  </main>
</template>
