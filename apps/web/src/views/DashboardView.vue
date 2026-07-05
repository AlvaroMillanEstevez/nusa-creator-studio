<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">Development dashboard</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight">Your creator workspace.</h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          A lightweight home for tools, projects and platform credits while authentication is being
          prepared.
        </p>
      </header>

      <section class="mt-9 rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Platform credits
        </p>
        <div class="mt-3 flex items-end justify-between gap-4">
          <div>
            <p class="text-2xl font-bold">Credits system ready</p>
            <p class="mt-2 text-sm leading-6 text-neutral-400">
              Your live balance will appear here after authentication is connected.
            </p>
          </div>
          <span class="mb-1 size-3 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
        </div>
      </section>

      <section class="mt-5 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          Quick action
        </p>
        <h2 class="mt-2 text-xl font-semibold">Create new content</h2>
        <p class="mt-3 text-sm leading-6 text-neutral-400">
          Choose the right workflow for your product and publishing goal.
        </p>
        <RouterLink
          to="/tools"
          class="mt-5 flex min-h-12 items-center justify-between rounded-2xl bg-white px-4 font-semibold text-neutral-950 transition hover:bg-emerald-300"
        >
          <span>Browse creator tools</span>
          <span aria-hidden="true">&rarr;</span>
        </RouterLink>
        <RouterLink
          to="/campaigns/new"
          class="mt-3 flex min-h-12 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-neutral-600 hover:bg-neutral-800"
        >
          Start affiliate campaign
        </RouterLink>
      </section>

      <section
        class="mt-5 rounded-3xl border border-dashed border-neutral-800 bg-neutral-900/40 p-5"
      >
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          Recent projects
        </p>
        <h2 class="mt-3 text-lg font-semibold text-neutral-200">
          Project dashboard will be connected after authentication.
        </h2>
        <p class="mt-3 text-sm leading-6 text-neutral-500">
          Campaign projects remain available through their existing project detail links during
          development.
        </p>
      </section>
    </div>
  </main>
</template>
