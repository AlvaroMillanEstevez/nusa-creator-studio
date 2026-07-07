<script setup lang="ts">
import { t } from '@/i18n'

withDefaults(
  defineProps<{
    compact?: boolean
  }>(),
  {
    compact: false,
  },
)
</script>

<template>
  <span data-testid="brand-wordmark" class="leading-none">
    <span class="block text-sm font-black tracking-tight text-white sm:text-base">
      {{ compact ? 'Nusa' : t('app.name') }}
    </span>
    <span v-if="!compact" class="mt-1 hidden text-[10px] font-medium uppercase tracking-[0.22em] text-slate-500 sm:block">
      {{ t('app.category') }}
    </span>
  </span>
</template>
