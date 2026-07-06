<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { getAdminSummary, listAdminUsers } from '@/services/admin'
import type {
  AdminSummary,
  AdminUserFilters,
  AdminUserListItem,
  AdminUserListResponse,
} from '@/types/admin'
import { formatDate, formatLabel } from '@/utils/formatters'

const summary = ref<AdminSummary | null>(null)
const users = ref<AdminUserListItem[]>([])
const pagination = ref<AdminUserListResponse['meta'] | null>(null)
const filters = reactive<AdminUserFilters>({
  search: '',
  role: '',
  plan_key: '',
  is_unlimited: '',
})
const isSummaryLoading = ref(true)
const isUsersLoading = ref(true)
const summaryError = ref('')
const usersError = ref('')

const roles = ['admin', 'internal_creator', 'subscriber', 'free_user'] as const
const plans = ['free', 'starter', 'creator', 'pro', 'internal_unlimited'] as const

const summaryCards = computed(() => {
  if (!summary.value) {
    return []
  }

  return [
    { label: 'Total users', value: summary.value.total_users },
    { label: 'Total projects', value: summary.value.total_projects },
    { label: 'Total generations', value: summary.value.total_generations },
    { label: 'Completed generations', value: summary.value.total_completed_generations },
    { label: 'Credits spent', value: summary.value.total_credits_spent },
    { label: 'Unlimited generations', value: summary.value.total_unlimited_generations },
  ]
})

function messageFrom(error: unknown, fallback: string): string {
  return error instanceof ApiError ? error.message : fallback
}

async function loadSummary(): Promise<void> {
  isSummaryLoading.value = true
  summaryError.value = ''

  try {
    summary.value = await getAdminSummary()
  } catch (error: unknown) {
    summaryError.value = messageFrom(error, 'We could not load the platform summary.')
  } finally {
    isSummaryLoading.value = false
  }
}

async function loadUsers(page = 1): Promise<void> {
  isUsersLoading.value = true
  usersError.value = ''

  try {
    const response = await listAdminUsers({
      search: filters.search?.trim(),
      role: filters.role,
      plan_key: filters.plan_key,
      is_unlimited: filters.is_unlimited,
      page,
    })

    users.value = response.data
    pagination.value = response.meta
  } catch (error: unknown) {
    usersError.value = messageFrom(error, 'We could not load platform users.')
  } finally {
    isUsersLoading.value = false
  }
}

onMounted(() => {
  void Promise.all([loadSummary(), loadUsers()])
})
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">Platform administration</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Admin Panel</h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
          Monitor users, credits, tool availability and mock generation activity.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <RouterLink
            to="/admin/tools"
            class="inline-flex min-h-11 items-center rounded-xl border border-emerald-800 bg-emerald-950/40 px-4 text-sm font-semibold text-emerald-300 transition hover:border-emerald-600 hover:bg-emerald-950"
          >
            Manage creator tools
          </RouterLink>
          <RouterLink
            to="/admin/plans"
            class="inline-flex min-h-11 items-center rounded-xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-300 transition hover:bg-neutral-800"
          >
            Manage plans
          </RouterLink>
        </div>
      </header>

      <section class="mt-9">
        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              Platform summary
            </p>
            <h2 class="mt-2 text-xl font-semibold">Current activity</h2>
          </div>
          <button
            type="button"
            :disabled="isSummaryLoading"
            class="text-xs font-semibold text-emerald-400 disabled:text-neutral-600"
            @click="loadSummary"
          >
            Refresh
          </button>
        </div>

        <div v-if="isSummaryLoading" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="index in 6"
            :key="index"
            class="h-24 animate-pulse rounded-2xl bg-neutral-900"
          />
        </div>
        <div
          v-else-if="summaryError"
          role="alert"
          class="mt-4 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-200"
        >
          {{ summaryError }}
        </div>
        <template v-else-if="summary">
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <article
              v-for="card in summaryCards"
              :key="card.label"
              class="rounded-2xl border border-neutral-800 bg-neutral-900/70 p-4"
            >
              <p class="text-2xl font-bold">{{ card.value }}</p>
              <p class="mt-2 text-xs leading-5 text-neutral-500">{{ card.label }}</p>
            </article>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article class="rounded-2xl border border-neutral-800 bg-neutral-900/50 p-4">
              <h3 class="text-sm font-semibold">Projects by type</h3>
              <dl class="mt-3 space-y-2 text-xs">
                <div
                  v-for="(count, type) in summary.projects_by_type"
                  :key="type"
                  class="flex justify-between gap-4 text-neutral-400"
                >
                  <dt>{{ formatLabel(type) }}</dt>
                  <dd class="font-semibold text-neutral-200">{{ count }}</dd>
                </div>
              </dl>
            </article>
            <article class="rounded-2xl border border-neutral-800 bg-neutral-900/50 p-4">
              <h3 class="text-sm font-semibold">Generations by job</h3>
              <dl class="mt-3 space-y-2 text-xs">
                <div
                  v-for="(count, job) in summary.generations_by_job_type"
                  :key="job"
                  class="flex justify-between gap-4 text-neutral-400"
                >
                  <dt>{{ formatLabel(job) }}</dt>
                  <dd class="font-semibold text-neutral-200">{{ count }}</dd>
                </div>
              </dl>
            </article>
          </div>
        </template>
      </section>

      <section class="mt-10">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
          User management
        </p>
        <h2 class="mt-2 text-xl font-semibold">Platform users</h2>

        <form
          class="mt-5 grid gap-3 rounded-3xl border border-neutral-800 bg-neutral-900/60 p-4 sm:grid-cols-2 lg:grid-cols-5"
          @submit.prevent="loadUsers(1)"
        >
          <input
            v-model="filters.search"
            name="search"
            type="search"
            placeholder="Search name or email"
            class="min-h-11 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-500 lg:col-span-2"
          />
          <select
            v-model="filters.role"
            name="role"
            class="min-h-11 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-500"
          >
            <option value="">All roles</option>
            <option v-for="role in roles" :key="role" :value="role">{{ formatLabel(role) }}</option>
          </select>
          <select
            v-model="filters.plan_key"
            name="plan_key"
            class="min-h-11 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-500"
          >
            <option value="">All plans</option>
            <option v-for="plan in plans" :key="plan" :value="plan">{{ formatLabel(plan) }}</option>
          </select>
          <select
            v-model="filters.is_unlimited"
            name="is_unlimited"
            class="min-h-11 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-500"
          >
            <option value="">All access</option>
            <option value="1">Unlimited</option>
            <option value="0">Credit based</option>
          </select>
          <button
            type="submit"
            :disabled="isUsersLoading"
            class="min-h-11 rounded-xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:bg-neutral-700 sm:col-span-2 lg:col-span-5"
          >
            {{ isUsersLoading ? 'Loading users...' : 'Apply filters' }}
          </button>
        </form>

        <div
          v-if="usersError"
          role="alert"
          class="mt-4 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-200"
        >
          {{ usersError }}
        </div>
        <p v-else-if="isUsersLoading" class="mt-5 text-sm text-neutral-500">Loading users...</p>
        <div
          v-else-if="users.length === 0"
          class="mt-5 rounded-3xl border border-dashed border-neutral-800 p-6 text-sm text-neutral-500"
        >
          No users match these filters.
        </div>
        <div v-else class="mt-5 grid gap-3 lg:grid-cols-2">
          <article
            v-for="user in users"
            :key="user.id"
            class="rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h3 class="truncate font-semibold">{{ user.name }}</h3>
                <p class="mt-1 truncate text-xs text-neutral-500">{{ user.email }}</p>
              </div>
              <span
                v-if="user.is_unlimited"
                class="rounded-full border border-emerald-800 bg-emerald-950 px-2.5 py-1 text-[10px] font-semibold text-emerald-300"
              >
                Unlimited
              </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs text-neutral-400">
              <span class="rounded-full bg-neutral-800 px-2.5 py-1">{{
                formatLabel(user.role)
              }}</span>
              <span class="rounded-full bg-neutral-800 px-2.5 py-1">{{
                formatLabel(user.plan_key)
              }}</span>
              <span>{{ user.credits_balance }} credits</span>
            </div>
            <div class="mt-4 flex items-center justify-between gap-4 text-xs text-neutral-500">
              <span
                >{{ user.projects_count }} projects · {{ user.generations_count }} generations</span
              >
              <span>{{ formatDate(user.created_at) }}</span>
            </div>
            <RouterLink
              :to="'/admin/users/' + user.id"
              class="mt-5 flex min-h-11 items-center justify-center rounded-xl border border-neutral-700 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800"
            >
              Manage
            </RouterLink>
          </article>
        </div>

        <div
          v-if="pagination && pagination.last_page > 1"
          class="mt-5 flex items-center justify-between gap-4"
        >
          <button
            type="button"
            :disabled="pagination.current_page <= 1 || isUsersLoading"
            class="min-h-10 rounded-xl border border-neutral-700 px-4 text-sm disabled:opacity-40"
            @click="loadUsers(pagination.current_page - 1)"
          >
            Previous
          </button>
          <p class="text-xs text-neutral-500">
            Page {{ pagination.current_page }} of {{ pagination.last_page }}
          </p>
          <button
            type="button"
            :disabled="pagination.current_page >= pagination.last_page || isUsersLoading"
            class="min-h-10 rounded-xl border border-neutral-700 px-4 text-sm disabled:opacity-40"
            @click="loadUsers(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </section>
    </div>
  </main>
</template>
