import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import App from '../App.vue'
import router from '../router'
import { pinia } from '../stores'
import { useAuthStore } from '../stores/auth'
import type { AuthUser } from '../types/auth'
import type { CreatorTool } from '../types/tool'

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
  credits_balance: 15,
  created_at: null,
}

const availableTool: CreatorTool = {
  key: 'affiliate_campaign',
  title: 'Product Content Kit',
  short_description: 'Generate a complete campaign pack.',
  long_description: null,
  category: 'campaign_creation',
  service_tier: 'basic',
  output_type: 'script_pack',
  implementation_stage: 'mock',
  status: 'available',
  route: '/campaigns/new',
  credit_cost: 1,
  credit_cost_label: '1 credit',
  badge_label: 'Available now',
  is_recommended: true,
  sort_order: 10,
}

describe('App', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('gives the home page a clear path to the creator studio', async () => {
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockImplementation(async (input) => {
        const url =
          typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

        if (url.endsWith('/auth/me')) {
          return new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
            status: 401,
            headers: { 'Content-Type': 'application/json' },
          })
        }

        return new Response(JSON.stringify({ status: 'ok', version: 'v1' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }),
    )

    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = false

    await router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
      global: {
        plugins: [pinia, router],
      },
    })

    await flushPromises()

    const studioLinks = wrapper.findAll('a[href="/tools"]')

    expect(studioLinks.some((link) => link.text().includes('Open Creator Studio'))).toBe(true)
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Product Content Kit')
    expect(wrapper.text()).not.toContain('Video Enhancer / Caption Pack')
    expect(wrapper.find('[data-testid^="tool-card-"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('API ok - v1')
  })
  it('hydrates the user menu when an authenticated user opens tools directly', async () => {
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/auth/me')) {
        return new Response(JSON.stringify({ user }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [availableTool] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = true
    await router.push('/')
    auth.initialized = false

    await router.push('/tools')
    const wrapper = mount(App, { global: { plugins: [pinia, router] } })
    await flushPromises()

    expect(wrapper.find('details').exists()).toBe(true)
    expect(wrapper.text()).toContain('Hi, Nusa')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
    const authenticatedToolCard = wrapper.get('[data-testid="workflow-card-product_content_kit"]')

    expect(authenticatedToolCard.text()).toContain('Start with this workflow')
    expect(authenticatedToolCard.attributes('href')).toBe('/dashboard?tool=product_content_kit')
    expect(
      fetchMock.mock.calls.filter(([input]) => String(input).includes('/auth/me')),
    ).toHaveLength(1)
    wrapper.unmount()
  })

  it('shows confirmed guest navigation and login tool CTAs after auth returns 401', async () => {
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/auth/me')) {
        return new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [availableTool] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const auth = useAuthStore(pinia)
    auth.user = null
    auth.initialized = true
    await router.push('/')
    auth.initialized = false

    await router.push('/tools')
    const wrapper = mount(App, { global: { plugins: [pinia, router] } })
    await flushPromises()

    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(true)
    expect(wrapper.find('details').exists()).toBe(false)
    const guestToolCard = wrapper.get('[data-testid="workflow-card-product_content_kit"]')

    expect(guestToolCard.text()).toContain('Log in to start')
    expect(guestToolCard.attributes('href')).toBe('/login?redirect=/dashboard?tool=product_content_kit')
    expect(auth.error).toBe('')
    wrapper.unmount()
  })
})
