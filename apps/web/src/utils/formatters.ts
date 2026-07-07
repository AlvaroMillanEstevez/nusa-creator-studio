import { currentLocale, t } from '@/i18n'

export function formatLabel(value: string | undefined): string {
  if (!value) {
    return '-'
  }

  return value.replace(/_/g, ' ').replace(/\b\w/g, (character: string) => character.toUpperCase())
}

export function formatDate(value: string | null): string {
  if (!value) {
    return t('common.dateUnavailable')
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return t('common.dateUnavailable')
  }

  return new Intl.DateTimeFormat(currentLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value).format(value)
}

export function formatCurrency(value: number, currency: string): string {
  return new Intl.NumberFormat(currentLocale.value === 'id' ? 'id-ID' : currentLocale.value, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}
