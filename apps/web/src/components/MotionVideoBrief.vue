<script setup lang="ts">
import type { MotionVideoProject } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ project: MotionVideoProject }>()
</script>

<template>
  <section class="nusa-card-premium mt-9 rounded-[2rem] p-5">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-lg font-semibold">Motion video brief</h2>
      <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">
        {{ formatLabel(project.input_payload?.platform) }}
      </span>
    </div>

    <dl class="mt-6 space-y-6">
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Project</dt>
        <dd class="mt-2 text-base font-medium text-neutral-100">
          {{ project.input_payload?.title ?? project.title }}
        </dd>
      </div>

      <div v-if="project.input_payload?.product_name || project.input_payload?.product_description">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Product</dt>
        <dd class="mt-2 text-sm font-medium text-neutral-100">
          {{ project.input_payload?.product_name ?? 'Product not specified' }}
        </dd>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload?.product_description ?? '-' }}
        </dd>
      </div>

      <div class="grid grid-cols-2 gap-4 border-t border-neutral-800 pt-5">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Goal</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.video_goal) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Motion</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.motion_style) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Visual</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.visual_style) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Duration</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ project.input_payload?.duration_seconds }} seconds
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Aspect</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.aspect_ratio) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Language</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.language) }}
          </dd>
        </div>
      </div>

      <div v-if="project.input_payload?.target_audience">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Audience</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload.target_audience }}
        </dd>
      </div>

      <div v-if="project.input_payload?.brand_notes || project.input_payload?.scene_notes || project.input_payload?.audio_notes || project.input_payload?.notes">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Notes</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ [project.input_payload?.brand_notes, project.input_payload?.scene_notes, project.input_payload?.audio_notes, project.input_payload?.notes].filter(Boolean).join('\n') }}
        </dd>
      </div>
    </dl>
  </section>
</template>