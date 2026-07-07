<script setup lang="ts">
import { RouterLink } from 'vue-router'

import BrandLogo from '@/components/BrandLogo.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { t } from '@/i18n'

defineProps<{
  alternateLabel: string
  alternateTo: string
}>()
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-lg px-5 pb-12 pt-6 sm:px-6">
      <header class="nusa-card flex items-center justify-between gap-4 rounded-[1.75rem] p-3">
        <RouterLink to="/" :aria-label="t('brand.homeLabel')" class="nusa-focus-ring rounded-2xl">
          <BrandLogo compact mark-size="md" />
        </RouterLink>
        <nav
          :aria-label="t('nav.authNavigation')"
          class="flex shrink-0 flex-wrap items-center justify-end gap-2 text-xs sm:gap-3"
        >
          <LanguageSwitcher />
          <RouterLink to="/" class="text-neutral-400 transition hover:text-white">
            {{ t('nav.backToHome') }}
          </RouterLink>
          <RouterLink
            :to="alternateTo"
            class="font-semibold text-emerald-400 transition hover:text-emerald-300"
          >
            {{ alternateLabel }}
          </RouterLink>
        </nav>
      </header>

      <slot />
    </div>
  </main>
</template>
