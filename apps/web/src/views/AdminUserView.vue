<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { getAdminUser, updateAdminUserAccess } from '@/services/admin'
import type { AdminUserAccessUpdate, AdminUserDetail } from '@/types/admin'
import { formatDate, formatLabel } from '@/utils/formatters'

const route = useRoute()
const user = ref<AdminUserDetail | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const loadError = ref('')
const saveError = ref('')
const successMessage = ref('')

const roles = ['admin', 'internal_creator', 'subscriber', 'free_user'] as const
const plans = ['free', 'starter', 'creator', 'pro', 'internal_unlimited'] as const

const form = reactive<AdminUserAccessUpdate>({
  role: 'free_user',
  plan_key: 'free',
  is_unlimited: false,
  monthly_credit_allowance: 20,
  credits_balance: 20,
})

function userId(): string | undefined {
  const value = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id

  return value || undefined
}

function syncForm(detail: AdminUserDetail): void {
  form.role = detail.role
  form.plan_key = detail.plan_key
  form.is_unlimited = detail.is_unlimited
  form.monthly_credit_allowance = detail.monthly_credit_allowance
  form.credits_balance = detail.credits_balance
}

function messageFrom(error: unknown, fallback: string): string {
  return error instanceof ApiError ? error.message : fallback
}

async function loadUser(): Promise<void> {
  const id = userId()

  if (!id) {
    loadError.value = 'This admin user link is invalid.'
    isLoading.value = false
    return
  }

  isLoading.value = true
  loadError.value = ''

  try {
    const detail = await getAdminUser(id)
    user.value = detail
    syncForm(detail)
  } catch (error: unknown) {
    loadError.value = messageFrom(error, 'We could not load this user.')
  } finally {
    isLoading.value = false
  }
}

async function saveAccess(): Promise<void> {
  const id = userId()

  if (!id) {
    return
  }

  saveError.value = ''
  successMessage.value = ''

  if (form.credits_balance < 0 || form.monthly_credit_allowance < 0) {
    saveError.value = 'Credit values cannot be negative.'
    return
  }

  isSaving.value = true

  try {
    const detail = await updateAdminUserAccess(id, { ...form })
    user.value = detail
    syncForm(detail)
    successMessage.value = 'User access updated successfully.'
  } catch (error: unknown) {
    saveError.value = messageFrom(error, 'We could not update this user.')
  } finally {
    isSaving.value = false
  }
}

onMounted(loadUser)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="mx-auto min-h-screen max-w-4xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-8 w-2/3 animate-pulse rounded-xl bg-neutral-900" />
        <div class="mt-5 h-52 animate-pulse rounded-3xl bg-neutral-900" />
        <p class="mt-4 text-sm text-neutral-500">Loading user...</p>
      </section>

      <section
        v-else-if="loadError"
        class="mt-12 rounded-3xl border border-rose-900/60 bg-rose-950/20 p-6"
      >
        <p class="text-sm font-medium text-rose-300">User unavailable</p>
        <h1 class="mt-3 text-3xl font-bold">This account could not be loaded.</h1>
        <p role="alert" class="mt-4 text-neutral-400">{{ loadError }}</p>
        <button
          type="button"
          class="mt-6 min-h-12 rounded-xl bg-white px-5 font-semibold text-neutral-950"
          @click="loadUser"
        >
          Try again
        </button>
      </section>

      <template v-else-if="user">
        <header class="pt-12">
          <RouterLink to="/admin" class="text-sm font-semibold text-emerald-400">
            ← Back to Admin Panel
          </RouterLink>
          <h1 class="mt-5 text-3xl font-bold leading-tight sm:text-4xl">{{ user.name }}</h1>
          <p class="mt-2 text-sm text-neutral-500">{{ user.email }} · User #{{ user.id }}</p>
        </header>

        <section class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
          <article
            v-for="stat in [
              ['Projects', user.stats.projects_count],
              ['Generations', user.stats.generations_count],
              ['Completed', user.stats.completed_generations_count],
              ['Failed', user.stats.failed_generations_count],
              ['Credits spent', user.stats.credits_spent],
            ]"
            :key="String(stat[0])"
            class="rounded-2xl border border-neutral-800 bg-neutral-900/70 p-4"
          >
            <p class="text-2xl font-bold">{{ stat[1] }}</p>
            <p class="mt-2 text-xs text-neutral-500">{{ stat[0] }}</p>
          </article>
        </section>

        <section class="mt-6 rounded-3xl border border-emerald-900/60 bg-emerald-950/20 p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Access management
          </p>
          <h2 class="mt-2 text-xl font-semibold">Role, plan and credits</h2>
          <p class="mt-3 text-sm leading-6 text-neutral-500">
            Identity and password fields cannot be changed from this panel.
          </p>

          <form class="mt-6 grid gap-5 sm:grid-cols-2" @submit.prevent="saveAccess">
            <div>
              <label for="admin-role" class="text-sm font-semibold text-neutral-200">Role</label>
              <select
                id="admin-role"
                v-model="form.role"
                name="role"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 text-sm outline-none focus:border-emerald-500"
              >
                <option v-for="role in roles" :key="role" :value="role">
                  {{ formatLabel(role) }}
                </option>
              </select>
            </div>

            <div>
              <label for="admin-plan" class="text-sm font-semibold text-neutral-200">Plan</label>
              <select
                id="admin-plan"
                v-model="form.plan_key"
                name="plan_key"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 text-sm outline-none focus:border-emerald-500"
              >
                <option v-for="plan in plans" :key="plan" :value="plan">
                  {{ formatLabel(plan) }}
                </option>
              </select>
            </div>

            <div>
              <label for="admin-credits" class="text-sm font-semibold text-neutral-200">
                Credits balance
              </label>
              <input
                id="admin-credits"
                v-model.number="form.credits_balance"
                name="credits_balance"
                type="number"
                min="0"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 text-sm outline-none focus:border-emerald-500"
              />
            </div>

            <div>
              <label for="admin-allowance" class="text-sm font-semibold text-neutral-200">
                Monthly allowance
              </label>
              <input
                id="admin-allowance"
                v-model.number="form.monthly_credit_allowance"
                name="monthly_credit_allowance"
                type="number"
                min="0"
                class="mt-2 min-h-12 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 text-sm outline-none focus:border-emerald-500"
              />
            </div>

            <label
              class="flex min-h-12 items-center gap-3 rounded-2xl border border-neutral-800 bg-neutral-950 px-4 text-sm sm:col-span-2"
            >
              <input
                v-model="form.is_unlimited"
                name="is_unlimited"
                type="checkbox"
                class="size-4 accent-emerald-500"
              />
              <span>
                <strong class="text-neutral-200">Unlimited access</strong>
                <span class="ml-2 text-neutral-500">Generations do not consume credits.</span>
              </span>
            </label>

            <p
              v-if="saveError"
              role="alert"
              class="rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-200 sm:col-span-2"
            >
              {{ saveError }}
            </p>
            <p
              v-if="successMessage"
              role="status"
              class="rounded-2xl border border-emerald-900/60 bg-emerald-950/40 p-4 text-sm text-emerald-200 sm:col-span-2"
            >
              {{ successMessage }}
            </p>

            <button
              type="submit"
              :disabled="isSaving"
              class="min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:bg-neutral-700 sm:col-span-2"
            >
              {{ isSaving ? 'Saving access...' : 'Save access' }}
            </button>
          </form>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-2">
          <div>
            <h2 class="text-xl font-semibold">Recent projects</h2>
            <div v-if="user.recent_projects.length" class="mt-4 space-y-3">
              <article
                v-for="project in user.recent_projects"
                :key="project.id"
                class="rounded-2xl border border-neutral-800 bg-neutral-900/60 p-4"
              >
                <p class="font-semibold">{{ project.title }}</p>
                <p class="mt-2 text-xs text-neutral-500">
                  {{ formatLabel(project.type) }} · {{ formatLabel(project.status) }} ·
                  {{ formatDate(project.created_at) }}
                </p>
              </article>
            </div>
            <p v-else class="mt-4 text-sm text-neutral-500">No projects yet.</p>
          </div>

          <div>
            <h2 class="text-xl font-semibold">Recent generations</h2>
            <div v-if="user.recent_generations.length" class="mt-4 space-y-3">
              <article
                v-for="generation in user.recent_generations"
                :key="generation.id"
                class="rounded-2xl border border-neutral-800 bg-neutral-900/60 p-4"
              >
                <p class="font-semibold">{{ generation.project_title ?? 'Project unavailable' }}</p>
                <p class="mt-2 text-xs text-neutral-500">
                  {{ formatLabel(generation.job_type) }} · {{ formatLabel(generation.status) }} ·
                  {{ generation.credits_charged }} credits
                </p>
              </article>
            </div>
            <p v-else class="mt-4 text-sm text-neutral-500">No generations yet.</p>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>
