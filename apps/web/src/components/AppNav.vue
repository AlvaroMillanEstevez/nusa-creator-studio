<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import BrandLogo from '@/components/BrandLogo.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { t } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const userMenu = ref<HTMLDetailsElement | null>(null)

const navigationItems = computed(() => {
  const publicItems = [
    { labelKey: 'nav.home', to: '/' },
    { labelKey: 'nav.tools', to: '/tools' },
    { labelKey: 'nav.pricing', to: '/pricing' },
  ]

  if (!auth.initialized || !auth.isAuthenticated) {
    return publicItems
  }

  const authenticatedItems = [
    ...publicItems,
    { labelKey: 'nav.dashboard', to: '/dashboard' },
    { labelKey: 'nav.media', to: '/media' },
  ]

  if (auth.user?.role === 'admin') {
    authenticatedItems.push({ labelKey: 'nav.admin', to: '/admin' })
  }

  return authenticatedItems
})

const firstName = computed(() => auth.user?.name.trim().split(/\s+/)[0] ?? 'Creator')

const hasUnlimitedAccess = computed(() => {
  if (!auth.user) {
    return false
  }

  return (
    auth.user.is_unlimited ||
    auth.user.plan_key === 'internal_unlimited' ||
    auth.user.role === 'internal_creator'
  )
})

const planBadge = computed(() => {
  if (!auth.user) {
    return ''
  }

  return hasUnlimitedAccess.value ? t('nav.unlimited') : (auth.user.plan_label ?? t('nav.freePlan'))
})

const creditSummary = computed(() => {
  if (!auth.user || hasUnlimitedAccess.value) {
    return ''
  }

  return t('nav.credits', { count: auth.user.credits_balance })
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
  <header
    data-testid="app-shell"
    :aria-label="t('nav.appShell')"
    class="nusa-card relative z-20 rounded-[1.75rem] p-2"
  >
    <div
      data-testid="app-shell-top-row"
      class="flex min-w-0 items-center justify-between gap-3 rounded-3xl border border-white/5 bg-slate-950/45 px-3 py-3 sm:px-4"
    >
      <RouterLink
        to="/"
        :aria-label="t('brand.homeLabel')"
        class="nusa-focus-ring min-w-0 shrink rounded-2xl"
      >
        <BrandLogo class="max-w-[15rem]" />
      </RouterLink>

      <div class="flex min-w-0 shrink-0 items-center justify-end gap-2">
        <LanguageSwitcher class="w-24 shrink-0" />

        <div
          v-if="!auth.initialized"
          data-testid="auth-nav-loading"
          class="flex min-h-10 shrink-0 items-center gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 px-3"
          :aria-label="t('nav.checkingSession')"
        >
          <span class="size-2 animate-pulse rounded-full bg-cyan-300" aria-hidden="true" />
          <span class="hidden text-[10px] font-medium text-slate-500 sm:inline">
            {{ t('nav.checkingSession') }}
          </span>
        </div>

        <div
          v-else-if="!auth.user"
          :aria-label="t('nav.authActions')"
          class="flex shrink-0 items-center gap-1"
        >
          <RouterLink
            to="/login"
            class="nusa-focus-ring flex min-h-10 items-center rounded-2xl border border-slate-800 px-3 text-xs font-bold text-slate-300 transition hover:border-cyan-400/40 hover:text-white"
          >
            {{ t('nav.login') }}
          </RouterLink>
          <RouterLink
            to="/register"
            class="nusa-button-primary nusa-focus-ring hidden min-h-10 items-center rounded-2xl px-3 text-xs font-black sm:flex"
          >
            {{ t('nav.register') }}
          </RouterLink>
        </div>

        <details v-else ref="userMenu" class="group relative shrink-0">
          <summary
            :aria-label="t('nav.openUserMenu')"
            class="nusa-focus-ring flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-2xl border border-slate-700/80 bg-slate-950/80 px-2.5 text-left transition hover:border-cyan-400/40 hover:bg-slate-900 [&::-webkit-details-marker]:hidden"
          >
            <span class="hidden max-w-24 truncate text-xs font-bold text-slate-100 sm:inline">
              {{ t('nav.hi', { name: firstName }) }}
            </span>
            <span
              data-testid="plan-badge"
              class="rounded-full border px-2 py-0.5 text-[10px] font-black"
              :class="
                hasUnlimitedAccess
                  ? 'border-cyan-300/40 bg-cyan-950/50 text-cyan-200'
                  : 'border-emerald-300/30 bg-emerald-950/40 text-emerald-200'
              "
            >
              {{ planBadge }}
            </span>
            <span class="text-[10px] text-slate-500 transition group-open:rotate-180" aria-hidden="true">?</span>
          </summary>

          <div
            class="absolute right-0 z-30 mt-2 w-72 overflow-hidden rounded-3xl border border-slate-700/80 bg-slate-950 p-2 shadow-2xl shadow-black/60"
          >
            <div class="border-b border-slate-800 px-3 pb-3 pt-2">
              <p class="truncate text-sm font-bold text-white">{{ auth.user.name }}</p>
              <p class="mt-1 truncate text-xs text-slate-500">{{ auth.user.email }}</p>
              <p class="mt-2 text-xs font-semibold text-cyan-200">
                {{ hasUnlimitedAccess ? t('nav.unlimitedAccess') : creditSummary }}
              </p>
            </div>

            <div class="py-2">
              <RouterLink
                to="/account"
                class="flex min-h-10 items-center rounded-2xl px-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
                @click="closeUserMenu"
              >
                {{ t('nav.account') }}
              </RouterLink>
              <RouterLink
                to="/dashboard"
                class="flex min-h-10 items-center rounded-2xl px-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
                @click="closeUserMenu"
              >
                {{ t('nav.dashboard') }}
              </RouterLink>
              <RouterLink
                to="/media"
                class="flex min-h-10 items-center rounded-2xl px-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
                @click="closeUserMenu"
              >
                {{ t('nav.mediaLibrary') }}
              </RouterLink>
              <RouterLink
                to="/account#plan"
                class="flex min-h-10 items-center rounded-2xl px-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
                @click="closeUserMenu"
              >
                {{ t('nav.managePlan') }}
              </RouterLink>
            </div>

            <button
              type="button"
              :disabled="auth.isLoading"
              class="flex min-h-10 w-full items-center rounded-2xl border-t border-slate-800 px-3 text-left text-sm font-semibold text-rose-300 transition hover:bg-rose-950/40 disabled:opacity-50"
              @click="logout"
            >
              {{ t('nav.logout') }}
            </button>
          </div>
        </details>
      </div>
    </div>

    <nav
      data-testid="app-shell-nav-row"
      :aria-label="t('nav.primaryNavigation')"
      class="nusa-scrollbar mt-2 flex min-w-0 gap-2 overflow-x-auto px-1 pb-1"
    >
      <RouterLink
        v-for="item in navigationItems"
        :key="item.to"
        :to="item.to"
        class="nusa-focus-ring flex min-h-10 shrink-0 items-center justify-center rounded-2xl border border-transparent px-4 text-xs font-black text-slate-400 transition hover:border-slate-700 hover:bg-slate-900/75 hover:text-white"
        active-class="border-cyan-400/40 bg-cyan-400/10 text-cyan-100"
        :exact-active-class="item.to === '/' ? 'border-cyan-400/40 bg-cyan-400/10 text-cyan-100' : undefined"
      >
        {{ t(item.labelKey) }}
      </RouterLink>
    </nav>
  </header>
</template>
