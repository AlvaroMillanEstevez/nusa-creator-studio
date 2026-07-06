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
  plan_key: 'free',
  is_unlimited: false,
  monthly_credit_allowance: 20,
  credits_balance: 20,
  created_at: '2026-07-06T00:00:00.000000Z',
}

describe('authentication route guard', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
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

  it('allows an initialized authenticated user into the dashboard', async () => {
    const auth = useAuthStore(pinia)
    auth.user = user
    auth.initialized = true

    await router.push('/dashboard')

    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})
