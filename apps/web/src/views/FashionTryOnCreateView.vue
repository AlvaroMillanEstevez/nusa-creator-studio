<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createFashionTryOnProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  fashionGarmentTypeOptions,
  fashionOutputStyleOptions,
  fashionPlatformOptions,
  fashionStyleGoalOptions,
  type FashionTryOnFormPayload,
} from '@/types/project'

type FormState = {
  title: string
  product_name: string
  product_description: string
  garment_type: FashionTryOnFormPayload['garment_type']
  target_audience: string
  style_goal: FashionTryOnFormPayload['style_goal']
  output_style: FashionTryOnFormPayload['output_style']
  background_preference: string
  language: FashionTryOnFormPayload['language']
  platform: FashionTryOnFormPayload['platform']
  model_notes: string
  garment_notes: string
  notes: string
}

type FieldErrors = Partial<Record<'title' | 'product_description', string>>

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FieldErrors>({})

const form = reactive<FormState>({
  title: '',
  product_name: '',
  product_description: '',
  garment_type: 'jacket',
  target_audience: '',
  style_goal: 'product_showcase',
  output_style: 'realistic',
  background_preference: '',
  language: 'english',
  platform: 'tiktok',
  model_notes: '',
  garment_notes: '',
  notes: '',
})

function optionalText(value: string): string | null {
  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

function validateForm(): boolean {
  const errors: FieldErrors = {}

  if (!form.title.trim()) {
    errors.title = 'Add a project title.'
  } else if (form.title.trim().length > 255) {
    errors.title = 'Keep the project title under 255 characters.'
  }

  if (!form.product_description.trim()) {
    errors.product_description = 'Describe the garment or product so the mock plan has context.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitTryOn(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  const payload: FashionTryOnFormPayload = {
    title: form.title.trim(),
    product_name: optionalText(form.product_name),
    product_description: optionalText(form.product_description),
    garment_type: form.garment_type,
    target_audience: optionalText(form.target_audience),
    style_goal: form.style_goal,
    output_style: form.output_style,
    model_notes: optionalText(form.model_notes),
    garment_notes: optionalText(form.garment_notes),
    background_preference: optionalText(form.background_preference),
    language: form.language,
    platform: form.platform,
    notes: optionalText(form.notes),
  }

  try {
    const project = await createFashionTryOnProject(payload)
    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the try-on project. Check the API connection and try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-3xl px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <header class="max-w-2xl">
        <p class="text-sm font-medium text-emerald-400">Fashion Try-On</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Prepare a product-model try-on concept.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Create a mock try-on project first. After saving it, upload a model/person reference and a
          garment/product reference before generating the placeholder plan.
        </p>
      </header>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitTryOn">
        <div>
          <label for="try-on-title" class="nusa-label">Project title</label>
          <input
            id="try-on-title"
            v-model="form.title"
            name="title"
            type="text"
            maxlength="255"
            placeholder="e.g. Streetwear jacket try-on"
            :aria-invalid="Boolean(fieldErrors.title)"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.title" class="mt-2 text-sm text-rose-400">{{ fieldErrors.title }}</p>
        </div>

        <div>
          <label for="product-name" class="nusa-label">Product name</label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            placeholder="e.g. Everyday creator jacket"
            class="nusa-input mt-2"
          />
        </div>

        <div>
          <label for="product-description" class="nusa-label">Product description</label>
          <textarea
            id="product-description"
            v-model="form.product_description"
            name="product_description"
            rows="4"
            placeholder="Describe the garment honestly. Avoid guaranteed fit claims."
            :aria-invalid="Boolean(fieldErrors.product_description)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.product_description" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.product_description }}
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="garment-type" class="nusa-label">Garment</label>
            <select id="garment-type" v-model="form.garment_type" name="garment_type" class="nusa-select mt-2">
              <option v-for="option in fashionGarmentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="language" class="nusa-label">Language</label>
            <select id="language" v-model="form.language" name="language" class="nusa-select mt-2">
              <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="style-goal" class="nusa-label">Style goal</label>
            <select id="style-goal" v-model="form.style_goal" name="style_goal" class="nusa-select mt-2">
              <option v-for="option in fashionStyleGoalOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="output-style" class="nusa-label">Output style</label>
            <select id="output-style" v-model="form.output_style" name="output_style" class="nusa-select mt-2">
              <option v-for="option in fashionOutputStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div>
          <label for="platform" class="nusa-label">Platform</label>
          <select id="platform" v-model="form.platform" name="platform" class="nusa-select mt-2">
            <option v-for="option in fashionPlatformOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="target-audience" class="nusa-label">Target audience</label>
          <textarea id="target-audience" v-model="form.target_audience" name="target_audience" rows="3" placeholder="Who is this try-on concept for?" class="nusa-textarea mt-2" />
        </div>

        <div>
          <label for="background" class="nusa-label">Background preference</label>
          <textarea id="background" v-model="form.background_preference" name="background_preference" rows="3" placeholder="Clean studio, streetwear wall, ecommerce white, etc." class="nusa-textarea mt-2" />
        </div>

        <div class="grid gap-5">
          <div>
            <label for="model-notes" class="nusa-label">Model notes</label>
            <textarea id="model-notes" v-model="form.model_notes" name="model_notes" rows="3" placeholder="Pose, framing, body crop, or safety notes." class="nusa-textarea mt-2" />
          </div>
          <div>
            <label for="garment-notes" class="nusa-label">Garment notes</label>
            <textarea id="garment-notes" v-model="form.garment_notes" name="garment_notes" rows="3" placeholder="Details that should stay visible: collar, zipper, pattern, color." class="nusa-textarea mt-2" />
          </div>
          <div>
            <label for="notes" class="nusa-label">Notes</label>
            <textarea id="notes" v-model="form.notes" name="notes" rows="3" placeholder="Campaign constraints, words to avoid, or creator direction." class="nusa-textarea mt-2" />
          </div>
        </div>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200">
          {{ submitError }}
        </div>

        <button type="submit" :disabled="isSubmitting" class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60">
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? 'Creating try-on project...' : 'Create try-on project' }}
        </button>
      </form>
    </div>
  </main>
</template>