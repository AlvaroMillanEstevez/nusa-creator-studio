<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  alternateLabel: string
  alternateTo: string
}>()
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-12 pt-6 sm:px-6">
      <header class="flex items-center justify-between gap-4">
        <RouterLink to="/" class="text-sm font-bold text-white"> Nusa Creator Studio </RouterLink>
        <nav
          aria-label="Authentication navigation"
          class="flex shrink-0 items-center gap-3 text-xs"
        >
          <RouterLink to="/" class="text-neutral-400 transition hover:text-white">
            Back to Home
          </RouterLink>
          <RouterLink
            :to="alternateTo"
            class="font-semibold text-emerald-400 transition hover:text-emerald-300"
          >
            {{ alternateLabel }}
          </RouterLink>
        </nav>
      </header>

      <slot />
    </div>
  </main>
</template>
