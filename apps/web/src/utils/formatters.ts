export function formatLabel(value: string | undefined): string {
  if (!value) {
    return '-'
  }

  return value.replace(/_/g, ' ').replace(/\b\w/g, (character: string) => character.toUpperCase())
}

export function formatDate(value: string | null): string {
  if (!value) {
    return 'Date unavailable'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Date unavailable'
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}
