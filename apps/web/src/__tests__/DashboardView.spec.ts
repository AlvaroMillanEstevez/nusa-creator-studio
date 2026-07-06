import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { DashboardSummary } from '@/types/dashboard'
import DashboardView from '@/views/DashboardView.vue'

const populatedDashboard: DashboardSummary = {
  user: {
    id: 1,
    name: 'Nusa Creator',
    email: 'creator@nusa.test',
    role: 'free_user',
    role_label: 'Free User',
    plan_key: 'free',
    plan_label: 'Free',
    is_unlimited: false,
    access_label: 'Current plan credits',
    monthly_credit_allowance: 20,
    credits_balance: 17,
  },
  access: {
    label: 'Free plan',
    is_unlimited: false,
    monthly_credit_allowance: 20,
    credits_balance: 17,
    can_generate_without_credits: false,
  },
  stats: {
    total_projects: 3,
    total_generations: 5,
    completed_generations: 4,
    failed_generations: 1,
    draft_projects: 2,
    total_credits_spent: 5,
    affiliate_campaign_projects: 1,
    video_enhancer_projects: 1,
    product_to_video_projects: 1,
  },
  recent_projects: [
    {
      id: 42,
      title: 'Portable key light launch',
      type: 'affiliate_campaign',
      status: 'draft',
      created_at: '2026-07-06T01:00:00.000000Z',
      updated_at: '2026-07-06T01:00:00.000000Z',
      generations_count: 2,
      latest_generation_status: 'completed',
      latest_generation_created_at: '2026-07-06T02:00:00.000000Z',
    },
  ],
  recent_generations: [
    {
      id: 8,
      project_id: 42,
      project_title: 'Portable key light launch',
      project_type: 'affiliate_campaign',
      job_type: 'generate_affiliate_campaign',
      provider: 'mock',
      status: 'completed',
      credits_charged: 1,
      cost_usd: '0.0000',
      created_at: '2026-07-06T02:00:00.000000Z',
      finished_at: '2026-07-06T02:00:00.000000Z',
    },
  ],
}

async function mountDashboard(summary: DashboardSummary) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: DashboardView },
      { path: '/account', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/video-enhancer/new', component: { template: '<div />' } },
      { path: '/product-to-video/new', component: { template: '<div />' } },
      { path: '/projects/:id', component: { template: '<div />' } },
    ],
  })
  const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
    new Response(JSON.stringify(summary), {
      status: 200,
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
    }),
  )
  vi.stubGlobal('fetch', fetchMock)

  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(DashboardView, {
    global: {
      plugins: [createPinia(), router],
    },
  })

  await flushPromises()

  return { wrapper, fetchMock }
}

describe('DashboardView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders credits, stats, recent activity, and all quick action links', async () => {
    const { wrapper, fetchMock } = await mountDashboard(populatedDashboard)
    const text = wrapper.text()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/dashboard',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
    expect(wrapper.get('[aria-label="Primary navigation"]').attributes('aria-label')).toBe(
      'Primary navigation',
    )
    expect(text).toContain('17')
    expect(text).toContain('Free plan')
    expect(text).toContain('20 credits monthly allowance')
    expect(text).toContain('available internal credits')
    expect(text).toContain('Total projects')
    expect(text).toContain('Completed generations')
    expect(text).toContain('Portable key light launch')
    expect(text).toContain('Affiliate Campaign')
    expect(text).toContain('Generate Affiliate Campaign')
    expect(text).toContain('mock')
    expect(text).toContain('1 credit charged')

    expect(wrapper.find('a[href="/tools"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/account#plan"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/campaigns/new"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/video-enhancer/new"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/product-to-video/new"]').exists()).toBe(true)
    expect(wrapper.findAll('a[href="/projects/42"]')).toHaveLength(2)
  })

  it('renders unlimited internal access without a misleading credit balance', async () => {
    const { wrapper } = await mountDashboard({
      ...populatedDashboard,
      user: {
        ...populatedDashboard.user,
        role: 'internal_creator',
        role_label: 'Internal Creator',
        plan_key: 'internal_unlimited',
        plan_label: 'Internal Unlimited',
        is_unlimited: true,
        access_label: 'Unlimited internal access',
        credits_balance: 0,
      },
      access: {
        label: 'Unlimited internal access',
        is_unlimited: true,
        monthly_credit_allowance: 20,
        credits_balance: 0,
        can_generate_without_credits: true,
      },
      stats: {
        ...populatedDashboard.stats,
        total_credits_spent: 0,
      },
    })

    const text = wrapper.text()

    expect(text).toContain('Unlimited internal access')
    expect(text).toContain('Generations do not consume credits for this account.')
    expect(text).not.toContain('available internal credits')
    expect(text).not.toContain('0 credits monthly allowance')
  })

  it('shows friendly empty states without hiding quick actions', async () => {
    const { wrapper } = await mountDashboard({
      ...populatedDashboard,
      stats: {
        total_projects: 0,
        total_generations: 0,
        completed_generations: 0,
        failed_generations: 0,
        draft_projects: 0,
        total_credits_spent: 0,
        affiliate_campaign_projects: 0,
        video_enhancer_projects: 0,
        product_to_video_projects: 0,
      },
      recent_projects: [],
      recent_generations: [],
    })

    expect(wrapper.text()).toContain('No projects yet.')
    expect(wrapper.text()).toContain('Choose a creator tool to start your first project.')
    expect(wrapper.text()).toContain('No generated results yet.')
    expect(wrapper.text()).toContain(
      'Generated campaign, caption-pack and video-script results will appear here.',
    )
    expect(wrapper.find('a[href="/tools"]').exists()).toBe(true)
  })
})
