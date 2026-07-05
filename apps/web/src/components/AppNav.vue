<script setup lang="ts">
import { RouterLink } from 'vue-router'

const navigationItems = [
  { label: 'Home', to: '/' },
  { label: 'Tools', to: '/tools' },
  { label: 'Dashboard', to: '/dashboard' },
  { label: 'Create', to: '/campaigns/new' },
] as const
</script>

<template>
  <nav
    aria-label="Primary navigation"
    class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-neutral-800 bg-neutral-900/80 p-1"
  >
    <RouterLink
      v-for="item in navigationItems"
      :key="item.to"
      :to="item.to"
      class="flex min-h-10 shrink-0 items-center justify-center rounded-xl px-3 text-xs font-semibold text-neutral-400 transition hover:bg-neutral-800 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
      active-class="bg-neutral-800 text-white"
      :exact-active-class="item.to === '/' ? 'bg-neutral-800 text-white' : undefined"
    >
      {{ item.label }}
    </RouterLink>
  </nav>
</template>
