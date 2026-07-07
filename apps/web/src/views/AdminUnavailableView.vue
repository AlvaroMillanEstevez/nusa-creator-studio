<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-3xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <section class="mt-12 rounded-3xl border border-amber-900/60 bg-amber-950/20 p-6">
        <p class="text-sm font-medium text-amber-400">Restricted area</p>
        <h1 class="mt-3 text-3xl font-bold">Admin panel unavailable</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">
          Your account does not have permission to access platform administration.
        </p>
        <div class="mt-7 grid gap-3 sm:grid-cols-2">
          <RouterLink
            to="/dashboard"
            class="flex min-h-12 items-center justify-center rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          >
            Back to dashboard
          </RouterLink>
          <RouterLink
            to="/account"
            class="flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800"
          >
            View account
          </RouterLink>
        </div>
      </section>
    </div>
  </main>
</template>
