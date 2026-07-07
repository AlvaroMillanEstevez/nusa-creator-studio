<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const userMenu = ref<HTMLDetailsElement | null>(null)

const navigationItems = computed(() => {
  const publicItems = [
    { label: 'Home', to: '/' },
    { label: 'Tools', to: '/tools' },
    { label: 'Pricing', to: '/pricing' },
  ]

  if (!auth.initialized) {
    return publicItems
  }

  if (auth.isAuthenticated) {
    const authenticatedItems = [
      ...publicItems,
      { label: 'Dashboard', to: '/dashboard' },
      { label: 'Media', to: '/media' },
      { label: 'Create', to: '/campaigns/new' },
    ]

    if (auth.user?.role === 'admin') {
      authenticatedItems.push({ label: 'Admin', to: '/admin' })
    }

    return authenticatedItems
  }

  return [...publicItems, { label: 'Login', to: '/login' }, { label: 'Register', to: '/register' }]
})

const firstName = computed(() => auth.user?.name.trim().split(/\s+/)[0] ?? 'Creator')
const planBadge = computed(() =>
  auth.user?.is_unlimited ? 'Unlimited' : (auth.user?.plan_label ?? 'Free'),
)
const creditSummary = computed(() => {
  if (!auth.user || auth.user.is_unlimited) {
    return ''
  }

  return auth.user.credits_balance + ' credits'
})

function closeUserMenu(): void {
  userMenu.value?.removeAttribute('open')
}

async function logout(): Promise<void> {
  closeUserMenu()

  try {
    await auth.logout()
  } catch {
    // Local state is cleared even if the server session has already expired.
  }

  await router.push({ name: 'login' })
}
</script>

<template>
  <nav
    aria-label="Primary navigation"
    class="relative flex items-center gap-1 rounded-2xl border border-neutral-800 bg-neutral-900/80 p-1"
  >
    <div class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto">
      <RouterLink
        v-for="item in navigationItems"
        :key="item.to"
        :to="item.to"
        class="flex min-h-10 shrink-0 items-center justify-center rounded-xl px-3 text-xs font-semibold text-neutral-400 transition hover:bg-neutral-800 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        active-class="bg-neutral-800 text-white"
        :exact-active-class="item.to === '/' ? 'bg-neutral-800 text-white' : undefined"
      >
        {{ item.label }}
      </RouterLink>
    </div>

    <div
      v-if="!auth.initialized"
      data-testid="auth-nav-loading"
      class="flex min-h-10 shrink-0 items-center gap-2 rounded-xl border border-neutral-800 bg-neutral-950/60 px-3"
      aria-label="Checking session"
    >
      <span class="size-2 animate-pulse rounded-full bg-emerald-400" aria-hidden="true" />
      <span class="text-[10px] font-medium text-neutral-500">Checking session</span>
    </div>

    <details v-else-if="auth.user" ref="userMenu" class="group relative shrink-0">
      <summary
        class="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-neutral-700 bg-neutral-950/70 px-3 text-left transition hover:border-neutral-600 hover:bg-neutral-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 [&::-webkit-details-marker]:hidden"
      >
        <span class="max-w-20 truncate text-xs font-semibold text-neutral-100">
          Hi, {{ firstName }}
        </span>
        <span
          class="rounded-full border border-emerald-800/70 bg-emerald-950/60 px-2 py-0.5 text-[10px] font-semibold text-emerald-300"
        >
          {{ planBadge }}
        </span>
        <span class="text-[10px] text-neutral-500 group-open:rotate-180" aria-hidden="true">▼</span>
      </summary>

      <div
        class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 p-2 shadow-2xl shadow-black/50"
      >
        <div class="border-b border-neutral-800 px-3 pb-3 pt-2">
          <p class="truncate text-sm font-semibold text-white">{{ auth.user.name }}</p>
          <p class="mt-1 truncate text-xs text-neutral-500">{{ auth.user.email }}</p>
          <p class="mt-2 text-xs font-medium text-emerald-300">
            {{ auth.user.is_unlimited ? 'Unlimited internal access' : creditSummary }}
          </p>
        </div>

        <div class="py-2">
          <RouterLink
            to="/account"
            class="flex min-h-10 items-center rounded-xl px-3 text-sm text-neutral-300 transition hover:bg-neutral-800 hover:text-white"
            @click="closeUserMenu"
          >
            Account
          </RouterLink>
          <RouterLink
            to="/dashboard"
            class="flex min-h-10 items-center rounded-xl px-3 text-sm text-neutral-300 transition hover:bg-neutral-800 hover:text-white"
            @click="closeUserMenu"
          >
            Dashboard
          </RouterLink>
          <RouterLink
            to="/media"
            class="flex min-h-10 items-center rounded-xl px-3 text-sm text-neutral-300 transition hover:bg-neutral-800 hover:text-white"
            @click="closeUserMenu"
          >
            Media Library
          </RouterLink>
          <RouterLink
            to="/account#plan"
            class="flex min-h-10 items-center rounded-xl px-3 text-sm text-neutral-300 transition hover:bg-neutral-800 hover:text-white"
            @click="closeUserMenu"
          >
            Manage plan
          </RouterLink>
        </div>

        <button
          type="button"
          :disabled="auth.isLoading"
          class="flex min-h-10 w-full items-center rounded-xl border-t border-neutral-800 px-3 text-left text-sm text-rose-300 transition hover:bg-rose-950/40 disabled:opacity-50"
          @click="logout"
        >
          Logout
        </button>
      </div>
    </details>
  </nav>
</template>
