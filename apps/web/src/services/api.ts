const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL ?? ''
const API_BASE_URL = configuredBaseUrl.replace(/\/$/, '')
const API_ORIGIN = API_BASE_URL.replace(/\/api\/v1$/, '')

interface ApiErrorPayload {
  message: string
  code?: string
}

function hasErrorMessage(value: unknown): value is ApiErrorPayload {
  return (
    typeof value === 'object' &&
    value !== null &&
    'message' in value &&
    typeof value.message === 'string'
  )
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

async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (options.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const token = csrfToken()
  const method = options.method?.toUpperCase() ?? 'GET'

  if (token && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers.set('X-XSRF-TOKEN', token)
  }

  const normalizedPath = path.startsWith('/') ? path : '/' + path
  const response = await fetch(API_BASE_URL + normalizedPath, {
    credentials: 'include',
    ...options,
    headers,
  })

  if (!response.ok) {
    let message =
      response.status === 401
        ? 'Your session has expired. Please log in again.'
        : 'The API request failed. Please try again.'
    let code: string | undefined

    try {
      const payload: unknown = await response.json()

      if (hasErrorMessage(payload)) {
        message = payload.message
        code = payload.code
      }
    } catch {
      // The API may return an empty or non-JSON error response.
    }

    throw new ApiError(message, response.status, code)
  }

  return response.json() as Promise<T>
}

export async function ensureCsrfCookie(): Promise<void> {
  const response = await fetch(API_ORIGIN + '/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  })

  if (!response.ok) {
    throw new ApiError('We could not start a secure session. Please try again.', response.status)
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

export { API_BASE_URL, API_ORIGIN }
