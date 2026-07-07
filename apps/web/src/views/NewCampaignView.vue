<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createCampaignProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  campaignToneOptions,
  type CampaignFormPayload,
} from '@/types/project'

type FormErrors = Partial<Record<keyof CampaignFormPayload, string>>

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FormErrors>({})

const form = reactive<CampaignFormPayload>({
  product_name: '',
  product_description: '',
  target_audience: '',
  language: 'english',
  tone: 'natural',
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
    errors.target_audience = 'Describe who should see this campaign.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitCampaign(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) {
    return
  }

  isSubmitting.value = true

  try {
    const project = await createCampaignProject({
      product_name: form.product_name.trim(),
      product_description: form.product_description.trim(),
      target_audience: form.target_audience.trim(),
      language: form.language,
      tone: form.tone,
    })

    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the campaign. Check the API connection and try again.'
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
        <p class="text-sm font-medium text-emerald-400">TikTok affiliate campaign</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Turn one product into your next campaign.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Give us the product context now. Script and video generation will arrive in the next step.
        </p>
      </section>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitCampaign">
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
          {{ isSubmitting ? 'Creating campaign...' : 'Create campaign' }}
        </button>
      </form>
    </div>
  </main>
</template>
