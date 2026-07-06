import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { AdminSummary, AdminUserListResponse } from '@/types/admin'
import AdminUnavailableView from '@/views/AdminUnavailableView.vue'
import AdminView from '@/views/AdminView.vue'

const summary: AdminSummary = {
  total_users: 12,
  total_admins: 1,
  total_internal_creators: 2,
  total_subscribers: 4,
  total_free_users: 5,
  total_projects: 18,
  total_generations: 30,
  total_completed_generations: 27,
  total_failed_generations: 3,
  total_credits_spent: 24,
  total_unlimited_generations: 6,
  projects_by_type: {
    affiliate_campaign: 8,
    video_enhancer: 6,
    product_to_video: 4,
  },
  generations_by_job_type: {
    generate_affiliate_campaign: 14,
    generate_video_enhancer: 10,
    generate_product_video_script: 6,
  },
}

const usersResponse: AdminUserListResponse = {
  data: [
    {
      id: 9,
      name: 'Managed Creator',
      email: 'managed@example.test',
      role: 'subscriber',
      plan_key: 'creator',
      is_unlimited: false,
      monthly_credit_allowance: 100,
      credits_balance: 42,
      projects_count: 3,
      generations_count: 7,
      created_at: '2026-07-06T01:00:00.000000Z',
      updated_at: '2026-07-06T01:00:00.000000Z',
    },
  ],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
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
      { path: '/admin', component: AdminView },
      { path: '/admin/tools', component: { template: '<div />' } },
      { path: '/admin/plans', component: { template: '<div />' } },
      { path: '/admin/users/:id', component: { template: '<div />' } },
      { path: '/admin/unavailable', component: AdminUnavailableView },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
    ],
  })
}

async function mountAdmin(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const router = testRouter()
  vi.stubGlobal('fetch', fetchMock)
  await router.push('/admin')
  await router.isReady()

  const wrapper = mount(AdminView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return wrapper
}

describe('AdminView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders platform summary and the paginated user list', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      const payload = url.endsWith('/admin/summary') ? summary : usersResponse

      return new Response(JSON.stringify(payload), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = await mountAdmin(fetchMock)
    const text = wrapper.text()

    expect(wrapper.get('h1').text()).toBe('Admin Panel')
    expect(text).toContain('Total users')
    expect(text).toContain('12')
    expect(text).toContain('Total projects')
    expect(text).toContain('18')
    expect(text).toContain('Unlimited generations')
    expect(text).toContain('Managed Creator')
    expect(text).toContain('managed@example.test')
    expect(text).toContain('42 credits')
    expect(wrapper.find('a[href="/admin/users/9"]').text()).toBe('Manage')
    expect(wrapper.find('a[href="/admin/tools"]').text()).toBe('Manage creator tools')
    expect(wrapper.find('a[href="/admin/plans"]').text()).toBe('Manage plans')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/admin/summary',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/admin/users',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('shows a friendly message for unsafe admin API failures', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/admin/summary')) {
        return new Response(
          JSON.stringify({
            message: 'SQLSTATE[42P01]: select * from users in /var/www/vendor/File.php:12',
          }),
          {
            status: 500,
            headers: { 'Content-Type': 'application/json' },
          },
        )
      }

      return new Response(JSON.stringify(usersResponse), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = await mountAdmin(fetchMock)

    expect(wrapper.get('[role="alert"]').text()).toBe('Something went wrong. Please try again.')
    expect(wrapper.text()).not.toContain('SQLSTATE')
    expect(wrapper.text()).not.toContain('vendor')
  })

  it('renders a friendly unavailable state for non-admin users', async () => {
    const router = testRouter()
    await router.push('/admin/unavailable')
    await router.isReady()

    const wrapper = mount(AdminUnavailableView, {
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.get('h1').text()).toBe('Admin panel unavailable')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'does not have permission to access platform administration',
    )
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(true)
  })
})
