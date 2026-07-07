import { afterEach, describe, expect, it, vi } from 'vitest'

import router from '@/router'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'

const user: AuthUser = {
  id: 1,
  name: 'Nusa Creator',
  email: 'creator@example.com',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 20,
  created_at: '2026-07-06T00:00:00.000000Z',
}

describe('authentication route guard', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('hydrates an existing session when a public tools route opens', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = true
    await router.push('/')
    auth.initialized = false

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ user }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await router.push('/tools')

    expect(router.currentRoute.value.name).toBe('tools')
    expect(auth.user).toEqual(user)
    expect(auth.initialized).toBe(true)
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/auth/me')
  })

  it('confirms guest state without exposing an expected 401 as an auth error', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true
    await router.push('/')
    auth.user = null
    auth.initialized = false

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await router.push('/tools')

    expect(router.currentRoute.value.name).toBe('tools')
    expect(auth.user).toBeNull()
    expect(auth.initialized).toBe(true)
    expect(auth.error).toBe('')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('redirects an unauthenticated protected route to login', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = false

    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    await router.push('/')
    await router.push('/dashboard')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard')
  })

  it('redirects an unauthenticated media library route to login with a return URL', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = false

    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    await router.push('/')
    await router.push('/media')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/media')
  })

  it('redirects an unauthenticated account route to login with a return URL', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = false

    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    await router.push('/')
    await router.push('/account')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/account')
  })

  it('redirects unauthenticated admin access to login', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = false

    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    await router.push('/')
    await router.push('/admin')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/admin')
  })

  it('redirects an authenticated non-admin to the friendly unavailable page', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/admin')

    expect(router.currentRoute.value.name).toBe('admin-unavailable')
  })

  it('redirects an authenticated non-admin away from tool management', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/admin/tools')

    expect(router.currentRoute.value.name).toBe('admin-unavailable')
  })

  it('redirects an authenticated non-admin away from plan management', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/admin/plans')

    expect(router.currentRoute.value.name).toBe('admin-unavailable')
  })

  it('allows an admin into the admin panel', async () => {
    const auth = useAuthStore(pinia)
    auth.user = {
      ...user,
      role: 'admin',
      role_label: 'Admin',
    }
    auth.initialized = true

    await router.push('/admin')

    expect(router.currentRoute.value.name).toBe('admin')
  })

  it('uses the default app title for the home route', async () => {
    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = true

    await router.push('/pricing')
    await router.push('/')

    expect(document.title).toBe('Nusa Creator Studio')
  })

  it('updates the document title from route metadata', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/tools')
    expect(document.title).toBe('Tools · Nusa Creator Studio')

    await router.push('/projects/42')
    expect(document.title).toBe('Project · Nusa Creator Studio')
  })

  it('allows an initialized authenticated user into the dashboard', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/dashboard')

    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})
