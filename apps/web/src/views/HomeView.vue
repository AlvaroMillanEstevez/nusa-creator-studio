<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { apiGet } from '@/services/api'

const apiStatus = ref(t('home.checkingApi'))

onMounted(async () => {
  try {
    const data = await apiGet<{ status: string; version: string }>('/health')
    apiStatus.value = t('home.apiConnected', { status: data.status, version: data.version })
  } catch {
    apiStatus.value = t('home.apiDisconnected')
  }
})
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-md px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="pt-14">
        <div
          class="nusa-badge inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold"
        >
          <span class="size-1.5 rounded-full bg-emerald-400" aria-hidden="true" />
          {{ t('home.badge') }}
        </div>

        <h1 class="nusa-gradient-text mt-6 text-4xl font-black leading-[1.08] sm:text-5xl">
          {{ t('home.headline') }}
        </h1>

        <p class="mt-5 text-base leading-7 text-neutral-300">
          {{ t('home.body') }}
        </p>

        <div class="mt-9 space-y-3">
          <RouterLink
            to="/tools"
            class="nusa-button-primary nusa-focus-ring flex min-h-14 items-center justify-between rounded-2xl px-5 py-4 font-black transition hover:brightness-110"
          >
            <span>{{ t('home.openStudio') }}</span>
            <span aria-hidden="true">&rarr;</span>
          </RouterLink>

          <RouterLink
            to="/dashboard"
            class="nusa-button-secondary nusa-focus-ring flex min-h-14 items-center justify-center rounded-2xl px-5 py-4 font-bold transition hover:border-cyan-400/40 hover:text-white"
          >
            {{ t('home.viewDashboard') }}
          </RouterLink>
        </div>
      </section>

      <section class="nusa-card-soft mt-12 rounded-3xl p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          {{ t('home.workflowKicker') }}
        </p>
        <ul class="mt-5 grid gap-4">
          <li class="flex gap-3">
            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
            <div>
              <p class="text-sm font-semibold text-neutral-200">{{ t('home.valueOneTitle') }}</p>
              <p class="mt-1 text-sm leading-6 text-neutral-500">
                {{ t('home.valueOneBody') }}
              </p>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
            <div>
              <p class="text-sm font-semibold text-neutral-200">
                {{ t('home.valueTwoTitle') }}
              </p>
              <p class="mt-1 text-sm leading-6 text-neutral-500">
                {{ t('home.valueTwoBody') }}
              </p>
            </div>
          </li>
        </ul>
      </section>

      <div class="nusa-card-soft mt-5 rounded-2xl px-4 py-3">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs text-neutral-500">{{ t('home.developmentStatus') }}</p>
          <p class="text-xs font-semibold text-neutral-300">{{ apiStatus }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
