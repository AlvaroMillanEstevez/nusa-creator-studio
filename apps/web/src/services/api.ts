const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL ?? ''
const API_BASE_URL = configuredBaseUrl.replace(/\/$/, '')

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

  const normalizedPath = path.startsWith('/') ? path : '/' + path
  const response = await fetch(API_BASE_URL + normalizedPath, {
    ...options,
    headers,
  })

  if (!response.ok) {
    let message = 'The API request failed. Please try again.'
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

export function apiGet<T>(path: string): Promise<T> {
  return apiRequest<T>(path)
}

export function apiPost<T, TPayload>(path: string, payload: TPayload): Promise<T> {
  return apiRequest<T>(path, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export { API_BASE_URL }
