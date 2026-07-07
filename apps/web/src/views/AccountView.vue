<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { ApiError } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { PasswordUpdatePayload, ProfileUpdatePayload } from '@/types/auth'
import { t } from '@/i18n'
import { formatDate } from '@/utils/formatters'

const auth = useAuthStore()
const user = computed(() => auth.user)

const profile = reactive<ProfileUpdatePayload>({
  name: '',
  email: '',
})
const profileError = ref('')
const profileSuccess = ref('')
const isProfileSaving = ref(false)

const password = reactive<PasswordUpdatePayload>({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const passwordError = ref('')
const passwordSuccess = ref('')
const isPasswordSaving = ref(false)

watch(
  () => auth.user,
  (currentUser) => {
    if (currentUser) {
      profile.name = currentUser.name
      profile.email = currentUser.email
    }
  },
  { immediate: true },
)

function requestErrorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError ? error.message : fallback
}

async function saveProfile(): Promise<void> {
  profileError.value = ''
  profileSuccess.value = ''

  if (!profile.name.trim() || !profile.email.trim()) {
    profileError.value = t('auth.enterNameEmail')
    return
  }

  isProfileSaving.value = true

  try {
    await auth.updateProfile({
      name: profile.name.trim(),
      email: profile.email.trim().toLowerCase(),
    })
    profileSuccess.value = t('account.profileUpdated')
  } catch (error: unknown) {
    profileError.value = requestErrorMessage(error, t('errors.profile'))
  } finally {
    isProfileSaving.value = false
  }
}

async function savePassword(): Promise<void> {
  passwordError.value = ''
  passwordSuccess.value = ''

  if (!password.current_password || !password.password || !password.password_confirmation) {
    passwordError.value = t('account.completePassword')
    return
  }

  if (password.password.length < 8) {
    passwordError.value = t('account.newPasswordMin')
    return
  }

  if (password.password !== password.password_confirmation) {
    passwordError.value = t('account.newPasswordMismatch')
    return
  }

  isPasswordSaving.value = true

  try {
    passwordSuccess.value = await auth.updatePassword({ ...password })
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
  } catch (error: unknown) {
    passwordError.value = requestErrorMessage(error, t('errors.password'))
  } finally {
    isPasswordSaving.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-neutral-950 text-white">
    <div class="mx-auto min-h-screen max-w-3xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="pt-12">
        <p class="text-sm font-medium text-emerald-400">{{ t('account.kicker') }}</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ t('account.title') }}</h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-neutral-400">
          {{ t('account.body') }}
        </p>
      </header>

      <template v-if="user">
        <section class="mt-9 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">{{ t('account.profile') }}</p>
          <h2 class="mt-2 text-xl font-semibold">{{ t('account.details') }}</h2>

          <form class="mt-6 space-y-5" novalidate @submit.prevent="saveProfile">
            <div>
              <label for="account-name" class="text-sm font-semibold text-neutral-200">{{ t('auth.name') }}</label>
              <input
                id="account-name"
                v-model="profile.name"
                name="name"
                type="text"
                autocomplete="name"
                class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
              />
            </div>

            <div>
              <label for="account-email" class="text-sm font-semibold text-neutral-200"
                >{{ t('auth.email') }}</label
              >
              <input
                id="account-email"
                v-model="profile.email"
                name="email"
                type="email"
                autocomplete="email"
                class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
              />
            </div>

            <p
              v-if="profileError"
              role="alert"
              class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200"
            >
              {{ profileError }}
            </p>
            <p
              v-if="profileSuccess"
              role="status"
              class="rounded-2xl border border-emerald-900/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200"
            >
              {{ profileSuccess }}
            </p>

            <button
              type="submit"
              :disabled="isProfileSaving"
              class="flex min-h-12 w-full items-center justify-center rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 sm:w-auto"
            >
              {{ isProfileSaving ? t('account.savingProfile') : t('account.saveProfile') }}
            </button>
          </form>
        </section>

        <section class="mt-6 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">{{ t('account.security') }}</p>
          <h2 class="mt-2 text-xl font-semibold">{{ t('account.updatePassword') }}</h2>
          <p class="mt-3 text-sm leading-6 text-neutral-500">
            {{ t('account.passwordBody') }}
          </p>

          <form class="mt-6 space-y-5" novalidate @submit.prevent="savePassword">
            <div>
              <label for="current-password" class="text-sm font-semibold text-neutral-200">
                {{ t('account.currentPassword') }}
              </label>
              <input
                id="current-password"
                v-model="password.current_password"
                name="current_password"
                type="password"
                autocomplete="current-password"
                class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
              />
            </div>

            <div>
              <label for="new-password" class="text-sm font-semibold text-neutral-200">
                {{ t('account.newPassword') }}
              </label>
              <input
                id="new-password"
                v-model="password.password"
                name="password"
                type="password"
                autocomplete="new-password"
                class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
              />
            </div>

            <div>
              <label for="password-confirmation" class="text-sm font-semibold text-neutral-200">
                {{ t('account.confirmNewPassword') }}
              </label>
              <input
                id="password-confirmation"
                v-model="password.password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
              />
            </div>

            <p
              v-if="passwordError"
              role="alert"
              class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm text-rose-200"
            >
              {{ passwordError }}
            </p>
            <p
              v-if="passwordSuccess"
              role="status"
              class="rounded-2xl border border-emerald-900/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200"
            >
              {{ passwordSuccess }}
            </p>

            <button
              type="submit"
              :disabled="isPasswordSaving"
              class="flex min-h-12 w-full items-center justify-center rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 sm:w-auto"
            >
              {{ isPasswordSaving ? t('account.updatingPassword') : t('account.updatePassword') }}
            </button>
          </form>
        </section>

        <section
          id="plan"
          class="mt-6 scroll-mt-6 rounded-3xl border border-emerald-900/60 bg-gradient-to-br from-emerald-950/50 to-neutral-900 p-5 sm:p-6"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('account.planAccess') }}
          </p>

          <div
            v-if="user.is_unlimited"
            class="mt-4 rounded-2xl border border-emerald-800/70 bg-neutral-950/50 p-4"
          >
            <h2 class="text-2xl font-bold">{{ t('dashboard.unlimitedTitle') }}</h2>
            <p class="mt-2 text-sm leading-6 text-emerald-100/70">
              {{ t('dashboard.unlimitedBody') }}
            </p>
          </div>

          <div v-else class="mt-4">
            <h2 class="text-2xl font-bold">
              {{ t('account.planName', { name: user.current_plan?.name ?? user.plan_label }) }}
            </h2>
            <div class="mt-5 grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-neutral-800 bg-neutral-950/50 p-4">
                <p class="text-2xl font-bold">{{ user.credits_balance }}</p>
                <p class="mt-1 text-xs text-neutral-500">{{ t('account.availableCredits') }}</p>
              </div>
              <div class="rounded-2xl border border-neutral-800 bg-neutral-950/50 p-4">
                <p class="text-2xl font-bold">
                  {{ user.current_plan?.monthly_credits ?? user.monthly_credit_allowance }}
                </p>
                <p class="mt-1 text-xs text-neutral-500">{{ t('account.monthlyCredits') }}</p>
              </div>
            </div>

            <ul
              v-if="user.current_plan?.features.length"
              class="mt-5 space-y-2 text-sm text-neutral-300"
            >
              <li v-for="feature in user.current_plan.features" :key="feature" class="flex gap-2">
                <span class="text-emerald-400" aria-hidden="true">✓</span>
                <span>{{ feature }}</span>
              </li>
            </ul>
          </div>

          <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
            <div class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-4">
              <dt class="text-neutral-500">{{ t('account.role') }}</dt>
              <dd class="mt-1 font-semibold text-neutral-200">{{ user.role_label }}</dd>
            </div>
            <div class="rounded-2xl border border-neutral-800/80 bg-neutral-950/40 p-4">
              <dt class="text-neutral-500">{{ t('account.plan') }}</dt>
              <dd class="mt-1 font-semibold text-neutral-200">{{ user.plan_label }}</dd>
            </div>
          </dl>

          <button
            v-if="!user.is_unlimited"
            type="button"
            disabled
            class="mt-5 min-h-12 w-full cursor-not-allowed rounded-2xl border border-neutral-700 bg-neutral-800 px-5 font-semibold text-neutral-400 sm:w-auto"
          >
            {{ t('account.manageSubscription') }}
          </button>
          <p v-if="!user.is_unlimited" class="mt-3 text-xs leading-5 text-neutral-500">
            {{ t('account.billingNotice') }}
          </p>
          <RouterLink
            to="/pricing"
            class="mt-4 inline-flex min-h-11 items-center rounded-xl border border-emerald-800 px-4 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-950"
          >
            {{ t('account.viewPlans') }}
          </RouterLink>
        </section>


        <section class="mt-6 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 sm:p-6">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('account.language') }}
          </p>
          <h2 class="mt-2 text-xl font-semibold">{{ t('account.preferredLanguage') }}</h2>
          <p class="mt-3 text-sm leading-6 text-neutral-500">
            {{ t('account.languageBody') }}
          </p>
          <LanguageSwitcher class="mt-5" :compact="false" />
        </section>

        <section class="mt-6 rounded-3xl border border-neutral-800 bg-neutral-900/40 p-5">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
            {{ t('account.metadata') }}
          </p>
          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="text-neutral-500">{{ t('account.memberSince') }}</dt>
              <dd class="text-right text-neutral-300">{{ formatDate(user.created_at) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-neutral-500">{{ t('account.userId') }}</dt>
              <dd class="font-mono text-xs text-neutral-500">#{{ user.id }}</dd>
            </div>
          </dl>
        </section>
      </template>
    </div>
  </main>
</template>
