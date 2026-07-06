<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const navigationItems = computed(() => {
  const publicItems = [
    { label: 'Home', to: '/' },
    { label: 'Tools', to: '/tools' },
  ]

  if (auth.isAuthenticated) {
    return [
      ...publicItems,
      { label: 'Dashboard', to: '/dashboard' },
      { label: 'Create', to: '/campaigns/new' },
    ]
  }

  return [
    ...publicItems,
    { label: 'Login', to: '/login' },
    { label: 'Register', to: '/register' },
  ]
})

async function logout(): Promise<void> {
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
    class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-neutral-800 bg-neutral-900/80 p-1"
  >
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

    <button
      v-if="auth.isAuthenticated"
      type="button"
      :disabled="auth.isLoading"
      class="flex min-h-10 shrink-0 items-center justify-center rounded-xl px-3 text-xs font-semibold text-neutral-400 transition hover:bg-neutral-800 hover:text-white disabled:opacity-50"
      @click="logout"
    >
      Logout
    </button>
  </nav>
</template>
