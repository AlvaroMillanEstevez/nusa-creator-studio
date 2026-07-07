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
  <span data-testid="brand-wordmark" class="min-w-0 leading-none">
    <span v-if="compact" class="block text-base font-black tracking-tight text-white sm:text-lg">
      Nusa
    </span>
    <template v-else>
      <span class="block text-base font-black tracking-tight text-white sm:hidden">Nusa</span>
      <span class="hidden text-base font-black tracking-tight text-white sm:block sm:text-lg">
        {{ t('app.name') }}
      </span>
    </template>
    <span
      v-if="!compact"
      class="mt-1 hidden text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500 sm:block"
    >
      {{ t('app.category') }}
    </span>
  </span>
</template>
