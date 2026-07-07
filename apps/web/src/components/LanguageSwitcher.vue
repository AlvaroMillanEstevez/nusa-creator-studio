<script setup lang="ts">
import { computed, ref } from 'vue'

import { currentLocale, localeOptions, normalizeLocale, setLocale, t, type SupportedLocale } from '@/i18n'
import { ApiError } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

withDefaults(
  defineProps<{
    compact?: boolean
    label?: string
  }>(),
  {
    compact: true,
    label: undefined,
  },
)

const auth = useAuthStore()
const error = ref('')
const isSaving = ref(false)

const selectedLocale = computed<SupportedLocale>({
  get: () => currentLocale.value,
  set: (value) => {
    void changeLocale(value)
  },
})

async function changeLocale(value: string): Promise<void> {
  const locale = normalizeLocale(value) ?? 'en'
  error.value = ''
  setLocale(locale)

  if (!auth.user || auth.user.preferred_locale === locale) {
    return
  }

  isSaving.value = true

  try {
    await auth.updateProfile({
      name: auth.user.name,
      email: auth.user.email,
      preferred_locale: locale,
    })
  } catch (requestError: unknown) {
    error.value = requestError instanceof ApiError ? requestError.message : t('errors.profile')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <label class="block" :class="compact ? 'min-w-24' : ''">
    <span
      v-if="!compact"
      class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500"
    >
      {{ label ?? t('account.preferredLanguage') }}
    </span>
    <select
      v-model="selectedLocale"
      :aria-label="label ?? t('account.preferredLanguage')"
      :disabled="isSaving"
      class="min-h-10 rounded-xl border border-neutral-800 bg-neutral-950/80 px-2 text-xs font-semibold text-neutral-300 outline-none transition hover:border-neutral-700 focus:border-emerald-500 disabled:opacity-60"
      :class="compact ? 'w-full' : 'mt-2 w-full min-h-12 px-4 text-sm'"
    >
      <option v-for="option in localeOptions" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <span v-if="error && !compact" role="alert" class="mt-2 block text-xs text-rose-300">
      {{ error }}
    </span>
  </label>
</template>
