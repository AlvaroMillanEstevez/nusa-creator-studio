import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import type { CreatorTool } from '@/types/tool'
import ToolsView from '@/views/ToolsView.vue'

const authenticatedUser: AuthUser = {
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
  credits_balance: 12,
  created_at: null,
}

function tool(overrides: Partial<CreatorTool> & Pick<CreatorTool, 'key' | 'title'>): CreatorTool {
  const { key, title, ...rest } = overrides

  return {
    key,
    title,
    short_description: 'Tool description.',
    long_description: 'Long tool description.',
    category: 'workflow',
    service_tier: 'basic',
    output_type: 'script_pack',
    implementation_stage: 'real_provider',
    status: 'available',
    route: '/dashboard',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'Available now',
    is_recommended: false,
    sort_order: 10,
    ...rest,
  }
}

const tools: CreatorTool[] = [
  tool({ key: 'affiliate_campaign', title: 'Product Content Kit', service_tier: 'basic', credit_cost: 1, credit_cost_label: '1 credit' }),
  tool({ key: 'video_to_campaign', title: 'Analyze Footage with AI', service_tier: 'advanced', credit_cost: 2, credit_cost_label: '2 credits' }),
  tool({ key: 'fashion_try_on', title: 'AI Fashion Try-On', service_tier: 'premium', credit_cost: 2, credit_cost_label: '2 credits' }),
  tool({ key: 'motion_ai_video', title: 'Motion AI Video', service_tier: 'premium', credit_cost: 3, credit_cost_label: '3 credits' }),
  tool({ key: 'auto_video_editor', title: 'Auto Video Editor', service_tier: 'premium', credit_cost: 2, credit_cost_label: '2 credits base' }),
  tool({ key: 'auto_video_ads', title: 'Auto Video Ads', service_tier: 'premium', status: 'coming_soon', route: null, credit_cost: 0, credit_cost_label: 'Credit cost coming soon', badge_label: 'Coming soon' }),
  tool({ key: 'ai_voiceover', title: 'AI Voiceover', service_tier: 'advanced', output_type: 'audio', credit_cost: 1, credit_cost_label: '1 credit' }),
]

async function mountToolsView(
  fetchMock: ReturnType<typeof vi.fn<typeof fetch>>,
  user: AuthUser | null = authenticatedUser,
) {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: ToolsView },
      { path: '/dashboard', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/account', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())
  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true

  await router.push('/tools')
  await router.isReady()

  const wrapper = mount(ToolsView, {
    global: {
      plugins: [pinia, router],
    },
  })
  await flushPromises()

  return wrapper
}

describe('ToolsView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the final available workflow catalog in the product order', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(Response.json({ data: tools }))

    const wrapper = await mountToolsView(fetchMock)
    const availableCards = wrapper.findAll('[data-workflow-card="available"]')
    const titles = availableCards.map((card) => card.find('h3').text())

    expect(wrapper.get('[data-testid="available-workflow-section"]').text()).toContain('Available workflows')
    expect(titles).toEqual([
      'Product Content Kit',
      'Analyze Footage with AI',
      'AI Fashion Try-On',
      'Motion AI Video',
      'Auto Video Editor',
    ])
    expect(wrapper.text()).not.toContain('AI Voiceover')
    expect(wrapper.text()).not.toContain('Supporting tools')
    expect(wrapper.text()).not.toContain('Mock beta')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('keeps Auto Video Ads as the only coming soon workflow', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(Response.json({ data: tools }))

    const wrapper = await mountToolsView(fetchMock)
    const comingSoonCards = wrapper.findAll('[data-workflow-card="coming-soon"]')

    expect(comingSoonCards).toHaveLength(1)
    expect(comingSoonCards[0]?.text()).toContain('Auto Video Ads')
    expect(comingSoonCards[0]?.element.tagName).toBe('ARTICLE')
    expect(comingSoonCards[0]?.attributes('aria-disabled')).toBe('true')
    expect(comingSoonCards[0]?.text()).toContain('Coming soon')
  })

  it('links available workflows for authenticated users', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(Response.json({ data: tools }))

    const wrapper = await mountToolsView(fetchMock)

    expect(wrapper.get('[data-testid="workflow-card-product_content_kit"]').attributes('href')).toBe('/dashboard?tool=product_content_kit')
    expect(wrapper.get('[data-testid="workflow-card-analyze_footage"]').attributes('href')).toBe('/dashboard?tool=video_to_campaign')
    expect(wrapper.get('[data-testid="workflow-card-motion_ai_video"]').attributes('href')).toBe('/dashboard?tool=motion_ai_video')
    expect(wrapper.get('[data-testid="workflow-card-auto_video_editor"]').attributes('href')).toBe('/dashboard?tool=auto_video_editor')
  })

  it('sends guests through login with the intended workflow destination', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(Response.json({ data: tools }))

    const wrapper = await mountToolsView(fetchMock, null)

    expect(wrapper.get('[data-testid="workflow-card-motion_ai_video"]').attributes('href')).toBe('/login?redirect=/dashboard?tool=motion_ai_video')
    expect(wrapper.get('[data-testid="workflow-card-motion_ai_video"]').text()).toContain('Log in to start')
  })

  it('shows a friendly retry state when the catalog cannot load', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Service unavailable.' }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)

    expect(wrapper.get('[role="alert"]').text()).toContain('Tools unavailable')
    expect(wrapper.get('[role="alert"]').text()).toContain('Something went wrong. Please try again.')
    expect(wrapper.get('[role=alert] button').text()).toBe('Try again')
  })
})
