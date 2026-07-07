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
    <div class="nusa-page-container mx-auto min-h-screen max-w-7xl px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="grid gap-8 pt-8 lg:grid-cols-[1.35fr_0.65fr] lg:items-center lg:pt-10">
        <div>
          <div
            class="nusa-badge inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold"
          >
            <span class="size-1.5 rounded-full bg-emerald-400" aria-hidden="true" />
            {{ t('home.badge') }}
          </div>

          <h1 class="nusa-gradient-text mt-6 max-w-[11ch] text-[clamp(2.45rem,10.4vw,3.8rem)] font-black leading-[0.98] tracking-[-0.055em] sm:max-w-[12ch] lg:max-w-[13ch] lg:text-[clamp(5rem,6.9vw,7.4rem)] lg:leading-[0.92]">
            {{ t('home.headline') }}
          </h1>

          <p class="mt-5 max-w-2xl text-base leading-7 text-neutral-300 sm:text-lg">
            {{ t('home.body') }}
          </p>

          <div class="mt-9 grid gap-3 sm:max-w-xl sm:grid-cols-2">
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
        </div>

        <aside class="nusa-card-premium rounded-[2rem] p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('home.workflowKicker') }}
          </p>
          <ul class="mt-5 grid gap-4">
            <li class="nusa-card-soft flex gap-3 rounded-2xl p-4">
              <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
              <div>
                <p class="nusa-label">{{ t('home.valueOneTitle') }}</p>
                <p class="mt-1 text-sm leading-6 text-neutral-500">
                  {{ t('home.valueOneBody') }}
                </p>
              </div>
            </li>
            <li class="nusa-card-soft flex gap-3 rounded-2xl p-4">
              <span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
              <div>
                <p class="nusa-label">
                  {{ t('home.valueTwoTitle') }}
                </p>
                <p class="mt-1 text-sm leading-6 text-neutral-500">
                  {{ t('home.valueTwoBody') }}
                </p>
              </div>
            </li>
          </ul>
        </aside>
      </section>

      <div class="nusa-card-soft mt-8 rounded-2xl px-4 py-3 lg:ml-auto lg:max-w-xl">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          <p class="text-xs text-neutral-500">{{ t('home.developmentStatus') }}</p>
          <p class="text-xs font-semibold text-neutral-300">{{ apiStatus }}</p>
        </div>
      </div>
    </div>
  </main>
</template>
