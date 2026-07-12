<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@/i18n'
import type { WorkspaceProject } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

const props = defineProps<{
  project: WorkspaceProject
}>()

const payload = computed(() => props.project.input_payload)
const settings = computed(() => props.project.settings)

const briefItems = computed(() => [
  { label: t('projectWorkspace.productName'), value: payload.value?.product_name },
  { label: t('projectWorkspace.productCategory'), value: payload.value?.product_category },
  { label: t('projectWorkspace.projectContext'), value: payload.value?.product_description },
  { label: t('projectWorkspace.targetAudience'), value: payload.value?.target_audience },
  { label: t('projectWorkspace.platform'), value: settings.value?.platform ? formatLabel(settings.value.platform) : null },
  { label: t('projectWorkspace.language'), value: payload.value?.language ? formatLabel(payload.value.language) : null },
  { label: t('projectWorkspace.mainGoal'), value: payload.value?.main_goal ? formatLabel(payload.value.main_goal) : null },
  { label: t('projectWorkspace.tone'), value: payload.value?.tone ? formatLabel(payload.value.tone) : null },
  { label: t('projectWorkspace.contentIdea'), value: payload.value?.content_idea },
  { label: t('projectWorkspace.keySellingPoints'), value: payload.value?.key_selling_points },
  { label: t('projectWorkspace.claimsToAvoid'), value: payload.value?.claims_to_avoid },
  { label: t('projectWorkspace.brandNotes'), value: payload.value?.brand_notes },
  { label: t('projectWorkspace.ctaPreference'), value: payload.value?.cta_preference },
  { label: t('projectWorkspace.competitorNotes'), value: payload.value?.competitor_notes },
  { label: t('projectWorkspace.notes'), value: payload.value?.notes },
].filter((item) => typeof item.value === 'string' && item.value.trim() !== ''))
</script>

<template>
  <section class="nusa-card-soft mt-8 rounded-[2rem] p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          {{ t('projectWorkspace.genericWorkspace') }}
        </p>
        <h2 class="mt-2 text-xl font-semibold">{{ t('projectWorkspace.projectContextTitle') }}</h2>
      </div>
      <span class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-1 text-xs font-semibold text-neutral-300">
        {{ t('projectWorkspace.workspaceType') }}
      </span>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-2">
      <article
        v-for="item in briefItems"
        :key="item.label"
        class="rounded-2xl border border-neutral-800 bg-neutral-950/50 p-4"
      >
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
          {{ item.label }}
        </p>
        <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-neutral-200">
          {{ item.value }}
        </p>
      </article>
    </div>
  </section>
</template>