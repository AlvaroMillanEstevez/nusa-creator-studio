const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL ?? ''
const API_BASE_URL = configuredBaseUrl.replace(/\/$/, '')
const API_ORIGIN = API_BASE_URL.replace(/\/api\/v1$/, '')

type ErrorPayloadRecord = Record<string, unknown>

const INTERNAL_ERROR_PATTERNS = [
  /SQLSTATE/i,
  /stack trace/i,
  /\b(?:App|Illuminate|Symfony)\\(?:[A-Za-z_][A-Za-z0-9_]*\\?)+/,
  /\b(?:PDOException|QueryException|ErrorException|TypeError|RuntimeException)\b/i,
  /(?:^|[\\/])vendor[\\/]/i,
  /(?:\.php:\d+|\.php on line \d+)/i,
  /#\d+\s+(?:[A-Za-z]:\\|\/)/,
  /\b(?:select|insert into|update|delete from)\b[\s\S]*\b(?:from|set|values|where)\b/i,
  /\bconnection:\s*\w+/i,
]

function errorPayload(value: unknown): ErrorPayloadRecord | null {
  return typeof value === 'object' && value !== null ? (value as ErrorPayloadRecord) : null
}

function safeMessage(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null
  }

  const message = value.trim()

  if (
    message.length === 0 ||
    message.length > 500 ||
    INTERNAL_ERROR_PATTERNS.some((pattern) => pattern.test(message))
  ) {
    return null
  }

  return message
}

function firstValidationMessage(payload: ErrorPayloadRecord | null): string | null {
  if (!payload || typeof payload.errors !== 'object' || payload.errors === null) {
    return null
  }

  for (const value of Object.values(payload.errors)) {
    if (Array.isArray(value)) {
      for (const item of value) {
        const message = safeMessage(item)

        if (message) {
          return message
        }
      }
    } else {
      const message = safeMessage(value)

      if (message) {
        return message
      }
    }
  }

  return null
}

export function sanitizeApiErrorMessage(status: number, rawPayload: unknown): string {
  if (status === 401) {
    return 'Please log in to continue.'
  }

  if (status === 403) {
    return 'You do not have access to this resource.'
  }

  if (status === 404) {
    return 'This resource could not be found.'
  }

  if (status === 419) {
    return 'Your session expired. Please try again.'
  }

  if (status >= 500) {
    return 'Something went wrong. Please try again.'
  }

  const payload = errorPayload(rawPayload)

  if (status === 422) {
    return (
      firstValidationMessage(payload) ??
      safeMessage(payload?.message) ??
      'Please check the form and try again.'
    )
  }

  return safeMessage(payload?.message) ?? 'Something went wrong. Please try again.'
}

function csrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const tokenCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))

  return tokenCookie ? decodeURIComponent(tokenCookie.slice('XSRF-TOKEN='.length)) : null
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

function resolveApiUrl(pathOrUrl: string): string {
  if (/^https?:\/\//i.test(pathOrUrl)) {
    if (
      (API_BASE_URL && pathOrUrl.startsWith(API_BASE_URL)) ||
      (API_ORIGIN && pathOrUrl.startsWith(API_ORIGIN))
    ) {
      return pathOrUrl
    }

    throw new ApiError('This media URL is not allowed.', 400)
  }

  if (pathOrUrl.startsWith('/api/v1/')) {
    return API_ORIGIN + pathOrUrl
  }

  const normalizedPath = pathOrUrl.startsWith('/') ? pathOrUrl : '/' + pathOrUrl

  return API_BASE_URL + normalizedPath
}

async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData

  if (options.body && !isFormData && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const token = csrfToken()
  const method = options.method?.toUpperCase() ?? 'GET'

  if (token && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers.set('X-XSRF-TOKEN', token)
  }

  const response = await fetch(resolveApiUrl(path), {
    credentials: 'include',
    ...options,
    headers,
  })

  if (!response.ok) {
    let payload: unknown = null

    try {
      payload = await response.json()
    } catch {
      // The API may return an empty or non-JSON error response.
    }

    const payloadRecord = errorPayload(payload)
    const code = typeof payloadRecord?.code === 'string' ? payloadRecord.code : undefined

    throw new ApiError(sanitizeApiErrorMessage(response.status, payload), response.status, code)
  }

  return response.json() as Promise<T>
}

export async function apiGetBlob(pathOrUrl: string): Promise<Blob> {
  const response = await fetch(resolveApiUrl(pathOrUrl), {
    credentials: 'include',
    headers: {
      Accept: '*/*',
    },
  })

  if (!response.ok) {
    let payload: unknown = null

    try {
      payload = await response.json()
    } catch {
      // Binary delivery may return an empty or non-JSON error response.
    }

    const payloadRecord = errorPayload(payload)
    const code = typeof payloadRecord?.code === 'string' ? payloadRecord.code : undefined

    throw new ApiError(sanitizeApiErrorMessage(response.status, payload), response.status, code)
  }

  return response.blob()
}

export async function ensureCsrfCookie(): Promise<void> {
  const response = await fetch(API_ORIGIN + '/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  })

  if (!response.ok) {
    throw new ApiError(sanitizeApiErrorMessage(response.status, null), response.status)
  }
}

export function apiGet<T>(path: string): Promise<T> {
  return apiRequest<T>(path)
}

export function apiPost<T, TPayload>(path: string, payload: TPayload): Promise<T> {
  return apiRequest<T>(path, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function apiPatch<T, TPayload>(path: string, payload: TPayload): Promise<T> {
  return apiRequest<T>(path, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function apiPostForm<T>(path: string, payload: FormData): Promise<T> {
  return apiRequest<T>(path, {
    method: 'POST',
    body: payload,
  })
}

export function apiDelete<T>(path: string): Promise<T> {
  return apiRequest<T>(path, {
    method: 'DELETE',
  })
}

export { API_BASE_URL, API_ORIGIN }
