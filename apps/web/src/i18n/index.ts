import { ref } from 'vue'
import { createI18n } from 'vue-i18n'

import en from '@/i18n/locales/en'
import es from '@/i18n/locales/es'
import id from '@/i18n/locales/id'
import { localeLabels, supportedLocales, type SupportedLocale } from '@/i18n/types'

const STORAGE_KEY = 'nusa_locale'
const messages = { en, id, es }

export function normalizeLocale(value: string | null | undefined): SupportedLocale | null {
  if (!value) {
    return null
  }

  const normalized = value.toLowerCase().split('-')[0]

  return supportedLocales.includes(normalized as SupportedLocale)
    ? (normalized as SupportedLocale)
    : null
}

export function savedLocale(): SupportedLocale | null {
  if (typeof localStorage === 'undefined') {
    return null
  }

  return normalizeLocale(localStorage.getItem(STORAGE_KEY))
}

export function browserLocale(): SupportedLocale | null {
  if (typeof navigator === 'undefined') {
    return null
  }

  return normalizeLocale(navigator.language)
}

export function resolveInitialLocale(preferredLocale?: string | null): SupportedLocale {
  return normalizeLocale(preferredLocale) ?? savedLocale() ?? browserLocale() ?? 'en'
}

const initialLocale = resolveInitialLocale()

export const currentLocale = ref<SupportedLocale>(initialLocale)

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale,
  fallbackLocale: 'en',
  messages,
})

export const localeOptions = supportedLocales.map((value) => ({
  value,
  label: localeLabels[value],
}))

export function persistLocale(locale: SupportedLocale): void {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, locale)
  }
}

export function setLocale(locale: SupportedLocale, persist = true): void {
  currentLocale.value = locale
  i18n.global.locale.value = locale

  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale
  }

  if (persist) {
    persistLocale(locale)
  }
}

export function syncLocaleFromUser(preferredLocale: string | null | undefined): void {
  const locale = normalizeLocale(preferredLocale)

  if (locale) {
    setLocale(locale)
  }
}

export function t(key: string, values?: Record<string, unknown>): string {
  void currentLocale.value

  return i18n.global.t(key, values ?? {})
}

export function useTranslations(): {
  t: typeof t
  currentLocale: typeof currentLocale
} {
  return { t, currentLocale }
}

setLocale(initialLocale, false)
export { localeLabels, supportedLocales, type SupportedLocale }
