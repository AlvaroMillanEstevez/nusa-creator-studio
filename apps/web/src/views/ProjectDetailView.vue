<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { ApiError } from '@/services/api'
import { generateCampaignProject, getCampaignProject } from '@/services/projects'
import type { CampaignGenerationResult, CampaignProject, Generation } from '@/types/project'

const route = useRoute()
const project = ref<CampaignProject | null>(null)
const generation = ref<Generation<CampaignGenerationResult> | null>(null)
const isLoading = ref(true)
const isGenerating = ref(false)
const loadError = ref('')
const generationError = ref('')
const remainingCredits = ref<number | null>(null)

const generationResult = computed(() => generation.value?.result_payload ?? null)

function formatLabel(value: string | undefined): string {
  if (!value) {
    return '-'
  }

  return value.replace(/_/g, ' ').replace(/\b\w/g, (character: string) => character.toUpperCase())
}

function currentProjectId(): string | undefined {
  const routeId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id

  return routeId || undefined
}

async function loadProject(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  const projectId = currentProjectId()

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

async function generateCampaign(): Promise<void> {
  const projectId = currentProjectId()

  if (!projectId || !project.value) {
    return
  }

  generationError.value = ''
  isGenerating.value = true

  try {
    const response = await generateCampaignProject(projectId)

    generation.value = response.data
    remainingCredits.value = response.meta.credits_balance
  } catch (error: unknown) {
    if (
      error instanceof ApiError &&
      (error.code === 'insufficient_credits' || error.status === 402)
    ) {
      generationError.value =
        'You need at least 1 platform credit to generate this campaign. Your balance was not changed.'
    } else {
      generationError.value =
        error instanceof ApiError
          ? error.message
          : 'We could not generate the campaign. Please try again.'
    }
  } finally {
    isGenerating.value = false
  }
}

onMounted(loadProject)
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-md px-5 pb-16 pt-6 sm:px-6">
      <header class="flex items-center justify-between">
        <RouterLink
          to="/"
          class="inline-flex min-h-11 items-center gap-2 rounded-full border border-neutral-800 px-4 text-sm text-neutral-300 transition hover:border-neutral-600 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        >
          <span aria-hidden="true">&larr;</span>
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
        <p class="mt-5 text-sm text-neutral-500">Loading campaign...</p>
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
            <span class="text-xs font-medium uppercase tracking-wider text-neutral-500">
              TikTok
            </span>
          </div>

          <dl class="mt-6 space-y-6">
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Product
              </dt>
              <dd class="mt-2 text-base font-medium text-neutral-100">
                {{ project.input_payload?.product_name ?? '-' }}
              </dd>
            </div>

            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Description
              </dt>
              <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
                {{ project.input_payload?.product_description ?? '-' }}
              </dd>
            </div>

            <div>
              <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">
                Target audience
              </dt>
              <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-300">
                {{ project.input_payload?.target_audience ?? '-' }}
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
          v-if="project.type === 'affiliate_campaign'"
          class="mt-5 rounded-3xl border border-emerald-900/70 bg-gradient-to-br from-neutral-900 to-emerald-950/40 p-5"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                Mock AI studio
              </p>
              <h2 class="mt-2 text-xl font-semibold">
                {{ generation ? 'Generate another version' : 'Create campaign content' }}
              </h2>
            </div>
            <span
              class="shrink-0 rounded-full border border-neutral-700 bg-neutral-950/70 px-3 py-1 text-xs text-neutral-300"
            >
              1 credit
            </span>
          </div>

          <p class="mt-3 text-sm leading-6 text-neutral-400">
            Generate deterministic hooks, a TikTok script, caption, hashtags, CTA, and practical
            filming notes.
          </p>

          <p v-if="remainingCredits !== null" class="mt-3 text-sm font-medium text-emerald-300">
            {{ remainingCredits }} credits remaining
          </p>

          <div
            v-if="generationError"
            role="alert"
            class="mt-4 rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200"
          >
            {{ generationError }}
          </div>

          <button
            type="button"
            :disabled="isGenerating"
            class="mt-5 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
            @click="generateCampaign"
          >
            <span
              v-if="isGenerating"
              class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
              aria-hidden="true"
            />
            {{
              isGenerating
                ? 'Generating campaign...'
                : generation
                  ? 'Generate again'
                  : 'Generate campaign'
            }}
          </button>
        </section>

        <div v-if="generationResult" class="mt-8 space-y-5" aria-live="polite">
          <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Generation complete
            </p>
            <h2 class="mt-2 text-2xl font-bold">Hooks</h2>
            <ol class="mt-5 space-y-3">
              <li
                v-for="(hook, index) in generationResult.hooks"
                :key="hook"
                class="flex gap-3 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-6 text-neutral-200"
              >
                <span class="font-bold text-emerald-400">{{ index + 1 }}</span>
                <span>{{ hook }}</span>
              </li>
            </ol>
          </section>

          <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
                  TikTok script
                </p>
                <h2 class="mt-2 text-xl font-bold">{{ generationResult.script.title }}</h2>
              </div>
              <span class="shrink-0 text-sm text-neutral-400">
                {{ generationResult.script.duration_seconds }}s
              </span>
            </div>

            <div class="mt-6 space-y-4">
              <article
                v-for="(scene, index) in generationResult.script.scenes"
                :key="scene.timestamp"
                class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
              >
                <div class="flex items-center justify-between gap-3">
                  <p class="text-sm font-semibold text-white">Scene {{ index + 1 }}</p>
                  <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
                    {{ scene.timestamp }}
                  </span>
                </div>

                <dl class="mt-4 space-y-4 text-sm">
                  <div>
                    <dt class="font-semibold text-neutral-500">Voiceover</dt>
                    <dd class="mt-1 leading-6 text-neutral-200">{{ scene.voiceover }}</dd>
                  </div>
                  <div>
                    <dt class="font-semibold text-neutral-500">Visual direction</dt>
                    <dd class="mt-1 leading-6 text-neutral-300">
                      {{ scene.visual_direction }}
                    </dd>
                  </div>
                  <div>
                    <dt class="font-semibold text-neutral-500">On-screen text</dt>
                    <dd class="mt-1 font-medium text-emerald-300">
                      {{ scene.on_screen_text }}
                    </dd>
                  </div>
                </dl>
              </article>
            </div>
          </section>

          <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Caption
            </p>
            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-200">
              {{ generationResult.caption }}
            </p>
          </section>

          <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Hashtags
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <span
                v-for="hashtag in generationResult.hashtags"
                :key="hashtag"
                class="rounded-full border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs font-medium text-neutral-300"
              >
                {{ hashtag }}
              </span>
            </div>
          </section>

          <section class="rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">CTA</p>
            <p class="mt-3 text-base font-semibold leading-7 text-white">
              {{ generationResult.cta }}
            </p>
          </section>

          <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Practical notes
            </p>
            <ul class="mt-4 space-y-3">
              <li
                v-for="note in generationResult.notes"
                :key="note"
                class="flex gap-3 text-sm leading-6 text-neutral-300"
              >
                <span class="text-emerald-400" aria-hidden="true">&#10003;</span>
                <span>{{ note }}</span>
              </li>
            </ul>
          </section>
        </div>
      </template>
    </div>
  </main>
</template>
