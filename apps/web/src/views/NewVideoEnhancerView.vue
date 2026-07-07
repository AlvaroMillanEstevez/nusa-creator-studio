<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createVideoEnhancerProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  videoEnhancerStyleOptions,
  type VideoEnhancerForm,
} from '@/types/project'

type FormErrors = Partial<Record<keyof VideoEnhancerForm, string>>

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FormErrors>({})

const form = reactive<VideoEnhancerForm>({
  video_title: '',
  product_name: '',
  video_context: '',
  spoken_summary: '',
  target_audience: '',
  language: 'english',
  style: 'natural',
})

function validateForm(): boolean {
  const errors: FormErrors = {}

  if (!form.video_title.trim()) {
    errors.video_title = 'Add a title for the recorded video.'
  } else if (form.video_title.trim().length > 255) {
    errors.video_title = 'Keep the video title under 255 characters.'
  }

  if (!form.product_name.trim()) {
    errors.product_name = 'Add the product shown in the video.'
  }

  if (!form.video_context.trim()) {
    errors.video_context = 'Describe what happens in the recorded video.'
  }

  if (!form.spoken_summary.trim()) {
    errors.spoken_summary = 'Summarize what is said in the recording.'
  }

  if (!form.target_audience.trim()) {
    errors.target_audience = 'Describe who should see this video.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitBrief(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) {
    return
  }

  isSubmitting.value = true

  try {
    const project = await createVideoEnhancerProject({
      video_title: form.video_title.trim(),
      product_name: form.product_name.trim(),
      video_context: form.video_context.trim(),
      spoken_summary: form.spoken_summary.trim(),
      target_audience: form.target_audience.trim(),
      language: form.language,
      style: form.style,
    })

    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the video brief. Check the API connection and try again.'
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
        <p class="text-sm font-medium text-emerald-400">Video Enhancer / Caption Pack</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Turn your recorded video brief into an edit-ready pack.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Describe the footage and spoken content. This mock version does not upload or inspect a
          video file.
        </p>
      </header>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitBrief">
        <div>
          <label for="video-title" class="nusa-label">
            Video title
          </label>
          <input
            id="video-title"
            v-model="form.video_title"
            name="video_title"
            type="text"
            maxlength="255"
            placeholder="e.g. Morning desk setup demo"
            :aria-invalid="Boolean(fieldErrors.video_title)"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.video_title" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.video_title }}
          </p>
        </div>

        <div>
          <label for="product-name" class="nusa-label">
            Product name
          </label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            placeholder="e.g. Portable key light"
            :aria-invalid="Boolean(fieldErrors.product_name)"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.product_name" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.product_name }}
          </p>
        </div>

        <div>
          <label for="video-context" class="nusa-label">
            What happens in the video?
          </label>
          <textarea
            id="video-context"
            v-model="form.video_context"
            name="video_context"
            rows="4"
            placeholder="Describe the shots, product demonstration and ending."
            :aria-invalid="Boolean(fieldErrors.video_context)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.video_context" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.video_context }}
          </p>
        </div>

        <div>
          <label for="spoken-summary" class="nusa-label">
            Spoken summary
          </label>
          <textarea
            id="spoken-summary"
            v-model="form.spoken_summary"
            name="spoken_summary"
            rows="4"
            placeholder="Summarize the words already spoken in the recording."
            :aria-invalid="Boolean(fieldErrors.spoken_summary)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.spoken_summary" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.spoken_summary }}
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
            placeholder="e.g. Creators improving small filming spaces"
            :aria-invalid="Boolean(fieldErrors.target_audience)"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.target_audience" class="mt-2 text-sm text-rose-400">
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
          <legend class="nusa-label">Editing style</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label
              v-for="option in videoEnhancerStyleOptions"
              :key="option.value"
              class="nusa-choice flex items-center"
              :class="
                form.style === option.value
                  ? 'nusa-choice-active'
                  : 'nusa-choice-idle'
              "
            >
              <input
                v-model="form.style"
                class="sr-only"
                type="radio"
                name="style"
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
          {{ isSubmitting ? 'Creating brief...' : 'Create video enhancer project' }}
        </button>
      </form>
    </div>
  </main>
</template>
