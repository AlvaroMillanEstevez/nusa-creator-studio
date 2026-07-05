<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { apiGet } from '@/services/api'

const apiStatus = ref('Checking API...')

onMounted(async () => {
  try {
    const data = await apiGet<{ status: string; version: string }>('/health')
    apiStatus.value = 'API ' + data.status + ' - ' + data.version
  } catch {
    apiStatus.value = 'API not connected'
  }
})
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <section class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12">
      <p class="mb-3 text-sm font-medium text-emerald-400">Nusa Creator Studio</p>

      <h1 class="text-4xl font-bold leading-tight">
        Convert any product into TikTok-ready content.
      </h1>

      <p class="mt-5 text-base leading-7 text-neutral-300">
        Generate hooks, scripts, captions and short-form product ads for creators and affiliates.
      </p>

      <div class="mt-8 rounded-2xl border border-neutral-800 bg-neutral-900 p-4">
        <p class="text-sm text-neutral-400">Backend status</p>
        <p class="mt-1 font-semibold">{{ apiStatus }}</p>
      </div>

      <RouterLink
        to="/campaigns/new"
        class="mt-8 flex min-h-14 items-center justify-center rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
      >
        Create first product campaign
      </RouterLink>
    </section>
  </main>
</template>
