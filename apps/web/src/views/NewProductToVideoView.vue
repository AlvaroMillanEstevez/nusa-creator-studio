<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createProductToVideoProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  productVideoDurationOptions,
  productVideoGoalOptions,
  productVideoStyleOptions,
  type ProductToVideoForm,
} from '@/types/project'

type TextField = 'product_name' | 'product_description' | 'key_benefits' | 'target_audience'

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<Partial<Record<TextField, string>>>({})

const form = reactive<ProductToVideoForm>({
  product_name: '',
  product_description: '',
  key_benefits: '',
  target_audience: '',
  video_goal: 'product_demo',
  video_style: 'natural',
  duration_seconds: 30,
  language: 'english',
})

function validateForm(): boolean {
  const errors: Partial<Record<TextField, string>> = {}

  if (!form.product_name.trim()) errors.product_name = 'Add the product name.'
  if (!form.product_description.trim()) errors.product_description = 'Describe the product.'
  if (!form.key_benefits.trim()) errors.key_benefits = 'Add the key benefits to demonstrate.'
  if (!form.target_audience.trim()) errors.target_audience = 'Describe the target audience.'

  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

async function submitBrief(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  try {
    const project = await createProductToVideoProject({
      ...form,
      product_name: form.product_name.trim(),
      product_description: form.product_description.trim(),
      key_benefits: form.key_benefits.trim(),
      target_audience: form.target_audience.trim(),
    })

    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the production brief. Check the API connection and try again.'
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
        <p class="text-sm font-medium text-emerald-400">Product-to-Video Script Pack</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Plan a short-form product video before you film.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Create a storyboard, voiceover direction and shot checklist. This produces a script pack,
          not a rendered video.
        </p>
      </header>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitBrief">
        <div>
          <label for="product-name" class="nusa-label">
            Product name
          </label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            maxlength="255"
            placeholder="e.g. Portable key light"
            :aria-invalid="Boolean(fieldErrors.product_name)"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.product_name" class="mt-2 text-sm text-rose-400">
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
            rows="4"
            placeholder="What is the product and how is it used?"
            :aria-invalid="Boolean(fieldErrors.product_description)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.product_description" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.product_description }}
          </p>
        </div>

        <div>
          <label for="key-benefits" class="nusa-label">
            Key benefits
          </label>
          <textarea
            id="key-benefits"
            v-model="form.key_benefits"
            name="key_benefits"
            rows="4"
            placeholder="List only benefits you can verify."
            :aria-invalid="Boolean(fieldErrors.key_benefits)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.key_benefits" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.key_benefits }}
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
            placeholder="Who should this video help?"
            :aria-invalid="Boolean(fieldErrors.target_audience)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.target_audience" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.target_audience }}
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="video-goal" class="nusa-label"
              >Video goal</label
            >
            <select
              id="video-goal"
              v-model="form.video_goal"
              name="video_goal"
              class="nusa-select mt-2"
            >
              <option
                v-for="option in productVideoGoalOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="duration" class="nusa-label">Duration</label>
            <select
              id="duration"
              v-model="form.duration_seconds"
              name="duration_seconds"
              class="nusa-select mt-2"
            >
              <option
                v-for="duration in productVideoDurationOptions"
                :key="duration"
                :value="duration"
              >
                {{ duration }} seconds
              </option>
            </select>
          </div>
        </div>

        <div>
          <label for="video-style" class="nusa-label"
            >Video style</label
          >
          <select
            id="video-style"
            v-model="form.video_style"
            name="video_style"
            class="nusa-select mt-2"
          >
            <option
              v-for="option in productVideoStyleOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
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

        <div
          v-if="submitError"
          role="alert"
          class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200"
        >
          {{ submitError }}
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="flex min-h-14 w-full items-center justify-center rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:bg-neutral-700 disabled:text-neutral-400"
        >
          {{ isSubmitting ? 'Creating script brief...' : 'Create product video project' }}
        </button>
      </form>
    </div>
  </main>
</template>
