<script setup lang="ts">
import type { ProductToVideoProject } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ project: ProductToVideoProject }>()
</script>

<template>
  <section class="nusa-card-premium mt-9 rounded-[2rem] p-5">
    <div class="flex items-center justify-between gap-4">
      <h2 class="text-lg font-semibold">Product video brief</h2>
      <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">TikTok</span>
    </div>
    <dl class="mt-6 space-y-6">
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Product</dt>
        <dd class="mt-2 text-base font-medium text-neutral-100">{{ project.input_payload?.product_name ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Description</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">{{ project.input_payload?.product_description ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Key benefits</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">{{ project.input_payload?.key_benefits ?? '-' }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Target audience</dt>
        <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">{{ project.input_payload?.target_audience ?? '-' }}</dd>
      </div>
      <div class="grid grid-cols-2 gap-4 border-t border-neutral-800 pt-5">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Goal</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">{{ formatLabel(project.input_payload?.video_goal) }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Style</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">{{ formatLabel(project.input_payload?.video_style) }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Duration</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">{{ project.input_payload?.duration_seconds ?? '-' }} seconds</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Language</dt>
          <dd class="mt-2 text-sm font-medium text-neutral-200">{{ formatLabel(project.input_payload?.language) }}</dd>
        </div>
      </div>
    </dl>
  </section>
</template>
