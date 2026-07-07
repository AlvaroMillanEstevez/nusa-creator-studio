import { afterEach, describe, expect, it, vi } from 'vitest'

import {
  browserLocale,
  currentLocale,
  normalizeLocale,
  resolveInitialLocale,
  savedLocale,
  setLocale,
  t,
} from '@/i18n'

describe('i18n locale foundation', () => {
  afterEach(() => {
    setLocale('en', false)
    localStorage.clear()
    vi.unstubAllGlobals()
  })

  it('normalizes supported regional locales and rejects unsupported values', () => {
    expect(normalizeLocale('es-ES')).toBe('es')
    expect(normalizeLocale('id-ID')).toBe('id')
    expect(normalizeLocale('en-US')).toBe('en')
    expect(normalizeLocale('fr-FR')).toBeNull()
  })

  it('resolves locale using user preference, saved preference, browser language, then English', () => {
    localStorage.setItem('nusa_locale', 'id')
    expect(resolveInitialLocale('es')).toBe('es')
    expect(resolveInitialLocale()).toBe('id')

    localStorage.clear()
    vi.stubGlobal('navigator', { language: 'es-MX' })
    expect(browserLocale()).toBe('es')
    expect(resolveInitialLocale()).toBe('es')

    vi.stubGlobal('navigator', { language: 'fr-FR' })
    expect(resolveInitialLocale()).toBe('en')
  })

  it('persists manual locale changes and falls back to English messages', () => {
    setLocale('es')

    expect(currentLocale.value).toBe('es')
    expect(savedLocale()).toBe('es')
    expect(document.documentElement.lang).toBe('es')
    expect(t('auth.login')).toBe('Iniciar sesión')
    expect(t('app.name')).toBe('Nusa Creator Studio')
  })
})
