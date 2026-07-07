<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { currentLocale, t } from '@/i18n'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import type { RegisterPayload } from '@/types/auth'
import { safeInternalRedirect } from '@/utils/redirects'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const validationError = ref('')

const form = reactive<RegisterPayload>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

onMounted(() => auth.clearError())

async function submit(): Promise<void> {
  validationError.value = ''
  auth.clearError()

  if (!form.name.trim() || !form.email.trim()) {
    validationError.value = t('auth.enterNameEmail')
    return
  }

  if (form.password.length < 8) {
    validationError.value = t('auth.passwordMin')
    return
  }

  if (form.password !== form.password_confirmation) {
    validationError.value = t('auth.passwordMismatch')
    return
  }

  try {
    await auth.register({
      name: form.name.trim(),
      email: form.email.trim().toLowerCase(),
      password: form.password,
      password_confirmation: form.password_confirmation,
      preferred_locale: currentLocale.value,
    })

    await router.push(safeInternalRedirect(route.query.redirect))
  } catch {
    // The store exposes a friendly server error.
  }
}
</script>

<template>
  <AuthLayout :alternate-label="t('nav.login')" alternate-to="/login">
    <section class="mt-12">
      <p class="text-sm font-medium text-emerald-400">{{ t('auth.startCreating') }}</p>
      <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ t('auth.createStudioAccount') }}</h1>
      <p class="mt-4 text-sm leading-6 text-neutral-400">
        {{ t('auth.registerIntro') }}
      </p>
    </section>

    <form
      class="mt-9 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 sm:p-6"
      novalidate
      @submit.prevent="submit"
    >
      <div>
        <label for="register-name" class="text-sm font-semibold text-neutral-200">{{ t('auth.name') }}</label>
        <input
          id="register-name"
          v-model="form.name"
          name="name"
          type="text"
          autocomplete="name"
          :placeholder="t('auth.creatorName')"
          class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
        />
      </div>

      <div class="mt-6">
        <label for="register-email" class="text-sm font-semibold text-neutral-200">{{ t('auth.email') }}</label>
        <input
          id="register-email"
          v-model="form.email"
          name="email"
          type="email"
          autocomplete="email"
          placeholder="creator@example.com"
          class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
        />
      </div>

      <div class="mt-6">
        <label for="register-password" class="text-sm font-semibold text-neutral-200"
          >{{ t('auth.password') }}</label
        >
        <input
          id="register-password"
          v-model="form.password"
          name="password"
          type="password"
          autocomplete="new-password"
          class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
        />
        <p class="mt-2 text-xs text-neutral-600">{{ t('auth.useAtLeast8') }}</p>
      </div>

      <div class="mt-6">
        <label for="register-confirmation" class="text-sm font-semibold text-neutral-200">
          {{ t('auth.confirmPassword') }}
        </label>
        <input
          id="register-confirmation"
          v-model="form.password_confirmation"
          name="password_confirmation"
          type="password"
          autocomplete="new-password"
          class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
        />
      </div>

      <div
        v-if="validationError || auth.error"
        role="alert"
        class="mt-5 rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200"
      >
        {{ validationError || auth.error }}
      </div>

      <button
        type="submit"
        :disabled="auth.isLoading"
        class="mt-6 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400"
      >
        <span
          v-if="auth.isLoading"
          class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
          aria-hidden="true"
        />
        {{ auth.isLoading ? t('auth.creatingAccount') : t('auth.createAccount') }}
      </button>

      <p class="mt-5 text-center text-sm text-neutral-500">
        {{ t('auth.alreadyHaveAccount') }}
        <RouterLink to="/login" class="font-semibold text-emerald-400 hover:text-emerald-300">
          {{ t('auth.login') }}
        </RouterLink>
      </p>
    </form>
  </AuthLayout>
</template>
