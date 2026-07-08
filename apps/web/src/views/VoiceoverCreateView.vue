<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createAiVoiceoverProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  voiceoverDurationOptions,
  voiceoverPaceOptions,
  voiceoverPlatformOptions,
  voiceoverStyleOptions,
  voiceoverToneOptions,
  type AiVoiceoverFormPayload,
  type VoiceoverDuration,
  type VoiceoverPace,
  type VoiceoverPlatform,
  type VoiceoverStyle,
  type VoiceoverTone,
} from '@/types/project'

type FormState = {
  title: string
  source_text: string
  product_name: string
  product_description: string
  target_audience: string
  language: AiVoiceoverFormPayload['language']
  voice_style: VoiceoverStyle
  tone: VoiceoverTone
  pace: VoiceoverPace
  duration_seconds: VoiceoverDuration
  platform: VoiceoverPlatform
  notes: string
}

type FieldErrors = Partial<Record<'title' | 'brief' | 'source_text', string>>

const VOICEOVER_MAX_CHARACTERS = 1000

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FieldErrors>({})

const form = reactive<FormState>({
  title: '',
  source_text: '',
  product_name: '',
  product_description: '',
  target_audience: '',
  language: 'english',
  voice_style: 'natural_creator',
  tone: 'natural',
  pace: 'medium',
  duration_seconds: 30,
  platform: 'tiktok',
  notes: '',
})

const sourceTextCharacterCount = computed(() => form.source_text.length)
const isSourceTextOverLimit = computed(
  () => sourceTextCharacterCount.value > VOICEOVER_MAX_CHARACTERS,
)

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

  if (form.source_text.length > VOICEOVER_MAX_CHARACTERS) {
    errors.source_text = `Keep the source script under ${VOICEOVER_MAX_CHARACTERS} characters.`
  }

  if (
    !form.source_text.trim() &&
    !form.product_name.trim() &&
    !form.product_description.trim()
  ) {
    errors.brief = 'Add source text or at least a product name and description.'
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitVoiceover(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  const payload: AiVoiceoverFormPayload = {
    title: form.title.trim(),
    source_text: optionalText(form.source_text),
    product_name: optionalText(form.product_name),
    product_description: optionalText(form.product_description),
    target_audience: optionalText(form.target_audience),
    language: form.language,
    voice_style: form.voice_style,
    tone: form.tone,
    pace: form.pace,
    duration_seconds: form.duration_seconds,
    platform: form.platform,
    notes: optionalText(form.notes),
  }

  try {
    const project = await createAiVoiceoverProject(payload)
    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the voiceover project. Check the API connection and try again.'
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
        <p class="text-sm font-medium text-emerald-400">AI Voiceover</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Shape a voiceover-ready narration plan.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Generate narration audio from a script. Later you will be able to use this voiceover in
          videos, subtitles and avatar workflows.
        </p>
      </header>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitVoiceover">
        <div>
          <label for="voiceover-title" class="nusa-label">
            Project title
          </label>
          <input
            id="voiceover-title"
            v-model="form.title"
            name="title"
            type="text"
            maxlength="255"
            placeholder="e.g. Portable key light voiceover"
            :aria-invalid="Boolean(fieldErrors.title)"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.title" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.title }}
          </p>
        </div>

        <div>
          <label for="source-text" class="nusa-label">
            Source text / existing script
          </label>
          <textarea
            id="source-text"
            v-model="form.source_text"
            name="source_text"
            rows="5"
            :maxlength="VOICEOVER_MAX_CHARACTERS"
            placeholder="Paste a rough script, campaign hook, or talking points."
            :aria-invalid="Boolean(fieldErrors.source_text) || isSourceTextOverLimit"
            class="nusa-textarea mt-2"
          />
          <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
            <p class="text-neutral-500">
              {{ sourceTextCharacterCount }} / {{ VOICEOVER_MAX_CHARACTERS }} characters
            </p>
            <p class="text-emerald-300">Default voice will be used for this first real voiceover version.</p>
          </div>
          <p v-if="fieldErrors.source_text" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.source_text }}
          </p>
        </div>

        <div class="rounded-2xl border border-sky-900/50 bg-sky-950/20 p-4 text-sm leading-6 text-sky-100/80">
          Tip: paste a script generated from Campaign Generator or Product-to-Video into the source
          text. This turns voiceover into a reusable production asset for future video, subtitle and
          avatar workflows.
        </div>

        <div class="grid gap-5">
          <div>
            <label for="product-name" class="nusa-label">
              Product name
            </label>
            <input
              id="product-name"
              v-model="form.product_name"
              name="product_name"
              type="text"
              placeholder="e.g. Portable creator light"
              class="nusa-input mt-2"
            />
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
              placeholder="Describe the product without exaggerated claims."
              class="nusa-textarea mt-2"
            />
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
              placeholder="Who should this narration speak to?"
              class="nusa-textarea mt-2"
            />
          </div>
        </div>

        <p v-if="fieldErrors.brief" role="alert" class="text-sm text-rose-400">
          {{ fieldErrors.brief }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
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

          <div>
            <label for="duration" class="nusa-label">Duration</label>
            <select
              id="duration"
              v-model="form.duration_seconds"
              name="duration_seconds"
              class="nusa-select mt-2"
            >
              <option
                v-for="duration in voiceoverDurationOptions"
                :key="duration"
                :value="duration"
              >
                {{ duration }} seconds
              </option>
            </select>
          </div>
        </div>

        <div>
          <label for="voice-style" class="nusa-label">
            Voice style
          </label>
          <select
            id="voice-style"
            v-model="form.voice_style"
            name="voice_style"
            class="nusa-select mt-2"
          >
            <option
              v-for="option in voiceoverStyleOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="tone" class="nusa-label">Tone</label>
            <select
              id="tone"
              v-model="form.tone"
              name="tone"
              class="nusa-select mt-2"
            >
              <option
                v-for="option in voiceoverToneOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="pace" class="nusa-label">Pace</label>
            <select
              id="pace"
              v-model="form.pace"
              name="pace"
              class="nusa-select mt-2"
            >
              <option
                v-for="option in voiceoverPaceOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div>
          <label for="platform" class="nusa-label">Platform</label>
          <select
            id="platform"
            v-model="form.platform"
            name="platform"
            class="nusa-select mt-2"
          >
            <option
              v-for="option in voiceoverPlatformOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="notes" class="nusa-label">Notes</label>
          <textarea
            id="notes"
            v-model="form.notes"
            name="notes"
            rows="3"
            placeholder="Pronunciation, delivery, words to avoid, or editing notes."
            class="nusa-textarea mt-2"
          />
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
          class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span
            v-if="isSubmitting"
            class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
            aria-hidden="true"
          />
          {{ isSubmitting ? 'Creating voiceover project...' : 'Create voiceover project' }}
        </button>
      </form>
    </div>
  </main>
</template>