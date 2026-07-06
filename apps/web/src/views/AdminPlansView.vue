<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { getAdminPlans, updateAdminPlan } from '@/services/admin'
import type {
  AdminSubscriptionPlan,
  AdminSubscriptionPlanUpdate,
  BillingInterval,
} from '@/types/plan'
import { formatLabel } from '@/utils/formatters'

const plans = ref<AdminSubscriptionPlan[]>([])
const selectedPlan = ref<AdminSubscriptionPlan | null>(null)
const featuresText = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref('')
const success = ref('')

const intervals: BillingInterval[] = ['month', 'year', 'one_time', 'internal']

const form = reactive<AdminSubscriptionPlanUpdate>({
  name: '',
  short_description: null,
  description: null,
  price_amount: null,
  currency: 'IDR',
  billing_interval: 'month',
  monthly_credits: 0,
  max_projects_per_month: null,
  max_generations_per_month: null,
  is_public: true,
  is_featured: false,
  is_active: true,
  is_internal: false,
  sort_order: 0,
  cta_label: null,
  features: [],
  limits: null,
  metadata: null,
})

function messageFrom(requestError: unknown, fallback: string): string {
  return requestError instanceof ApiError ? requestError.message : fallback
}

function optionalNumber(value: unknown): number | null {
  return value === '' || value === null ? null : Number(value)
}

function selectPlan(plan: AdminSubscriptionPlan): void {
  selectedPlan.value = plan
  featuresText.value = plan.features.join('\n')
  error.value = ''
  success.value = ''

  Object.assign(form, {
    name: plan.name,
    short_description: plan.short_description,
    description: plan.description,
    price_amount: optionalNumber(plan.price_amount),
    currency: plan.currency,
    billing_interval: plan.billing_interval,
    monthly_credits: plan.monthly_credits,
    max_projects_per_month: plan.max_projects_per_month,
    max_generations_per_month: plan.max_generations_per_month,
    is_public: plan.is_public,
    is_featured: plan.is_featured,
    is_active: plan.is_active,
    is_internal: plan.is_internal,
    sort_order: plan.sort_order,
    cta_label: plan.cta_label,
    features: [...plan.features],
    limits: plan.limits,
    metadata: plan.metadata,
  })
}

async function loadPlans(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    plans.value = await getAdminPlans()
  } catch (requestError: unknown) {
    error.value = messageFrom(requestError, 'We could not load plan management.')
  } finally {
    isLoading.value = false
  }
}

async function savePlan(): Promise<void> {
  if (!selectedPlan.value) {
    return
  }

  isSaving.value = true
  error.value = ''
  success.value = ''

  const payload: AdminSubscriptionPlanUpdate = {
    ...form,
    short_description: form.short_description?.trim() || null,
    description: form.description?.trim() || null,
    price_amount: optionalNumber(form.price_amount),
    currency: form.currency.trim().toUpperCase(),
    monthly_credits: Number(form.monthly_credits),
    max_projects_per_month: optionalNumber(form.max_projects_per_month),
    max_generations_per_month: optionalNumber(form.max_generations_per_month),
    sort_order: Number(form.sort_order),
    cta_label: form.cta_label?.trim() || null,
    features: featuresText.value
      .split('\n')
      .map((feature) => feature.trim())
      .filter(Boolean),
  }

  try {
    const updated = await updateAdminPlan(selectedPlan.value.key, payload)
    const index = plans.value.findIndex((plan) => plan.key === updated.key)

    if (index >= 0) {
      plans.value[index] = updated
    }

    selectPlan(updated)
    success.value = 'Plan settings saved.'
  } catch (requestError: unknown) {
    error.value = messageFrom(requestError, 'We could not save this plan.')
  } finally {
    isSaving.value = false
  }
}

function priceLabel(plan: AdminSubscriptionPlan): string {
  if (plan.price_amount === null) {
    return 'No public price'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: plan.currency,
    maximumFractionDigits: 0,
  }).format(Number(plan.price_amount))
}

onMounted(() => {
  void loadPlans()
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
            <h1 class="text-3xl font-bold leading-tight sm:text-4xl">Plan Management</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
              Manage placeholder pricing, monthly credits and public plan presentation.
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
        v-if="error && !selectedPlan"
        role="alert"
        class="mt-8 rounded-2xl border border-rose-900/60 bg-rose-950/30 p-4 text-sm text-rose-200"
      >
        {{ error }}
      </div>

      <div v-if="isLoading" class="mt-8 grid gap-4 lg:grid-cols-2">
        <div v-for="index in 4" :key="index" class="h-44 animate-pulse rounded-3xl bg-neutral-900" />
      </div>

      <div v-else class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,0.9fr)]">
        <section>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            Subscription catalog
          </p>
          <h2 class="mt-2 text-xl font-semibold">All plans</h2>

          <div class="mt-5 grid gap-3">
            <article
              v-for="plan in plans"
              :key="plan.key"
              :data-testid="'admin-plan-' + plan.key"
              class="rounded-3xl border p-5"
              :class="
                selectedPlan?.key === plan.key
                  ? 'border-emerald-700 bg-emerald-950/20'
                  : 'border-neutral-800 bg-neutral-900/70'
              "
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs text-neutral-500">{{ plan.key }}</p>
                  <h3 class="mt-1 font-semibold">{{ plan.name }}</h3>
                  <p class="mt-2 text-sm font-semibold text-emerald-300">
                    {{ priceLabel(plan) }}
                    <span class="font-normal text-neutral-500">/ {{ formatLabel(plan.billing_interval) }}</span>
                  </p>
                </div>
                <span
                  class="rounded-full border px-2.5 py-1 text-[10px] font-semibold"
                  :class="
                    plan.is_active
                      ? 'border-emerald-800 bg-emerald-950 text-emerald-300'
                      : 'border-rose-900 bg-rose-950 text-rose-300'
                  "
                >
                  {{ plan.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>

              <div class="mt-4 flex flex-wrap gap-2 text-xs text-neutral-400">
                <span class="rounded-full bg-neutral-800 px-2.5 py-1">
                  {{ plan.monthly_credits }} credits
                </span>
                <span v-if="plan.is_public" class="rounded-full bg-neutral-800 px-2.5 py-1">Public</span>
                <span v-if="plan.is_internal" class="rounded-full bg-neutral-800 px-2.5 py-1">Internal</span>
                <span v-if="plan.is_featured" class="rounded-full bg-emerald-950 px-2.5 py-1 text-emerald-300">Featured</span>
              </div>

              <button
                type="button"
                class="mt-5 min-h-10 w-full rounded-xl border border-neutral-700 text-sm font-semibold text-neutral-200 transition hover:bg-neutral-800"
                @click="selectPlan(plan)"
              >
                Edit plan
              </button>
            </article>
          </div>
        </section>

        <section class="rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 lg:sticky lg:top-5">
          <template v-if="selectedPlan">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              Editing {{ selectedPlan.key }}
            </p>
            <h2 class="mt-2 text-xl font-semibold">Plan settings</h2>

            <form class="mt-6 space-y-4" @submit.prevent="savePlan">
              <label class="block text-sm text-neutral-300">
                Name
                <input v-model="form.name" name="name" required class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
              </label>

              <label class="block text-sm text-neutral-300">
                Short description
                <textarea v-model="form.short_description" name="short_description" rows="2" class="mt-2 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-3 outline-none focus:border-emerald-500" />
              </label>

              <label class="block text-sm text-neutral-300">
                Description
                <textarea v-model="form.description" name="description" rows="3" class="mt-2 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-3 outline-none focus:border-emerald-500" />
              </label>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Price amount
                  <input v-model.number="form.price_amount" name="price_amount" type="number" min="0" class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
                <label class="block text-sm text-neutral-300">
                  Currency
                  <input v-model="form.currency" name="currency" maxlength="3" required class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 uppercase outline-none focus:border-emerald-500" />
                </label>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Billing interval
                  <select v-model="form.billing_interval" name="billing_interval" class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500">
                    <option v-for="interval in intervals" :key="interval" :value="interval">{{ formatLabel(interval) }}</option>
                  </select>
                </label>
                <label class="block text-sm text-neutral-300">
                  Monthly credits
                  <input v-model.number="form.monthly_credits" name="monthly_credits" type="number" min="0" required class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Project limit
                  <input v-model.number="form.max_projects_per_month" name="max_projects_per_month" type="number" min="0" placeholder="No limit" class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
                <label class="block text-sm text-neutral-300">
                  Generation limit
                  <input v-model.number="form.max_generations_per_month" name="max_generations_per_month" type="number" min="0" placeholder="No limit" class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm text-neutral-300">
                  Sort order
                  <input v-model.number="form.sort_order" name="sort_order" type="number" min="0" required class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
                <label class="block text-sm text-neutral-300">
                  CTA label
                  <input v-model="form.cta_label" name="cta_label" class="mt-2 min-h-11 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 outline-none focus:border-emerald-500" />
                </label>
              </div>

              <label class="block text-sm text-neutral-300">
                Features
                <textarea v-model="featuresText" name="features" rows="6" placeholder="One feature per line" class="mt-2 w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-3 outline-none focus:border-emerald-500" />
              </label>

              <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300">
                  <input v-model="form.is_active" name="is_active" type="checkbox" class="size-4 accent-emerald-500" />
                  Active
                </label>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300">
                  <input v-model="form.is_public" name="is_public" type="checkbox" class="size-4 accent-emerald-500" />
                  Public
                </label>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300">
                  <input v-model="form.is_featured" name="is_featured" type="checkbox" class="size-4 accent-emerald-500" />
                  Featured
                </label>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-950 px-3 text-sm text-neutral-300">
                  <input v-model="form.is_internal" name="is_internal" type="checkbox" class="size-4 accent-emerald-500" />
                  Internal
                </label>
              </div>

              <div v-if="error" role="alert" class="rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm text-rose-200">
                {{ error }}
              </div>
              <p v-if="success" role="status" class="text-sm font-medium text-emerald-300">{{ success }}</p>

              <button type="submit" :disabled="isSaving" class="min-h-12 w-full rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:bg-neutral-700">
                {{ isSaving ? 'Saving...' : 'Save plan settings' }}
              </button>
            </form>
          </template>

          <div v-else class="py-10 text-center">
            <h2 class="text-lg font-semibold">Select a plan to edit</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              Choose a plan to update pricing, credits and public presentation.
            </p>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>