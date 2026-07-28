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
    workspace_projects: 1,
    total_generations: 5,
    completed_generations: 4,
    failed_generations: 1,
    draft_projects: 2,
    total_credits_spent: 5,
    affiliate_campaign_projects: 1,
    video_enhancer_projects: 1,
    product_to_video_projects: 0,
    fashion_try_on_projects: 0,
    motion_ai_video_projects: 0,
  },
  recent_projects: [
    {
      id: 42,
      title: 'Portable key light launch',
      type: 'workspace',
      status: 'draft',
      created_at: '2026-07-06T01:00:00.000000Z',
      updated_at: '2026-07-06T01:30:00.000000Z',
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
      project_type: 'workspace',
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

async function mountDashboard(summary: DashboardSummary, path = '/dashboard') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/dashboard', component: DashboardView },
      { path: '/projects/new', component: { template: '<div />' } },
      { path: '/projects/:id', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
    ],
  })
  const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
    new Response(JSON.stringify(summary), {
      status: 200,
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
    }),
  )
  vi.stubGlobal('fetch', fetchMock)

  await router.push(path)
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

  it('renders project workspaces first with create and analytics actions', async () => {
    const { wrapper, fetchMock } = await mountDashboard(populatedDashboard)
    const text = wrapper.text()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/dashboard',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
    expect(wrapper.get('[aria-label="Primary navigation"]').attributes('aria-label')).toBe(
      'Primary navigation',
    )
    expect(text).toContain('Your project workspaces')
    expect(text.match(/Your project workspaces/g) ?? []).toHaveLength(1)
    expect(text).toContain('Start with a product workspace, then create content kits, analyze footage, generate Motion AI clips and edit creator videos from the same brief and assets.')
    expect(text).toContain('Recent projects')
    expect(text).toContain('Open a workspace to continue with Product Content Kit, Analyze Footage with AI, Motion AI Video or Auto Video Editor.')
    expect(wrapper.get('[data-testid="dashboard-beta-workflows"]').text()).toContain('Product Content Kit')
    expect(wrapper.get('[data-testid="dashboard-beta-workflows"]').text()).toContain('Auto Video Editor')
    expect(wrapper.get('[data-testid="dashboard-beta-workflows"]').text()).toContain('Auto Video Ads')
    expect(wrapper.get('[data-testid="dashboard-beta-workflows"]').text()).toContain('Motion AI Video')
    expect(text).toContain('Portable key light launch')
    expect(text).toContain('Workspace')
    expect(text).toContain('2 generations')
    expect(text).toContain('Latest: Completed')
    expect(text).toContain('Open workspace')
    expect(text).toContain('Workspace activity')
    expect(text).toContain('Analytics coming soon')
    expect(text).toContain('Recent generations')
    expect(text).toContain('Product Content Kit')
    expect(text).toContain('mock')
    expect(text).toContain('1 credit charged')

    expect(wrapper.find('a[href="/projects/new"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/dashboard#analytics"]').exists()).toBe(true)
    expect(wrapper.findAll('a[href="/projects/42"]')).toHaveLength(2)
    expect(text).not.toContain('Plan access')
    expect(text).not.toContain('Quick actions')
    expect(wrapper.find('a[href="/campaigns/new"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/video-enhancer/new"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/product-to-video/new"]').exists()).toBe(false)
  })

  it('shows the selected workflow as the top dashboard hero without duplicate create controls', async () => {
    const { wrapper } = await mountDashboard(populatedDashboard, '/dashboard?tool=ai_voiceover')
    const text = wrapper.text()

    expect(text).toContain('Selected workflow')
    expect(text).toContain('Start with AI Voiceover')
    expect(text).toContain('Choose a project workspace to continue with AI Voiceover.')
    expect(text).toContain('Choose existing project')
    expect(text).toContain('Open workspace and run from Continue from here')
    expect(text).toContain('View workspace activity')
    expect(text).not.toContain('Start with a product workspace, then create content kits, analyze footage, generate Motion AI clips and edit creator videos from the same brief and assets.')
    expect(text).not.toContain('Your project workspaces')
    expect(wrapper.findAll('a[href="/projects/new"]')).toHaveLength(1)
    expect(wrapper.find('a[href="/dashboard?tool=ai_voiceover#analytics"]').exists()).toBe(true)
  })

  it('shows selected workflow intents for beta workflow keys and legacy video analysis links', async () => {
    const productKit = await mountDashboard(populatedDashboard, '/dashboard?tool=product_content_kit')

    expect(productKit.wrapper.text()).toContain('Start with Product Content Kit')
    expect(productKit.wrapper.text()).toContain('Choose a project workspace to continue with Product Content Kit.')
    expect(productKit.wrapper.find('a[href="/dashboard?tool=product_content_kit#analytics"]').exists()).toBe(true)

    const videoAnalysis = await mountDashboard(populatedDashboard, '/dashboard?tool=video_to_campaign')

    expect(videoAnalysis.wrapper.text()).toContain('Start with Analyze Footage with AI')
    expect(videoAnalysis.wrapper.text()).toContain('Choose a project workspace to continue with Analyze Footage with AI.')
    expect(videoAnalysis.wrapper.find('a[href="/dashboard?tool=video_to_campaign#analytics"]').exists()).toBe(true)

    const autoEditor = await mountDashboard(populatedDashboard, '/dashboard?tool=auto_video_editor')

    expect(autoEditor.wrapper.text()).toContain('Start with Auto Video Editor')
    expect(autoEditor.wrapper.text()).toContain('Choose a project workspace to continue with Auto Video Editor.')
    expect(autoEditor.wrapper.find('a[href="/dashboard?tool=auto_video_editor#analytics"]').exists()).toBe(true)

    const autoVideoAds = await mountDashboard(populatedDashboard, '/dashboard?tool=auto_video_ads')

    expect(autoVideoAds.wrapper.text()).toContain('Start with Auto Video Ads')
    expect(autoVideoAds.wrapper.text()).toContain('Choose a project workspace to continue with Auto Video Ads.')
  })

  it('shows a create-first-project state when no workspaces exist', async () => {
    const { wrapper } = await mountDashboard({
      ...populatedDashboard,
      stats: {
        total_projects: 0,
        workspace_projects: 0,
        total_generations: 0,
        completed_generations: 0,
        failed_generations: 0,
        draft_projects: 0,
        total_credits_spent: 0,
        affiliate_campaign_projects: 0,
        video_enhancer_projects: 0,
        product_to_video_projects: 0,
        fashion_try_on_projects: 0,
        motion_ai_video_projects: 0,
      },
      recent_projects: [],
      recent_generations: [],
    })

    expect(wrapper.text()).toContain('No projects yet.')
    expect(wrapper.text()).toContain('Create your first project workspace to connect briefs, assets and generations.')
    expect(wrapper.text()).toContain('Create your first project')
    expect(wrapper.text()).toContain('No generated results yet.')
    expect(wrapper.find('a[href="/projects/new"]').exists()).toBe(true)

  })
})