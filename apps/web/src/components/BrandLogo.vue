<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue'
import BrandWordmark from '@/components/BrandWordmark.vue'

withDefaults(
  defineProps<{
    compact?: boolean
    markSize?: 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  {
    compact: false,
    markSize: 'md',
  },
)
</script>

<template>
  <span data-testid="brand-logo" class="inline-flex min-w-0 items-center gap-3.5">
    <BrandMark :size="markSize" />
    <BrandWordmark :compact="compact" />
  </span>
</template>
