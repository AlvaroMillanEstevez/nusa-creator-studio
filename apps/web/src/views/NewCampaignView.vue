<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

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
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-12 pt-6 sm:px-6">
      <header class="flex items-center justify-between">
        <RouterLink
          to="/"
          class="inline-flex min-h-11 items-center gap-2 rounded-full border border-neutral-800 px-4 text-sm text-neutral-300 transition hover:border-neutral-600 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        >
          <span aria-hidden="true">?</span>
          Home
        </RouterLink>

        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">
          New campaign
        </p>
      </header>

      <section class="mt-10">
        <p class="text-sm font-medium text-emerald-400">TikTok affiliate campaign</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Turn one product into your next campaign.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Give us the product context now. Script and video generation will arrive in the next step.
        </p>
      </section>

      <form class="mt-9 space-y-7" novalidate @submit.prevent="submitCampaign">
        <div>
          <label for="product-name" class="text-sm font-semibold text-neutral-200">
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
            class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
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
          <label for="product-description" class="text-sm font-semibold text-neutral-200">
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
            class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
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
          <label for="target-audience" class="text-sm font-semibold text-neutral-200">
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
            class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
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
          <label for="language" class="text-sm font-semibold text-neutral-200">Language</label>
          <select
            id="language"
            v-model="form.language"
            name="language"
            class="mt-2 w-full appearance-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
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
          <legend class="text-sm font-semibold text-neutral-200">Tone</legend>
          <div class="mt-3 grid grid-cols-2 gap-3">
            <label
              v-for="option in campaignToneOptions"
              :key="option.value"
              class="flex min-h-14 cursor-pointer items-center rounded-2xl border px-4 py-3 text-sm font-medium transition"
              :class="
                form.tone === option.value
                  ? 'border-emerald-400 bg-emerald-400/10 text-emerald-300'
                  : 'border-neutral-800 bg-neutral-900 text-neutral-300 hover:border-neutral-700'
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
          class="flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        >
          <span
            v-if="isSubmitting"
            class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
            aria-hidden="true"
          />
          {{ isSubmitting ? 'Creating campaign?' : 'Create campaign' }}
        </button>
      </form>
    </div>
  </main>
</template>
