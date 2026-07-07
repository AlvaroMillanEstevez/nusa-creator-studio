<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { createMotionVideoProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  motionAspectRatioOptions,
  motionDurationOptions,
  motionPlatformOptions,
  motionStyleOptions,
  motionVideoGoalOptions,
  motionVisualStyleOptions,
  type MotionVideoFormPayload,
} from '@/types/project'

type FormState = {
  title: string
  product_name: string
  product_description: string
  video_goal: MotionVideoFormPayload['video_goal']
  motion_style: MotionVideoFormPayload['motion_style']
  visual_style: MotionVideoFormPayload['visual_style']
  duration_seconds: MotionVideoFormPayload['duration_seconds']
  aspect_ratio: MotionVideoFormPayload['aspect_ratio']
  target_audience: string
  platform: MotionVideoFormPayload['platform']
  language: MotionVideoFormPayload['language']
  brand_notes: string
  scene_notes: string
  audio_notes: string
  notes: string
}

type FieldErrors = Partial<Record<'title', string>>

const router = useRouter()
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FieldErrors>({})

const form = reactive<FormState>({
  title: '',
  product_name: '',
  product_description: '',
  video_goal: 'product_showcase',
  motion_style: 'smooth_camera',
  visual_style: 'ecommerce_clean',
  duration_seconds: 15,
  aspect_ratio: 'vertical_9_16',
  target_audience: '',
  platform: 'tiktok',
  language: 'english',
  brand_notes: '',
  scene_notes: '',
  audio_notes: '',
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

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitMotionVideo(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  const payload: MotionVideoFormPayload = {
    title: form.title.trim(),
    product_name: optionalText(form.product_name),
    product_description: optionalText(form.product_description),
    video_goal: form.video_goal,
    motion_style: form.motion_style,
    visual_style: form.visual_style,
    duration_seconds: form.duration_seconds,
    aspect_ratio: form.aspect_ratio,
    target_audience: optionalText(form.target_audience),
    platform: form.platform,
    language: form.language,
    brand_notes: optionalText(form.brand_notes),
    scene_notes: optionalText(form.scene_notes),
    audio_notes: optionalText(form.audio_notes),
    notes: optionalText(form.notes),
  }

  try {
    const project = await createMotionVideoProject(payload)
    await router.push({ name: 'project-detail', params: { id: project.id } })
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not create the motion video project. Check the API connection and try again.'
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
        <p class="text-sm font-medium text-emerald-400">Motion AI Video</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Plan a short-form motion video from project assets.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Create the project first, then upload a product image, source video, background reference
          or brand reference before running the mock generation job.
        </p>
      </header>

      <form class="nusa-form-card mt-8 space-y-7 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitMotionVideo">
        <div>
          <label for="motion-title" class="nusa-label">Project title</label>
          <input
            id="motion-title"
            v-model="form.title"
            name="title"
            type="text"
            maxlength="255"
            placeholder="e.g. Portable blender launch motion video"
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
            placeholder="e.g. Portable smoothie blender"
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
            placeholder="Describe the product honestly. Avoid guaranteed or exaggerated claims."
            class="nusa-textarea mt-2"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="video-goal" class="nusa-label">Video goal</label>
            <select id="video-goal" v-model="form.video_goal" name="video_goal" class="nusa-select mt-2">
              <option v-for="option in motionVideoGoalOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="duration" class="nusa-label">Duration</label>
            <select id="duration" v-model.number="form.duration_seconds" name="duration_seconds" class="nusa-select mt-2">
              <option v-for="duration in motionDurationOptions" :key="duration" :value="duration">
                {{ duration }} seconds
              </option>
            </select>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="motion-style" class="nusa-label">Motion style</label>
            <select id="motion-style" v-model="form.motion_style" name="motion_style" class="nusa-select mt-2">
              <option v-for="option in motionStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="visual-style" class="nusa-label">Visual style</label>
            <select id="visual-style" v-model="form.visual_style" name="visual_style" class="nusa-select mt-2">
              <option v-for="option in motionVisualStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="aspect-ratio" class="nusa-label">Aspect ratio</label>
            <select id="aspect-ratio" v-model="form.aspect_ratio" name="aspect_ratio" class="nusa-select mt-2">
              <option v-for="option in motionAspectRatioOptions" :key="option.value" :value="option.value">
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

        <div>
          <label for="platform" class="nusa-label">Platform</label>
          <select id="platform" v-model="form.platform" name="platform" class="nusa-select mt-2">
            <option v-for="option in motionPlatformOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="target-audience" class="nusa-label">Target audience</label>
          <textarea id="target-audience" v-model="form.target_audience" name="target_audience" rows="3" placeholder="Who should this video speak to?" class="nusa-textarea mt-2" />
        </div>

        <div class="grid gap-5">
          <div>
            <label for="brand-notes" class="nusa-label">Brand notes</label>
            <textarea id="brand-notes" v-model="form.brand_notes" name="brand_notes" rows="3" placeholder="Color, mood, packaging, creator vibe, or brand guardrails." class="nusa-textarea mt-2" />
          </div>
          <div>
            <label for="scene-notes" class="nusa-label">Scene notes</label>
            <textarea id="scene-notes" v-model="form.scene_notes" name="scene_notes" rows="3" placeholder="Key scenes, product moments, camera movement, or composition ideas." class="nusa-textarea mt-2" />
          </div>
          <div>
            <label for="audio-notes" class="nusa-label">Audio notes</label>
            <textarea id="audio-notes" v-model="form.audio_notes" name="audio_notes" rows="3" placeholder="Music mood, beat sync, voiceover direction, or no-audio constraints." class="nusa-textarea mt-2" />
          </div>
          <div>
            <label for="notes" class="nusa-label">Notes</label>
            <textarea id="notes" v-model="form.notes" name="notes" rows="3" placeholder="Anything else the mock planner should respect." class="nusa-textarea mt-2" />
          </div>
        </div>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200">
          {{ submitError }}
        </div>

        <button type="submit" :disabled="isSubmitting" class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60">
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? 'Creating motion video project...' : 'Create motion video project' }}
        </button>
      </form>
    </div>
  </main>
</template>