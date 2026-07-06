<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import type { LoginPayload } from '@/types/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const validationError = ref('')

const form = reactive<LoginPayload>({
  email: '',
  password: '',
})

onMounted(() => auth.clearError())

async function submit(): Promise<void> {
  validationError.value = ''
  auth.clearError()

  if (!form.email.trim() || !form.password) {
    validationError.value = 'Enter your email and password.'
    return
  }

  try {
    await auth.login({
      email: form.email.trim().toLowerCase(),
      password: form.password,
    })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    await router.push(redirect)
  } catch {
    // The store exposes a friendly server error.
  }
}
</script>

<template>
  <AuthLayout alternate-label="Register" alternate-to="/register">
    <section class="mt-12">
      <p class="text-sm font-medium text-emerald-400">Creator account</p>
      <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">Welcome back.</h1>
      <p class="mt-4 text-sm leading-6 text-neutral-400">
        Log in to access your projects, generations and platform credits.
      </p>
    </section>

    <form
      class="mt-9 rounded-3xl border border-neutral-800 bg-neutral-900/70 p-5 sm:p-6"
      novalidate
      @submit.prevent="submit"
    >
      <div>
        <label for="login-email" class="text-sm font-semibold text-neutral-200">Email</label>
        <input
          id="login-email"
          v-model="form.email"
          name="email"
          type="email"
          autocomplete="email"
          placeholder="creator@example.com"
          class="mt-2 w-full rounded-2xl border border-neutral-800 bg-neutral-950 px-4 py-3.5 text-base text-white outline-none transition placeholder:text-neutral-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15"
        />
      </div>

      <div class="mt-6">
        <label for="login-password" class="text-sm font-semibold text-neutral-200">Password</label>
        <input
          id="login-password"
          v-model="form.password"
          name="password"
          type="password"
          autocomplete="current-password"
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
        {{ auth.isLoading ? 'Logging in...' : 'Log in' }}
      </button>

      <p class="mt-5 text-center text-sm text-neutral-500">
        New to Nusa?
        <RouterLink to="/register" class="font-semibold text-emerald-400 hover:text-emerald-300">
          Create an account
        </RouterLink>
      </p>
    </form>
  </AuthLayout>
</template>
