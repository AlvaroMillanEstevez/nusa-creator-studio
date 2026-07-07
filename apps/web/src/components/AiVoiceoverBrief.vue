<script setup lang="ts">
import type { AiVoiceoverProject } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ project: AiVoiceoverProject }>()
</script>

<template>
  <section class="mt-9 rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-lg font-semibold">Voiceover brief</h2>
      <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">
        {{ formatLabel(project.input_payload?.platform) }}
      </span>
    </div>

    <dl class="mt-6 space-y-6">
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Narration title</dt>
        <dd class="mt-2 text-base font-medium text-neutral-100">
          {{ project.input_payload?.title ?? project.title }}
        </dd>
      </div>

      <div v-if="project.input_payload?.source_text">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Source text</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload.source_text }}
        </dd>
      </div>

      <div v-if="project.input_payload?.product_name || project.input_payload?.product_description">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Product context</dt>
        <dd class="mt-2 text-sm font-medium text-neutral-100">
          {{ project.input_payload?.product_name ?? 'Product not specified' }}
        </dd>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload?.product_description ?? '-' }}
        </dd>
      </div>

      <div v-if="project.input_payload?.target_audience">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Target audience</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload.target_audience }}
        </dd>
      </div>

      <div class="grid grid-cols-2 gap-4 border-t border-neutral-800 pt-5">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Language</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.language) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Voice style</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.voice_style) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Tone</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.tone) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Pace</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.pace) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Duration</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ project.input_payload?.duration_seconds ?? '-' }} seconds
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Platform</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">
            {{ formatLabel(project.input_payload?.platform) }}
          </dd>
        </div>
      </div>

      <div v-if="project.input_payload?.notes">
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Notes</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
          {{ project.input_payload.notes }}
        </dd>
      </div>
    </dl>
  </section>
</template>