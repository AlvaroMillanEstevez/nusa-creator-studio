import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { AdminCreatorTool } from '@/types/tool'
import AdminToolsView from '@/views/AdminToolsView.vue'

const tool: AdminCreatorTool = {
  id: 1,
  key: 'affiliate_campaign',
  title: 'Affiliate Campaign Generator',
  short_description: 'Generate a complete campaign pack.',
  long_description: 'Turn a product brief into campaign content.',
  category: 'campaign_creation',
  status: 'available',
  route: '/campaigns/new',
  credit_cost: 1,
  credit_cost_label: '1 credit',
  badge_label: 'Available now',
  is_recommended: true,
  is_visible: true,
  sort_order: 10,
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
      { path: '/dashboard', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
      { path: '/admin', component: placeholder },
      { path: '/admin/tools', component: AdminToolsView },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  await router.push('/admin/tools')
  await router.isReady()

  const wrapper = mount(AdminToolsView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return wrapper
}

describe('AdminToolsView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders all backend-managed tools with management fields', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: [tool] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountView(fetchMock)
    const card = wrapper.get('[data-testid="admin-tool-affiliate_campaign"]')

    expect(wrapper.get('h1').text()).toBe('Tool Management')
    expect(card.text()).toContain('Affiliate Campaign Generator')
    expect(card.text()).toContain('Available')
    expect(card.text()).toContain('/campaigns/new')
    expect(card.text()).toContain('Visible')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/admin/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('submits a typed tool update payload', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (_input, init) => {
      if (init?.method === 'PATCH') {
        const payload = JSON.parse(String(init.body))

        return new Response(
          JSON.stringify({
            data: {
              ...tool,
              ...payload,
              updated_at: '2026-07-06T01:00:00.000000Z',
            },
          }),
          {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
          },
        )
      }

      return new Response(JSON.stringify({ data: [tool] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = await mountView(fetchMock)

    await wrapper.get('[data-testid="admin-tool-affiliate_campaign"] button').trigger('click')
    await wrapper.get('select[name="status"]').setValue('maintenance')
    await wrapper.get('input[name="credit_cost"]').setValue(3)
    await wrapper.get('input[name="sort_order"]').setValue(2)
    await wrapper.get('input[name="badge_label"]').setValue('Improving')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const patchCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'PATCH')

    expect(patchCall).toBeDefined()
    expect(patchCall?.[0]).toBe(API_BASE_URL + '/admin/tools/affiliate_campaign')
    expect(patchCall?.[1]).toEqual(
      expect.objectContaining({
        method: 'PATCH',
        body: expect.any(String),
      }),
    )

    const payload = JSON.parse(String(patchCall?.[1]?.body))

    expect(payload).toEqual(
      expect.objectContaining({
        title: 'Affiliate Campaign Generator',
        status: 'maintenance',
        credit_cost: 3,
        sort_order: 2,
        badge_label: 'Improving',
        is_visible: true,
      }),
    )
    expect(wrapper.get('[role="status"]').text()).toBe('Tool settings saved.')
  })
})