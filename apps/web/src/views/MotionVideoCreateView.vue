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
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <header class="mt-10">
        <p class="text-sm font-medium text-emerald-400">Motion AI Video</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          Plan a short-form motion video from project assets.
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Create the project first, then upload a product image, source video, background reference
          or brand reference before running the mock generation job.
        </p>
      </header>

      <form class="mt-9 space-y-7" novalidate @submit.prevent="submitMotionVideo">
        <div>
          <label for="motion-title" class="text-sm font-semibold text-neutral-200">Project title</label>
          <input
            id="motion-title"
            v-model="form.title"
            name="title"
            type="text"
            maxlength="255"
            placeholder="e.g. Portable blender launch motion video"
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
            placeholder="e.g. Portable smoothie blender"
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
            placeholder="Describe the product honestly. Avoid guaranteed or exaggerated claims."
            class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="video-goal" class="text-sm font-semibold text-neutral-200">Video goal</label>
            <select id="video-goal" v-model="form.video_goal" name="video_goal" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in motionVideoGoalOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="duration" class="text-sm font-semibold text-neutral-200">Duration</label>
            <select id="duration" v-model.number="form.duration_seconds" name="duration_seconds" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="duration in motionDurationOptions" :key="duration" :value="duration">
                {{ duration }} seconds
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="motion-style" class="text-sm font-semibold text-neutral-200">Motion style</label>
            <select id="motion-style" v-model="form.motion_style" name="motion_style" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in motionStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div>
            <label for="visual-style" class="text-sm font-semibold text-neutral-200">Visual style</label>
            <select id="visual-style" v-model="form.visual_style" name="visual_style" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in motionVisualStyleOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="aspect-ratio" class="text-sm font-semibold text-neutral-200">Aspect ratio</label>
            <select id="aspect-ratio" v-model="form.aspect_ratio" name="aspect_ratio" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-3 py-3.5 text-sm text-white outline-none focus:border-emerald-500">
              <option v-for="option in motionAspectRatioOptions" :key="option.value" :value="option.value">
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

        <div>
          <label for="platform" class="text-sm font-semibold text-neutral-200">Platform</label>
          <select id="platform" v-model="form.platform" name="platform" class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base text-white outline-none focus:border-emerald-500">
            <option v-for="option in motionPlatformOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="target-audience" class="text-sm font-semibold text-neutral-200">Target audience</label>
          <textarea id="target-audience" v-model="form.target_audience" name="target_audience" rows="3" placeholder="Who should this video speak to?" class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
        </div>

        <div class="grid gap-5">
          <div>
            <label for="brand-notes" class="text-sm font-semibold text-neutral-200">Brand notes</label>
            <textarea id="brand-notes" v-model="form.brand_notes" name="brand_notes" rows="3" placeholder="Color, mood, packaging, creator vibe, or brand guardrails." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
          <div>
            <label for="scene-notes" class="text-sm font-semibold text-neutral-200">Scene notes</label>
            <textarea id="scene-notes" v-model="form.scene_notes" name="scene_notes" rows="3" placeholder="Key scenes, product moments, camera movement, or composition ideas." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
          <div>
            <label for="audio-notes" class="text-sm font-semibold text-neutral-200">Audio notes</label>
            <textarea id="audio-notes" v-model="form.audio_notes" name="audio_notes" rows="3" placeholder="Music mood, beat sync, voiceover direction, or no-audio constraints." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
          <div>
            <label for="notes" class="text-sm font-semibold text-neutral-200">Notes</label>
            <textarea id="notes" v-model="form.notes" name="notes" rows="3" placeholder="Anything else the mock planner should respect." class="mt-2 w-full resize-none rounded-2xl border border-neutral-800 bg-neutral-900 px-4 py-3.5 text-base leading-6 text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500" />
          </div>
        </div>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200">
          {{ submitError }}
        </div>

        <button type="submit" :disabled="isSubmitting" class="flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? 'Creating motion video project...' : 'Create motion video project' }}
        </button>
      </form>
    </div>
  </main>
</template>