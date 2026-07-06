import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { AdminSubscriptionPlan } from '@/types/plan'
import AdminPlansView from '@/views/AdminPlansView.vue'

const plan: AdminSubscriptionPlan = {
  id: 3,
  key: 'creator',
  name: 'Creator',
  short_description: 'For active creators.',
  description: 'A balanced monthly plan.',
  price_amount: '199000.00',
  currency: 'IDR',
  billing_interval: 'month',
  monthly_credits: 300,
  max_projects_per_month: null,
  max_generations_per_month: null,
  is_public: true,
  is_featured: true,
  is_active: true,
  is_internal: false,
  sort_order: 30,
  cta_label: 'Choose Creator',
  features: ['300 monthly credits', 'More campaign variations'],
  limits: null,
  metadata: null,
  created_at: '2026-07-06T00:00:00.000000Z',
  updated_at: '2026-07-06T00:00:00.000000Z',
}

async function mountView(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/dashboard', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
      { path: '/admin', component: placeholder },
      { path: '/admin/plans', component: AdminPlansView },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  await router.push('/admin/plans')
  await router.isReady()

  const wrapper = mount(AdminPlansView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return wrapper
}

describe('AdminPlansView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders backend-managed plans', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: [plan] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountView(fetchMock)
    const card = wrapper.get('[data-testid="admin-plan-creator"]')

    expect(wrapper.get('h1').text()).toBe('Plan Management')
    expect(card.text()).toContain('Creator')
    expect(card.text()).toContain('300 credits')
    expect(card.text()).toContain('Public')
    expect(card.text()).toContain('Featured')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/admin/plans',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('submits the edited plan payload', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (_input, init) => {
      if (init?.method === 'PATCH') {
        const payload = JSON.parse(String(init.body))

        return new Response(JSON.stringify({ data: { ...plan, ...payload } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [plan] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = await mountView(fetchMock)

    await wrapper.get('[data-testid="admin-plan-creator"] button').trigger('click')
    await wrapper.get('input[name="price_amount"]').setValue(249000)
    await wrapper.get('input[name="monthly_credits"]').setValue(450)
    await wrapper.get('textarea[name="features"]').setValue(
      '450 monthly credits\nMore campaign variations',
    )
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const patchCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'PATCH')
    const payload = JSON.parse(String(patchCall?.[1]?.body))

    expect(patchCall?.[0]).toBe(API_BASE_URL + '/admin/plans/creator')
    expect(payload).toEqual(
      expect.objectContaining({
        name: 'Creator',
        price_amount: 249000,
        monthly_credits: 450,
        features: ['450 monthly credits', 'More campaign variations'],
        is_public: true,
        is_featured: true,
        is_active: true,
      }),
    )
    expect(wrapper.get('[role="status"]').text()).toBe('Plan settings saved.')
  })
})