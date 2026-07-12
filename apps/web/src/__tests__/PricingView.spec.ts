import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import type { SubscriptionPlan } from '@/types/plan'
import PricingView from '@/views/PricingView.vue'

const plans: SubscriptionPlan[] = [
  {
    key: 'free',
    name: 'Free',
    short_description: 'Start creating.',
    description: null,
    price_amount: '0.00',
    currency: 'IDR',
    billing_interval: 'month',
    monthly_credits: 20,
    is_featured: false,
    cta_label: 'Start free',
    features: ['20 monthly credits'],
    limits: null,
    sort_order: 10,
  },
  {
    key: 'creator',
    name: 'Creator',
    short_description: 'For active creators.',
    description: null,
    price_amount: '199000.00',
    currency: 'IDR',
    billing_interval: 'month',
    monthly_credits: 300,
    is_featured: true,
    cta_label: 'Choose Creator',
    features: ['300 monthly credits', 'More campaign variations'],
    limits: null,
    sort_order: 30,
  },
  {
    key: 'internal_unlimited',
    name: 'Internal Unlimited',
    short_description: 'Private plan.',
    description: null,
    price_amount: null,
    currency: 'IDR',
    billing_interval: 'internal',
    monthly_credits: 0,
    is_featured: false,
    cta_label: null,
    features: ['Internal unlimited access'],
    limits: null,
    sort_order: 100,
  },
]

const user: AuthUser = {
  id: 1,
  name: 'Nusa Creator',
  email: 'creator@example.test',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 20,
  created_at: null,
}

async function mountPricing(authenticated: boolean) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = authenticated ? user : null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: PricingView },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
    ],
  })
  const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
    new Response(JSON.stringify({ data: plans }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  vi.stubGlobal('fetch', fetchMock)

  await router.push('/pricing')
  await router.isReady()

  const wrapper = mount(PricingView, {
    global: { plugins: [pinia, router] },
  })
  await flushPromises()

  return { wrapper, fetchMock }
}

describe('PricingView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads public plans, hides internal plans and highlights the featured plan', async () => {
    const { wrapper, fetchMock } = await mountPricing(false)

    expect(wrapper.get('h1').text()).toBe('Choose your creator plan')
    expect(wrapper.text()).toContain('Free')
    expect(wrapper.text()).toContain('Creator')
    expect(wrapper.text()).not.toContain('Internal Unlimited')
    expect(wrapper.get('[data-testid="pricing-plan-creator"]').text()).toContain('Recommended')
    expect(wrapper.get('[data-testid="featured-plan-badge"]').text()).toBe('Recommended')
    expect(wrapper.text()).toContain('Credits power your workspace')
    expect(wrapper.text()).not.toContain('Tool tiers are explained in Tools')
    expect(wrapper.text()).not.toContain('Basic, Advanced and Premium')
    expect(wrapper.get('[data-testid="pricing-tools-link"]').text()).toBe('View tools')
    expect(wrapper.get('[data-testid="pricing-credits-note"]').text()).toContain('Credits are internal Nusa platform credits')
    expect(wrapper.html().indexOf('data-testid="pricing-plan-creator"')).toBeLessThan(wrapper.html().indexOf('data-testid="pricing-credits-note"'))
    expect(wrapper.text()).not.toContain('Basic workflows are designed for fast content planning')
    expect(wrapper.text()).not.toContain('Advanced workflows use media inputs')
    expect(wrapper.text()).not.toContain('Premium workflows use expensive generative media')
    expect(wrapper.text()).toContain(
      'Billing will be connected when paid subscriptions are enabled.',
    )
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/plans',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('links guest CTAs to registration', async () => {
    const { wrapper } = await mountPricing(false)

    expect(
      wrapper.findAll('[data-testid^="pricing-plan-"] a[href="/register"]'),
    ).toHaveLength(2)
  })

  it('links authenticated CTAs to account plan management', async () => {
    const { wrapper } = await mountPricing(true)

    expect(
      wrapper.findAll('[data-testid^="pricing-plan-"] a[href="/account#plan"]'),
    ).toHaveLength(2)
  })
})
