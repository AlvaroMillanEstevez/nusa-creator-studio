import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { currentLocale, setLocale } from '@/i18n'
import { API_BASE_URL, API_ORIGIN } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import AccountView from '@/views/AccountView.vue'

const freeUser: AuthUser = {
  id: 7,
  name: 'Sari Creator',
  email: 'creator@example.test',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 13,
  preferred_locale: 'en',
  current_plan: {
    key: 'free',
    name: 'Free',
    monthly_credits: 20,
    price_amount: '0.00',
    currency: 'IDR',
    billing_interval: 'month',
    features: ['20 monthly credits', 'Saved project history'],
  },
  created_at: '2026-07-01T08:00:00.000000Z',
}

async function mountAccount(user: AuthUser) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/account', component: AccountView },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
    ],
  })

  const auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true

  await router.push('/account')
  await router.isReady()

  const wrapper = mount(AccountView, {
    global: { plugins: [pinia, router] },
  })

  return { wrapper, auth }
}

describe('AccountView', () => {
  afterEach(() => {
    setLocale('en', false)
    localStorage.clear()
    vi.unstubAllGlobals()
  })

  it('renders profile, security and free-plan access details', async () => {
    const { wrapper } = await mountAccount(freeUser)
    const text = wrapper.text()

    expect(wrapper.get('h1').text()).toBe('Account and access')
    expect((wrapper.get('input[name="name"]').element as HTMLInputElement).value).toBe(
      'Sari Creator',
    )
    expect((wrapper.get('input[name="email"]').element as HTMLInputElement).value).toBe(
      'creator@example.test',
    )
    expect(wrapper.get('input[name="current_password"]').attributes('type')).toBe('password')
    expect(text).toContain('Free plan')
    expect(text).toContain('Free User')
    expect(text).toContain('13')
    expect(text).toContain('Available credits')
    expect(text).toContain('20')
    expect(text).toContain('Monthly credits')
    expect(text).toContain('Saved project history')
    expect(text).toContain('Manage subscription')
    expect(
      wrapper.findAll('a[href="/pricing"]').some((link) => link.text() === 'View creator plans'),
    ).toBe(true)
    expect(text).toContain('Subscription management will be connected when billing is enabled.')
    expect(text).toContain('User ID')
    expect(text).toContain('#7')
  })

  it('submits profile and password updates through the typed auth store', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url === API_ORIGIN + '/sanctum/csrf-cookie') {
        return new Response(null, { status: 204 })
      }

      if (url === API_BASE_URL + '/auth/profile') {
        return new Response(
          JSON.stringify({
            user: {
              ...freeUser,
              name: 'Updated Creator',
              email: 'updated@example.test',
            },
          }),
          {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          },
        )
      }

      return new Response(JSON.stringify({ message: 'Password updated successfully.' }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper, auth } = await mountAccount(freeUser)

    await wrapper.get('input[name="name"]').setValue('Updated Creator')
    await wrapper.get('input[name="email"]').setValue('updated@example.test')
    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()

    expect(auth.user?.name).toBe('Updated Creator')
    expect(auth.user?.email).toBe('updated@example.test')
    expect(wrapper.text()).toContain('Profile updated successfully.')

    await wrapper.get('input[name="current_password"]').setValue('old-password')
    await wrapper.get('input[name="password"]').setValue('new-strong-password')
    await wrapper.get('input[name="password_confirmation"]').setValue('new-strong-password')
    await wrapper.findAll('form')[1]!.trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Password updated successfully.')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/auth/profile',
      expect.objectContaining({ method: 'PATCH' }),
    )
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/auth/password',
      expect.objectContaining({ method: 'PATCH' }),
    )
  })

  it('updates the preferred locale from the account language switcher', async () => {
    let profileMethod = ''
    let profilePayload: unknown = null
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url === API_ORIGIN + '/sanctum/csrf-cookie') {
        return new Response(null, { status: 204 })
      }

      if (url === API_BASE_URL + '/auth/profile') {
        profileMethod = init?.method ?? ''
        profilePayload = JSON.parse(String(init?.body))

        return new Response(JSON.stringify({ user: { ...freeUser, preferred_locale: 'id' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ message: 'Unexpected request.' }), { status: 500 })
    })
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper, auth } = await mountAccount(freeUser)
    const languageSelects = wrapper.findAll('select')
    const languageSelect = languageSelects[languageSelects.length - 1]

    expect(languageSelect?.exists()).toBe(true)

    await languageSelect!.setValue('id')
    await flushPromises()

    expect(currentLocale.value).toBe('id')
    expect(localStorage.getItem('nusa_locale')).toBe('id')
    expect(auth.user?.preferred_locale).toBe('id')
    expect(profileMethod).toBe('PATCH')
    expect(profilePayload).toMatchObject({
      name: freeUser.name,
      email: freeUser.email,
      preferred_locale: 'id',
    })
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/auth/profile',
      expect.objectContaining({ method: 'PATCH' }),
    )
  })

  it('renders the unlimited internal access card without billing controls', async () => {
    const { wrapper } = await mountAccount({
      ...freeUser,
      role: 'internal_creator',
      role_label: 'Internal Creator',
      plan_key: 'internal_unlimited',
      plan_label: 'Internal Unlimited',
      is_unlimited: true,
      access_label: 'Unlimited internal access',
      credits_balance: 0,
      current_plan: {
        key: 'internal_unlimited',
        name: 'Internal Unlimited',
        monthly_credits: 0,
        price_amount: null,
        currency: 'IDR',
        billing_interval: 'internal',
        features: ['Internal unlimited access'],
      },
    })

    const text = wrapper.text()

    expect(text).toContain('Unlimited internal access')
    expect(text).toContain('Generations do not consume credits for this account.')
    expect(text).toContain('Internal Creator')
    expect(text).toContain('Internal Unlimited')
    expect(text).not.toContain('Manage subscription')
    expect(text).not.toContain('Available credits')
  })
})
