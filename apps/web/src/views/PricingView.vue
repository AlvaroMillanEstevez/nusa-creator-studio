<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getSubscriptionPlans } from '@/services/plans'
import { useAuthStore } from '@/stores/auth'
import type { BillingInterval, SubscriptionPlan } from '@/types/plan'
import { formatCurrency } from '@/utils/formatters'

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
    return t('pricing.custom')
  }

  const amount = Number(plan.price_amount)

  if (amount === 0) {
    return t('pricing.free')
  }

  return formatCurrency(amount, plan.currency)
}

function intervalLabel(interval: BillingInterval): string {
  if (interval === 'one_time') {
    return t('pricing.oneTime')
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
        : t('errors.plansLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadPlans()
})
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="mx-auto max-w-3xl pt-14 text-center">
        <p class="text-sm font-medium text-emerald-400">{{ t('pricing.kicker') }}</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight sm:text-5xl">{{ t('pricing.headline') }}</h1>
        <p class="mt-5 text-sm leading-7 text-neutral-400 sm:text-base">
          {{ t('pricing.body') }}
        </p>
      </header>

      <section class="nusa-card-soft mx-auto mt-10 max-w-3xl rounded-[2rem] p-5 text-center">
        <p class="text-sm leading-6 text-neutral-300">
          {{ t('pricing.subscriptionAccessNote') }}
        </p>
        <p class="mt-2 text-xs leading-5 text-neutral-500">
          {{ t('pricing.toolTiersExplained') }}
        </p>
        <RouterLink
          to="/tools"
          data-testid="pricing-tools-link"
          class="mt-4 inline-flex min-h-10 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-xs font-semibold text-neutral-100 transition hover:border-emerald-500 hover:bg-emerald-950/30"
        >
          {{ t('pricing.viewToolTiers') }}
        </RouterLink>
      </section>

      <div v-if="isLoading" class="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="index in 4"
          :key="index"
          class="h-96 animate-pulse rounded-[2rem] border border-slate-800/70 bg-slate-900/60"
        />
      </div>

      <div
        v-else-if="error"
        role="alert"
        class="mx-auto mt-12 max-w-xl rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5 text-center"
      >
        <h2 class="text-lg font-semibold text-rose-100">{{ t('pricing.unavailable') }}</h2>
        <p class="mt-2 text-sm text-rose-200">{{ error }}</p>
        <button
          type="button"
          class="mt-5 min-h-11 rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950"
          @click="loadPlans"
        >
          {{ t('common.tryAgain') }}
        </button>
      </div>

      <section v-else class="mt-12 grid items-stretch gap-5 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="plan in publicPlans"
          :key="plan.key"
          :data-testid="'pricing-plan-' + plan.key"
          class="nusa-card-interactive relative flex flex-col rounded-[2rem] p-6 transition"
          :class="
            plan.is_featured
              ? 'ring-1 ring-cyan-300/40'
              : ''
          "
        >
          <span
            v-if="plan.is_featured"
            data-testid="featured-plan-badge"
            class="absolute -top-3 left-5 rounded-full bg-white px-3 py-1 text-xs font-black text-slate-950 shadow-lg shadow-cyan-950/30"
          >
            {{ t('pricing.recommended') }}
          </span>

          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('pricing.monthlyCredits', { count: plan.monthly_credits }) }}
          </p>
          <h2 class="mt-3 text-2xl font-bold">{{ plan.name }}</h2>
          <p class="mt-3 min-h-12 text-sm leading-6 text-neutral-400">
            {{ plan.short_description }}
          </p>

          <div class="mt-6">
            <p class="text-3xl font-bold">{{ formatPrice(plan) }}</p>
            <p v-if="plan.price_amount !== '0.00'" class="mt-1 text-xs text-neutral-500">
              {{ t('pricing.per', { interval: intervalLabel(plan.billing_interval) }) }}
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
            class="nusa-focus-ring mt-7 flex min-h-12 items-center justify-center rounded-2xl px-4 text-sm font-black transition"
            :class="
              plan.is_featured
                ? 'bg-white text-slate-950 hover:bg-cyan-100'
                : 'border border-slate-700/80 bg-slate-950/70 text-slate-100 hover:border-cyan-300/40 hover:bg-slate-900'
            "
          >
            {{ plan.cta_label ?? t('pricing.viewPlan') }}
          </RouterLink>
        </article>
      </section>

      <p
        class="nusa-card-soft mx-auto mt-10 max-w-2xl rounded-2xl p-4 text-center text-xs leading-5 text-neutral-400"
      >
        {{ t('pricing.billingNotice') }}
      </p>
    </div>
  </main>
</template>
