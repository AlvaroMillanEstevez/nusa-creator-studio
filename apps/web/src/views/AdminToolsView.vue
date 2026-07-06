<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { getAdminTools, updateAdminTool } from '@/services/admin'
import type {
  AdminCreatorTool,
  AdminCreatorToolUpdate,
  ToolStatus,
} from '@/types/tool'
import { formatLabel } from '@/utils/formatters'

const tools = ref<AdminCreatorTool[]>([])
const selectedTool = ref<AdminCreatorTool | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref('')
const success = ref('')

const statuses: ToolStatus[] = ['available', 'coming_soon', 'maintenance', 'disabled']

const form = reactive<AdminCreatorToolUpdate>({
  title: '',
  short_description: '',
  long_description: null,
  category: '',
  status: 'coming_soon',
  route: null,
  credit_cost: 1,
  credit_cost_label: null,
  badge_label: null,
  is_recommended: false,
  is_visible: true,
  sort_order: 0,
})

function messageFrom(requestError: unknown, fallback: string): string {
  return requestError instanceof ApiError ? requestError.message : fallback
}

function selectTool(tool: AdminCreatorTool): void {
  selectedTool.value = tool
  success.value = ''
  error.value = ''
  Object.assign(form, {
    title: tool.title,
    short_description: tool.short_description,
    long_description: tool.long_description,
    category: tool.category,
    status: tool.status,
    route: tool.route,
    credit_cost: tool.credit_cost,
    credit_cost_label: tool.credit_cost_label,
    badge_label: tool.badge_label,
    is_recommended: tool.is_recommended,
    is_visible: tool.is_visible,
    sort_order: tool.sort_order,
  })
}

async function loadTools(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    tools.value = await getAdminTools()
  } catch (requestError: unknown) {
    error.value = messageFrom(requestError, 'We could not load tool management.')
  } finally {
    isLoading.value = false
  }
}

async function saveTool(): Promise<void> {
  if (!selectedTool.value) {
    return
  }

  isSaving.value = true
  error.value = ''
  success.value = ''

  const payload: AdminCreatorToolUpdate = {
    ...form,
    long_description: form.long_description?.trim() || null,
    route: form.route?.trim() || null,
    credit_cost_label: form.credit_cost_label?.trim() || null,
    badge_label: form.badge_label?.trim() || null,
  }

  try {
    const updated = await updateAdminTool(selectedTool.value.key, payload)
    const index = tools.value.findIndex((tool) => tool.key === updated.key)

    if (index >= 0) {
      tools.value[index] = updated
    }

    selectTool(updated)
    success.value = 'Tool settings saved.'
  } catch (requestError: unknown) {
    error.value = messageFrom(requestError, 'We could not save this tool.')
  } finally {
    isSaving.value = false
  }
}

function statusClasses(status: ToolStatus): string {
  if (status === 'available') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'maintenance') {
    return 'border-amber-800 bg-amber-950 text-amber-300'
  }

  if (status === 'disabled') {
    return 'border-rose-900 bg-rose-950 text-rose-300'
  }

  return 'border-neutral-700 bg-neutral-800 text-neutral-300'
}

onMounted(() => {
  void loadTools()
})
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">Platform administration</p>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold leading-tight sm:text-4xl">Tool Management</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
              Control catalog visibility, availability and credit display without changing
              frontend code.
            </p>
          </div>
          <RouterLink
            to="/admin"
            class="flex min-h-11 items-center rounded-xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-300 transition hover:bg-neutral-800"
          >
            Back to Admin Panel
          </RouterLink>
        </div>
      </header>

      <div
        v-if="error && !selectedTool"
        role="alert"
        class="mt-8 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-200"
      >
        {{ error }}
      </div>

      <div v-if="isLoading" class="mt-8 grid gap-4 lg:grid-cols-2">
        <div
          v-for="index in 4"
          :key="index"
          class="h-44 animate-pulse rounded-3xl bg-neutral-900"
        />
      </div>

      <div v-else class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.85fr)]">
        <section>
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
                Service catalog
              </p>
              <h2 class="mt-2 text-xl font-semibold">All tools</h2>
            </div>
            <span class="text-xs text-neutral-500">{{ tools.length }} configured</span>
          </div>

          <div class="mt-5 grid gap-3">
            <article
              v-for="tool in tools"
              :key="tool.key"
              :data-testid="'admin-tool-' + tool.key"
              class="rounded-3xl border p-5 transition"
              :class="
                selectedTool?.key === tool.key
                  ? 'border-emerald-700 bg-emerald-950/20'
                  : 'border-neutral-800 bg-neutral-900/70'
              "
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-medium text-neutral-500">{{ tool.key }}</p>
                  <h3 class="mt-1 font-semibold">{{ tool.title }}</h3>
                </div>
                <span
                  class="rounded-full border px-2.5 py-1 text-[10px] font-semibold"
                  :class="statusClasses(tool.status)"
                >
                  {{ formatLabel(tool.status) }}
                </span>
              </div>

              <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt class="text-neutral-600">Visibility</dt>
                  <dd class="mt-1 text-neutral-300">
                    {{ tool.is_visible ? 'Visible' : 'Hidden' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-neutral-600">Credit cost</dt>
                  <dd class="mt-1 text-neutral-300">{{ tool.credit_cost }}</dd>
                </div>
                <div>
                  <dt class="text-neutral-600">Sort order</dt>
                  <dd class="mt-1 text-neutral-300">{{ tool.sort_order }}</dd>
                </div>
                <div>
                  <dt class="text-neutral-600">Route</dt>
                  <dd class="mt-1 break-all text-neutral-300">{{ tool.route ?? 'Not configured' }}</dd>
                </div>
              </dl>

              <button
                type="button"
                class="mt-5 min-h-10 w-full rounded-xl border border-neutral-700 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800"
                @click="selectTool(tool)"
              >
                Edit tool
              </button>
            </article>
          </div>
        </section>

        <section
          class="rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 lg:sticky lg:top-5"
        >
          <template v-if="selectedTool">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Editing {{ selectedTool.key }}
            </p>
            <h2 class="mt-2 text-xl font-semibold">Tool settings</h2>

            <form class="mt-6 space-y-4" @submit.prevent="saveTool">
              <label class="block text-sm text-neutral-300">
                Title
                <input
                  v-model="form.title"
                  name="title"
                  required
                  class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                />
              </label>

              <label class="block text-sm text-neutral-300">
                Short description
                <textarea
                  v-model="form.short_description"
                  name="short_description"
                  required
                  rows="3"
                  class="mt-2 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-3 text-white outline-none focus:border-emerald-500"
                />
              </label>

              <label class="block text-sm text-neutral-300">
                Long description
                <textarea
                  v-model="form.long_description"
                  name="long_description"
                  rows="4"
                  class="mt-2 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-3 text-white outline-none focus:border-emerald-500"
                />
              </label>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Category
                  <input
                    v-model="form.category"
                    name="category"
                    required
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  />
                </label>

                <label class="block text-sm text-neutral-300">
                  Status
                  <select
                    v-model="form.status"
                    name="status"
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  >
                    <option v-for="status in statuses" :key="status" :value="status">
                      {{ formatLabel(status) }}
                    </option>
                  </select>
                </label>
              </div>

              <label class="block text-sm text-neutral-300">
                Route
                <input
                  v-model="form.route"
                  name="route"
                  placeholder="/tool/new or blank"
                  class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                />
              </label>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Credit cost
                  <input
                    v-model.number="form.credit_cost"
                    name="credit_cost"
                    type="number"
                    min="0"
                    required
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  />
                </label>

                <label class="block text-sm text-neutral-300">
                  Sort order
                  <input
                    v-model.number="form.sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    required
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  />
                </label>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Credit label
                  <input
                    v-model="form.credit_cost_label"
                    name="credit_cost_label"
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  />
                </label>

                <label class="block text-sm text-neutral-300">
                  Badge label
                  <input
                    v-model="form.badge_label"
                    name="badge_label"
                    class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 text-white outline-none focus:border-emerald-500"
                  />
                </label>
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <label
                  class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300"
                >
                  <input
                    v-model="form.is_recommended"
                    name="is_recommended"
                    type="checkbox"
                    class="size-4 accent-emerald-500"
                  />
                  Recommended
                </label>
                <label
                  class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300"
                >
                  <input
                    v-model="form.is_visible"
                    name="is_visible"
                    type="checkbox"
                    class="size-4 accent-emerald-500"
                  />
                  Visible in catalog
                </label>
              </div>

              <div
                v-if="error"
                role="alert"
                class="rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-200"
              >
                {{ error }}
              </div>
              <p v-if="success" role="status" class="text-sm font-medium text-emerald-300">
                {{ success }}
              </p>

              <button
                type="submit"
                :disabled="isSaving"
                class="min-h-12 w-full rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:bg-neutral-700"
              >
                {{ isSaving ? 'Saving...' : 'Save tool settings' }}
              </button>
            </form>
          </template>

          <div v-else class="py-10 text-center">
            <h2 class="text-lg font-semibold">Select a tool to edit</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              Choose a catalog item to update its status, visibility, route and credit display.
            </p>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>