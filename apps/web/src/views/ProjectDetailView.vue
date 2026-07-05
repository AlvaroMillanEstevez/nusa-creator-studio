<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { ApiError } from '@/services/api'
import { getCampaignProject } from '@/services/projects'
import type { CampaignProject } from '@/types/project'

const route = useRoute()
const project = ref<CampaignProject | null>(null)
const isLoading = ref(true)
const loadError = ref('')

function formatLabel(value: string | undefined): string {
  if (!value) {
    return '?'
  }

  return value.replace(/_/g, ' ').replace(/\b\w/g, (character: string) => character.toUpperCase())
}

async function loadProject(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  const projectId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id

  if (!projectId) {
    loadError.value = 'This project link is invalid.'
    isLoading.value = false
    return
  }

  try {
    project.value = await getCampaignProject(projectId)
  } catch (error: unknown) {
    loadError.value =
      error instanceof ApiError
        ? error.message
        : 'We could not load this project. Check the API connection and try again.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadProject)
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-12 pt-6 sm:px-6">
      <header class="flex items-center justify-between">
        <RouterLink
          to="/"
          class="inline-flex min-h-11 items-center gap-2 rounded-full border border-neutral-800 px-4 text-sm text-neutral-300 transition hover:border-neutral-600 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        >
          <span aria-hidden="true">?</span>
          Home
        </RouterLink>

        <RouterLink
          to="/campaigns/new"
          class="text-sm font-semibold text-emerald-400 transition hover:text-emerald-300"
        >
          New campaign
        </RouterLink>
      </header>

      <section v-if="isLoading" class="mt-16" aria-live="polite">
        <div class="h-4 w-28 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-3 h-10 w-3/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-10 h-56 animate-pulse rounded-3xl bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">Loading campaign?</p>
      </section>

      <section v-else-if="loadError" class="mt-16">
        <p class="text-sm font-medium text-rose-400">Unable to load campaign</p>
        <h1 class="mt-3 text-3xl font-bold">This page needs another try.</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button
          type="button"
          class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950"
          @click="loadProject"
        >
          Try again
        </button>
      </section>

      <template v-else-if="project">
        <section class="mt-10">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full border border-emerald-800 bg-emerald-950 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-300"
            >
              {{ formatLabel(project.status) }}
            </span>
            <span class="text-xs font-medium uppercase tracking-[0.16em] text-neutral-500">
              {{ formatLabel(project.type) }}
            </span>
          </div>

          <h1 class="mt-5 text-3xl font-bold leading-tight sm:text-4xl">
            {{ project.title }}
          </h1>
          <p class="mt-3 text-sm text-neutral-500">Project #{{ project.id }}</p>
        </section>

        <section class="mt-9 rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Campaign brief</h2>
            <span class="text-xs font-medium uppercase tracking-wider text-neutral-500"
              >TikTok</span
            >
          </div>

          <dl class="mt-6 space-y-6">
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Product
              </dt>
              <dd class="mt-2 text-base font-medium text-neutral-100">
                {{ project.input_payload?.product_name ?? '?' }}
              </dd>
            </div>

            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Description
              </dt>
              <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
                {{ project.input_payload?.product_description ?? '?' }}
              </dd>
            </div>

            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Target audience
              </dt>
              <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
                {{ project.input_payload?.target_audience ?? '?' }}
              </dd>
            </div>

            <div class="grid grid-cols-2 gap-4 border-t border-neutral-800 pt-5">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                  Language
                </dt>
                <dd class="mt-2 text-sm font-medium text-neutral-200">
                  {{ formatLabel(project.input_payload?.language) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                  Tone
                </dt>
                <dd class="mt-2 text-sm font-medium text-neutral-200">
                  {{ formatLabel(project.input_payload?.tone) }}
                </dd>
              </div>
            </div>
          </dl>
        </section>

        <section
          class="mt-5 overflow-hidden rounded-3xl border border-dashed border-neutral-700 bg-gradient-to-br from-neutral-900 to-emerald-950/30 p-5"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Coming next
          </p>
          <h2 class="mt-3 text-xl font-semibold">AI generation will be added next.</h2>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            Your campaign brief is saved and ready for scripts, hooks, captions, and video assets.
          </p>
        </section>
      </template>
    </div>
  </main>
</template>
