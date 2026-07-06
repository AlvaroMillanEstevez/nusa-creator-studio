import { afterEach, describe, expect, it, vi } from 'vitest'

import { createPinia, setActivePinia } from 'pinia'

import { API_BASE_URL, API_ORIGIN } from '@/services/api'
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

describe('auth store', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('uses the Sanctum and typed auth endpoints for the full session lifecycle', async () => {
    setActivePinia(createPinia())

    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }

      if (url.endsWith('/auth/logout')) {
        return new Response(JSON.stringify({ message: 'Logged out successfully.' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      if (url.endsWith('/auth/profile')) {
        return new Response(
          JSON.stringify({
            user: {
              ...user,
              name: 'Updated Creator',
              email: 'updated@example.com',
            },
          }),
          {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          },
        )
      }

      if (url.endsWith('/auth/password')) {
        return new Response(JSON.stringify({ message: 'Password updated successfully.' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ user }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore()

    await auth.fetchMe()
    expect(auth.user).toEqual(user)
    expect(auth.user?.role).toBe('free_user')
    expect(auth.user?.plan_key).toBe('free')
    expect(auth.user?.is_unlimited).toBe(false)
    expect(auth.user?.monthly_credit_allowance).toBe(20)
    expect(auth.isAuthenticated).toBe(true)

    await auth.login({ email: user.email, password: 'strong-password' })
    await auth.register({
      name: user.name,
      email: user.email,
      password: 'strong-password',
      password_confirmation: 'strong-password',
    })
    await auth.updateProfile({
      name: 'Updated Creator',
      email: 'updated@example.com',
    })
    expect(auth.user?.name).toBe('Updated Creator')
    expect(auth.user?.email).toBe('updated@example.com')

    await expect(
      auth.updatePassword({
        current_password: 'strong-password',
        password: 'new-strong-password',
        password_confirmation: 'new-strong-password',
      }),
    ).resolves.toBe('Password updated successfully.')

    await auth.logout()

    const requestedUrls = fetchMock.mock.calls.map(([input]) =>
      typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url,
    )

    expect(requestedUrls).toContain(API_BASE_URL + '/auth/me')
    expect(requestedUrls).toContain(API_BASE_URL + '/auth/login')
    expect(requestedUrls).toContain(API_BASE_URL + '/auth/register')
    expect(requestedUrls).toContain(API_BASE_URL + '/auth/profile')
    expect(requestedUrls).toContain(API_BASE_URL + '/auth/password')
    expect(requestedUrls).toContain(API_BASE_URL + '/auth/logout')
    expect(requestedUrls.filter((url) => url === API_ORIGIN + '/sanctum/csrf-cookie')).toHaveLength(
      5,
    )
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/auth/profile',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          name: 'Updated Creator',
          email: 'updated@example.com',
        }),
      }),
    )
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/auth/password',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          current_password: 'strong-password',
          password: 'new-strong-password',
          password_confirmation: 'new-strong-password',
        }),
      }),
    )
    expect(fetchMock.mock.calls.every(([, options]) => options?.credentials === 'include')).toBe(
      true,
    )
    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
  })
})
