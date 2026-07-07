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
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <header class="mt-10">
        <p class="text-sm font-medium text-emerald-400">Fashion Try-On</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Prepare a product-model try-on concept.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Create a mock try-on project first. After saving it, upload a model/person reference and a
          garment/product reference before generating the placeholder plan.
        </p>
      </header>

      <form class="mt-9 space-y-7" novalidate @submit.prevent="submitTryOn">
        <div>
          <label for="try-on-title" class="text-sm font-semibold text-neutral-200">Project title</label>
          <input
            id="try-on-title"
            v-model="form.title"
            name="title"
            type="text"
            maxlength="255"
            placeholder="e.g. Streetwear jacket try-on"
            :aria-invalid="Boolean(fieldErrors.title)"
            class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500"
          />
          <p v-if="fieldErrors.title" class="mt-2 text-sm text-rose-400">{{ fieldErrors.title }}</p>
        </div>

        <div>
          <label for="product-name" class="text-sm font-semibold text-neutral-200">Product name</label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            placeholder="e.g. Everyday creator jacket"
            class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500"
          />
        </div>

        <div>
          <label for="product-description" class="text-sm font-semibold text-neutral-200">Product description</label>
          <textarea
            id="product-description"
            v-model="form.product_description"
            name="product_description"
            rows="4"
            placeholder="Describe the garment honestly. Avoid guaranteed fit claims."
            :aria-invalid="Boolean(fieldErrors.product_description)"
            class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500"
          />
          <p v-if="fieldErrors.product_description" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.product_description }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="garment-type" class="text-sm font-semibold text-neutral-200">Garment</label>
            <select id="garment-type" v-model="form.garment_type" name="garment_type" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in fashionGarmentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="language" class="text-sm font-semibold text-neutral-200">Language</label>
            <select id="language" v-model="form.language" name="language" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="style-goal" class="text-sm font-semibold text-neutral-200">Style goal</label>
            <select id="style-goal" v-model="form.style_goal" name="style_goal" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in fashionStyleGoalOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="output-style" class="text-sm font-semibold text-neutral-200">Output style</label>
            <select id="output-style" v-model="form.output_style" name="output_style" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in fashionOutputStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div>
          <label for="platform" class="text-sm font-semibold text-neutral-200">Platform</label>
          <select id="platform" v-model="form.platform" name="platform" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none focus:border-emerald-500">
            <option v-for="option in fashionPlatformOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="target-audience" class="text-sm font-semibold text-neutral-200">Target audience</label>
          <textarea id="target-audience" v-model="form.target_audience" name="target_audience" rows="3" placeholder="Who is this try-on concept for?" class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
        </div>

        <div>
          <label for="background" class="text-sm font-semibold text-neutral-200">Background preference</label>
          <textarea id="background" v-model="form.background_preference" name="background_preference" rows="3" placeholder="Clean studio, streetwear wall, ecommerce white, etc." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
        </div>

        <div class="grid gap-5">
          <div>
            <label for="model-notes" class="text-sm font-semibold text-neutral-200">Model notes</label>
            <textarea id="model-notes" v-model="form.model_notes" name="model_notes" rows="3" placeholder="Pose, framing, body crop, or safety notes." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
          <div>
            <label for="garment-notes" class="text-sm font-semibold text-neutral-200">Garment notes</label>
            <textarea id="garment-notes" v-model="form.garment_notes" name="garment_notes" rows="3" placeholder="Details that should stay visible: collar, zipper, pattern, color." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
          <div>
            <label for="notes" class="text-sm font-semibold text-neutral-200">Notes</label>
            <textarea id="notes" v-model="form.notes" name="notes" rows="3" placeholder="Campaign constraints, words to avoid, or creator direction." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
        </div>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200">
          {{ submitError }}
        </div>

        <button type="submit" :disabled="isSubmitting" class="flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? 'Creating try-on project...' : 'Create try-on project' }}
        </button>
      </form>
    </div>
  </main>
</template>