<script setup lang="ts">
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { availableTools, comingSoonTools } from '@/config/tools'
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">Creator Studio</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight">What do you want to create today?</h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          Start with a campaign brief now. More focused creator workflows are joining the studio
          next.
        </p>
      </header>

      <section class="mt-10">
        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Ready to use
            </p>
            <h2 class="mt-1 text-xl font-semibold">Available tools</h2>
          </div>
          <span class="text-xs text-neutral-500">{{ availableTools.length }} live</span>
        </div>

        <div class="mt-5 space-y-4">
          <RouterLink
            v-for="tool in availableTools"
            :key="tool.id"
            :to="tool.route"
            :data-testid="'tool-card-' + tool.id"
            class="group block rounded-3xl border border-emerald-900/70 bg-gradient-to-br from-neutral-900 via-neutral-900 to-emerald-950/50 p-5 transition hover:-translate-y-0.5 hover:border-emerald-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-400">
                  {{ tool.category }}
                </p>
                <h3 class="mt-2 text-xl font-semibold">{{ tool.title }}</h3>
              </div>
              <span
                class="shrink-0 rounded-full border border-emerald-800 bg-emerald-950 px-3 py-1 text-xs font-semibold text-emerald-300"
              >
                {{ tool.badge }}
              </span>
            </div>

            <p class="mt-4 text-sm leading-6 text-neutral-300">{{ tool.short_description }}</p>
            <p class="mt-3 text-sm leading-6 text-neutral-500">{{ tool.long_description }}</p>

            <div class="mt-5 flex items-center justify-between border-t border-neutral-800 pt-4">
              <span class="text-xs font-medium text-neutral-400">{{ tool.credit_cost_label }}</span>
              <span class="text-sm font-semibold text-emerald-300">
                Open tool <span aria-hidden="true">&rarr;</span>
              </span>
            </div>
          </RouterLink>
        </div>
      </section>

      <section class="mt-12">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          Expanding the studio
        </p>
        <h2 class="mt-1 text-xl font-semibold">Coming soon</h2>

        <div class="mt-5 space-y-3">
          <article
            v-for="tool in comingSoonTools"
            :key="tool.id"
            :data-testid="'tool-card-' + tool.id"
            aria-disabled="true"
            class="rounded-3xl border border-neutral-800 bg-neutral-900/60 p-5 opacity-75"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                  {{ tool.category }}
                </p>
                <h3 class="mt-2 text-lg font-semibold text-neutral-200">{{ tool.title }}</h3>
              </div>
              <span class="shrink-0 rounded-full bg-neutral-800 px-3 py-1 text-xs text-neutral-400">
                {{ tool.badge }}
              </span>
            </div>
            <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
            <p class="mt-4 text-xs font-medium text-neutral-600">{{ tool.credit_cost_label }}</p>
          </article>
        </div>
      </section>
    </div>
  </main>
</template>
