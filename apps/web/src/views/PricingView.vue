<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { ApiError } from '@/services/api'
import { getSubscriptionPlans } from '@/services/plans'
import { useAuthStore } from '@/stores/auth'
import type { BillingInterval, SubscriptionPlan } from '@/types/plan'

const auth = useAuthStore()
const plans = ref<SubscriptionPlan[]>([])
const isLoading = ref(true)
const error = ref('')

const publicPlans = computed(() =>
  plans.value
    .filter((plan) => plan.key !== 'internal_unlimited')
    .sort((left, right) => left.sort_order - right.sort_order),
)
const ctaRoute = computed(() => (auth.isAuthenticated ? '/account#plan' : '/register'))

function formatPrice(plan: SubscriptionPlan): string {
  if (plan.price_amount === null) {
    return 'Custom'
  }

  const amount = Number(plan.price_amount)

  if (amount === 0) {
    return 'Free'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: plan.currency,
    maximumFractionDigits: 0,
  }).format(amount)
}

function intervalLabel(interval: BillingInterval): string {
  if (interval === 'one_time') {
    return 'one time'
  }

  return interval
}

async function loadPlans(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    plans.value = await getSubscriptionPlans()
  } catch (requestError: unknown) {
    error.value =
      requestError instanceof ApiError
        ? requestError.message
        : 'We could not load creator plans. Please try again.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadPlans()
})
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="mx-auto max-w-3xl pt-14 text-center">
        <p class="text-sm font-medium text-emerald-400">Platform pricing</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight sm:text-5xl">Choose your creator plan</h1>
        <p class="mt-5 text-sm leading-7 text-neutral-400 sm:text-base">
          Plans provide internal platform credits for campaign, caption and creator workflows.
          Prices are placeholders while real provider costs are evaluated.
        </p>
      </header>

      <div v-if="isLoading" class="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="index in 4"
          :key="index"
          class="h-96 animate-pulse rounded-3xl border border-neutral-800 bg-neutral-900"
        />
      </div>

      <div
        v-else-if="error"
        role="alert"
        class="mx-auto mt-12 max-w-xl rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5 text-center"
      >
        <h2 class="text-lg font-semibold text-rose-100">Pricing unavailable</h2>
        <p class="mt-2 text-sm text-rose-200">{{ error }}</p>
        <button
          type="button"
          class="mt-5 min-h-11 rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950"
          @click="loadPlans"
        >
          Try again
        </button>
      </div>

      <section v-else class="mt-12 grid items-stretch gap-5 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="plan in publicPlans"
          :key="plan.key"
          :data-testid="'pricing-plan-' + plan.key"
          class="relative flex flex-col rounded-3xl border p-6"
          :class="
            plan.is_featured
              ? 'border-emerald-500 bg-gradient-to-b from-emerald-950/70 to-neutral-900 shadow-xl shadow-emerald-950/30'
              : 'border-neutral-800 bg-neutral-900/70'
          "
        >
          <span
            v-if="plan.is_featured"
            data-testid="featured-plan-badge"
            class="absolute -top-3 left-5 rounded-full bg-emerald-400 px-3 py-1 text-xs font-bold text-emerald-950"
          >
            Recommended
          </span>

          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ plan.monthly_credits }} monthly credits
          </p>
          <h2 class="mt-3 text-2xl font-bold">{{ plan.name }}</h2>
          <p class="mt-3 min-h-12 text-sm leading-6 text-neutral-400">
            {{ plan.short_description }}
          </p>

          <div class="mt-6">
            <p class="text-3xl font-bold">{{ formatPrice(plan) }}</p>
            <p v-if="plan.price_amount !== '0.00'" class="mt-1 text-xs text-neutral-500">
              per {{ intervalLabel(plan.billing_interval) }}
            </p>
          </div>

          <ul class="mt-6 flex-1 space-y-3 text-sm text-neutral-300">
            <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
              <span class="text-emerald-400" aria-hidden="true">✓</span>
              <span>{{ feature }}</span>
            </li>
          </ul>

          <RouterLink
            :to="ctaRoute"
            class="mt-7 flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold transition"
            :class="
              plan.is_featured
                ? 'bg-emerald-300 text-emerald-950 hover:bg-emerald-200'
                : 'bg-white text-neutral-950 hover:bg-neutral-200'
            "
          >
            {{ plan.cta_label ?? 'View plan' }}
          </RouterLink>
        </article>
      </section>

      <p
        class="mx-auto mt-10 max-w-2xl rounded-2xl border border-neutral-800 bg-neutral-900/50 p-4 text-center text-xs leading-5 text-neutral-500"
      >
        Billing will be connected when paid subscriptions are enabled. No checkout or payment is
        active yet.
      </p>
    </div>
  </main>
</template>