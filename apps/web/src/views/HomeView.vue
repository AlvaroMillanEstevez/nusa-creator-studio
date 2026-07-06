<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
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
    <div class="mx-auto min-h-screen max-w-md px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="pt-14">
        <div
          class="inline-flex items-center gap-2 rounded-full border border-emerald-900/70 bg-emerald-950/40 px-3 py-1.5 text-xs font-semibold text-emerald-300"
        >
          <span class="size-1.5 rounded-full bg-emerald-400" aria-hidden="true" />
          Creator tools for TikTok affiliates
        </div>

        <h1 class="mt-6 text-4xl font-bold leading-[1.08] sm:text-5xl">
          Your product content studio, all in one place.
        </h1>

        <p class="mt-5 text-base leading-7 text-neutral-300">
          Plan affiliate content, shape recorded product stories and keep every saved result in one
          focused creator workspace.
        </p>

        <div class="mt-9 space-y-3">
          <RouterLink
            to="/tools"
            class="flex min-h-14 items-center justify-between rounded-2xl bg-white px-5 py-4 font-semibold text-neutral-950 transition hover:bg-emerald-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          >
            <span>Open Creator Studio</span>
            <span aria-hidden="true">&rarr;</span>
          </RouterLink>

          <RouterLink
            to="/dashboard"
            class="flex min-h-14 items-center justify-center rounded-2xl border border-neutral-800 bg-neutral-900 px-5 py-4 font-semibold text-neutral-200 transition hover:border-neutral-700 hover:bg-neutral-800"
          >
            View dashboard
          </RouterLink>
        </div>
      </section>

      <section class="mt-12 border-y border-neutral-900 py-6">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          Built for a faster creator workflow
        </p>
        <ul class="mt-5 grid gap-4">
          <li class="flex gap-3">
            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
            <div>
              <p class="text-sm font-semibold text-neutral-200">From brief to usable content</p>
              <p class="mt-1 text-sm leading-6 text-neutral-500">
                Keep planning, generation and saved results together.
              </p>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
            <div>
              <p class="text-sm font-semibold text-neutral-200">
                Made for vertical product stories
              </p>
              <p class="mt-1 text-sm leading-6 text-neutral-500">
                Structured for TikTok affiliate creation in English, Indonesian and Spanish.
              </p>
            </div>
          </li>
        </ul>
      </section>

      <div class="mt-5 rounded-2xl border border-neutral-900 bg-neutral-900/60 px-4 py-3">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs text-neutral-500">Development status</p>
          <p class="text-xs font-semibold text-neutral-300">{{ apiStatus }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
