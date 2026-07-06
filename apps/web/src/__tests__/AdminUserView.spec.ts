import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL, API_ORIGIN } from '@/services/api'
import type { AdminUserDetail } from '@/types/admin'
import AdminUserView from '@/views/AdminUserView.vue'

const detail: AdminUserDetail = {
  id: 9,
  name: 'Managed Creator',
  email: 'managed@example.test',
  role: 'free_user',
  plan_key: 'free',
  is_unlimited: false,
  monthly_credit_allowance: 20,
  credits_balance: 12,
  created_at: '2026-07-06T01:00:00.000000Z',
  updated_at: '2026-07-06T01:00:00.000000Z',
  stats: {
    projects_count: 1,
    generations_count: 2,
    completed_generations_count: 1,
    failed_generations_count: 1,
    credits_spent: 2,
  },
  recent_projects: [
    {
      id: 4,
      title: 'Creator campaign',
      type: 'affiliate_campaign',
      status: 'draft',
      created_at: '2026-07-06T01:00:00.000000Z',
    },
  ],
  recent_generations: [
    {
      id: 6,
      project_id: 4,
      project_title: 'Creator campaign',
      job_type: 'generate_affiliate_campaign',
      provider: 'mock',
      status: 'completed',
      credits_charged: 1,
      created_at: '2026-07-06T02:00:00.000000Z',
    },
  ],
}

function testRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
      { path: '/admin', component: { template: '<div />' } },
      { path: '/admin/users/:id', component: AdminUserView },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
    ],
  })
}

describe('AdminUserView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('submits only the typed access-management payload', async () => {
    const updatedDetail: AdminUserDetail = {
      ...detail,
      role: 'subscriber',
      plan_key: 'creator',
      is_unlimited: true,
      monthly_credit_allowance: 40,
      credits_balance: 99,
    }
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url === API_ORIGIN + '/sanctum/csrf-cookie') {
        return new Response(null, { status: 204 })
      }

      if (url.endsWith('/access')) {
        return new Response(JSON.stringify({ data: updatedDetail }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: detail }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const router = testRouter()
    await router.push('/admin/users/9')
    await router.isReady()

    const wrapper = mount(AdminUserView, {
      global: { plugins: [createPinia(), router] },
    })
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Managed Creator')
    expect(wrapper.text()).toContain('Creator campaign')
    expect(wrapper.text()).toContain('Credits spent')

    await wrapper.get('select[name="role"]').setValue('subscriber')
    await wrapper.get('select[name="plan_key"]').setValue('creator')
    await wrapper.get('input[name="credits_balance"]').setValue('99')
    await wrapper.get('input[name="monthly_credit_allowance"]').setValue('40')
    await wrapper.get('input[name="is_unlimited"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/admin/users/9/access',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          role: 'subscriber',
          plan_key: 'creator',
          is_unlimited: true,
          monthly_credit_allowance: 40,
          credits_balance: 99,
        }),
      }),
    )
    expect(wrapper.get('[role="status"]').text()).toBe('User access updated successfully.')
  })
})
