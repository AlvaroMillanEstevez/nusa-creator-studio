<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { createWorkspaceProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  campaignToneOptions,
  type CreatorPlatform,
  type WorkspaceFormPayload,
} from '@/types/project'

type FormField = keyof WorkspaceFormPayload | 'title'
type FormErrors = Partial<Record<FormField, string>>

const platformOptions: Array<{ value: CreatorPlatform; label: string }> = [
  { value: 'tiktok', label: 'TikTok' },
  { value: 'instagram_reels', label: 'Instagram Reels' },
  { value: 'youtube_shorts', label: 'YouTube Shorts' },
  { value: 'generic_vertical_video', label: 'Generic vertical video' },
]

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FormErrors>({})

const form = reactive<WorkspaceFormPayload & { title: string }>({
  title: '',
  product_name: '',
  content_idea: '',
  product_description: '',
  target_audience: '',
  language: 'english',
  tone: 'natural',
  platform: 'tiktok',
  notes: '',
})

function validateForm(): boolean {
  const errors: FormErrors = {}

  if (!form.title.trim()) {
    errors.title = t('projectWorkspace.errors.title')
  } else if (form.title.trim().length > 255) {
    errors.title = t('projectWorkspace.errors.titleLength')
  }

  if (!form.content_idea.trim()) {
    errors.content_idea = t('projectWorkspace.errors.contentIdea')
  }

  if (!form.product_description.trim()) {
    errors.product_description = t('projectWorkspace.errors.projectContext')
  }

  if (!form.target_audience.trim()) {
    errors.target_audience = t('projectWorkspace.errors.targetAudience')
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitWorkspace(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) {
    return
  }

  isSubmitting.value = true

  try {
    const title = form.title.trim()
    const contentIdea = form.content_idea.trim()
    const project = await createWorkspaceProject(
      {
        product_name: form.product_name.trim() || contentIdea || title,
        content_idea: contentIdea,
        product_description: form.product_description.trim(),
        target_audience: form.target_audience.trim(),
        language: form.language,
        tone: form.tone,
        platform: form.platform,
        notes: form.notes?.trim() ? form.notes.trim() : null,
      },
      title,
    )

    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : t('projectWorkspace.errors.createFailed')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-3xl px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="max-w-2xl pt-8">
        <p class="text-sm font-medium text-emerald-400">{{ t('projectWorkspace.kicker') }}</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          {{ t('projectWorkspace.title') }}
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          {{ t('projectWorkspace.body') }}
        </p>
      </section>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitWorkspace">
        <div>
          <label for="project-title" class="nusa-label">{{ t('projectWorkspace.projectName') }}</label>
          <input
            id="project-title"
            v-model="form.title"
            name="title"
            type="text"
            autocomplete="off"
            maxlength="255"
            :placeholder="t('projectWorkspace.projectNamePlaceholder')"
            :aria-invalid="Boolean(fieldErrors.title)"
            :aria-describedby="fieldErrors.title ? 'project-title-error' : undefined"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.title" id="project-title-error" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.title }}
          </p>
        </div>

        <div>
          <label for="content-idea" class="nusa-label">{{ t('projectWorkspace.contentIdea') }}</label>
          <input
            id="content-idea"
            v-model="form.content_idea"
            name="content_idea"
            type="text"
            autocomplete="off"
            :placeholder="t('projectWorkspace.contentIdeaPlaceholder')"
            :aria-invalid="Boolean(fieldErrors.content_idea)"
            :aria-describedby="fieldErrors.content_idea ? 'content-idea-error' : undefined"
            class="nusa-input mt-2"
          />
          <p v-if="fieldErrors.content_idea" id="content-idea-error" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.content_idea }}
          </p>
        </div>

        <div>
          <label for="product-name" class="nusa-label">{{ t('projectWorkspace.productName') }}</label>
          <input
            id="product-name"
            v-model="form.product_name"
            name="product_name"
            type="text"
            autocomplete="off"
            :placeholder="t('projectWorkspace.productNamePlaceholder')"
            class="nusa-input mt-2"
          />
        </div>

        <div>
          <label for="project-context" class="nusa-label">{{ t('projectWorkspace.projectContext') }}</label>
          <textarea
            id="project-context"
            v-model="form.product_description"
            name="product_description"
            rows="5"
            :placeholder="t('projectWorkspace.projectContextPlaceholder')"
            :aria-invalid="Boolean(fieldErrors.product_description)"
            :aria-describedby="fieldErrors.product_description ? 'project-context-error' : undefined"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.product_description" id="project-context-error" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.product_description }}
          </p>
        </div>

        <div>
          <label for="target-audience" class="nusa-label">{{ t('projectWorkspace.targetAudience') }}</label>
          <textarea
            id="target-audience"
            v-model="form.target_audience"
            name="target_audience"
            rows="3"
            :placeholder="t('projectWorkspace.targetAudiencePlaceholder')"
            :aria-invalid="Boolean(fieldErrors.target_audience)"
            :aria-describedby="fieldErrors.target_audience ? 'target-audience-error' : undefined"
            class="nusa-textarea mt-2"
          />
          <p v-if="fieldErrors.target_audience" id="target-audience-error" class="mt-2 text-sm text-rose-400">
            {{ fieldErrors.target_audience }}
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="language" class="nusa-label">{{ t('projectWorkspace.language') }}</label>
            <select id="language" v-model="form.language" name="language" class="nusa-select mt-2">
              <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <div>
            <label for="platform" class="nusa-label">{{ t('projectWorkspace.platform') }}</label>
            <select id="platform" v-model="form.platform" name="platform" class="nusa-select mt-2">
              <option v-for="option in platformOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <fieldset>
          <legend class="nusa-label">{{ t('projectWorkspace.tone') }}</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label
              v-for="option in campaignToneOptions"
              :key="option.value"
              class="nusa-choice flex items-center"
              :class="form.tone === option.value ? 'nusa-choice-active' : 'nusa-choice-idle'"
            >
              <input v-model="form.tone" class="sr-only" type="radio" name="tone" :value="option.value" />
              {{ option.label }}
            </label>
          </div>
        </fieldset>

        <div>
          <label for="project-notes" class="nusa-label">{{ t('projectWorkspace.notes') }}</label>
          <textarea
            id="project-notes"
            v-model="form.notes"
            name="notes"
            rows="3"
            :placeholder="t('projectWorkspace.notesPlaceholder')"
            class="nusa-textarea mt-2"
          />
        </div>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200">
          {{ submitError }}
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? t('projectWorkspace.creating') : t('projectWorkspace.create') }}
        </button>
      </form>
    </div>
  </main>
</template>