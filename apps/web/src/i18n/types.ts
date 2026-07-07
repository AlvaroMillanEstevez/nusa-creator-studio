export const supportedLocales = ['en', 'id', 'es'] as const

export type SupportedLocale = (typeof supportedLocales)[number]

export const localeLabels: Record<SupportedLocale, string> = {
  en: 'English',
  id: 'Bahasa Indonesia',
  es: 'Español',
}
