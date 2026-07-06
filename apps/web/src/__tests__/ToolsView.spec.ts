import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { CreatorTool } from '@/types/tool'
import ToolsView from '@/views/ToolsView.vue'

const tools: CreatorTool[] = [
  {
    key: 'affiliate_campaign',
    title: 'Affiliate Campaign Generator',
    short_description: 'Generate a complete campaign pack.',
    long_description: 'Turn a brief into structured TikTok campaign content.',
    category: 'campaign_creation',
    status: 'available',
    route: '/campaigns/new',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'Available now',
    is_recommended: true,
    sort_order: 10,
  },
  {
    key: 'ai_voiceover',
    title: 'AI Voiceover',
    short_description: 'Create natural voice tracks.',
    long_description: null,
    category: 'audio',
    status: 'coming_soon',
    route: null,
    credit_cost: 1,
    credit_cost_label: 'Credit cost coming soon',
    badge_label: 'Coming soon',
    is_recommended: false,
    sort_order: 20,
  },
  {
    key: 'video_enhancer',
    title: 'Video Enhancer / Caption Pack',
    short_description: 'Polish an existing video.',
    long_description: null,
    category: 'video_production',
    status: 'maintenance',
    route: '/video-enhancer/new',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'Maintenance',
    is_recommended: false,
    sort_order: 30,
  },
  {
    key: 'hidden_tool',
    title: 'Disabled internal tool',
    short_description: 'Should never be displayed.',
    long_description: null,
    category: 'internal',
    status: 'disabled',
    route: '/hidden',
    credit_cost: 0,
    credit_cost_label: null,
    badge_label: null,
    is_recommended: false,
    sort_order: 40,
  },
]

async function mountToolsView(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: ToolsView },
      { path: '/dashboard', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
      { path: '/campaigns/new', component: placeholder },
      { path: '/video-enhancer/new', component: placeholder },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  await router.push('/tools')
  await router.isReady()

  const wrapper = mount(ToolsView, {
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return wrapper
}

describe('ToolsView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads tools from the API and links available tools', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const card = wrapper.get('[data-testid="tool-card-affiliate_campaign"]')

    expect(wrapper.text()).toContain('What do you want to create today?')
    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/campaigns/new')
    expect(card.text()).toContain('Affiliate Campaign Generator')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('keeps coming-soon and maintenance tools non-clickable and hides disabled tools', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const comingSoon = wrapper.get('[data-testid="tool-card-ai_voiceover"]')
    const maintenance = wrapper.get('[data-testid="tool-card-video_enhancer"]')

    expect(comingSoon.element.tagName).toBe('ARTICLE')
    expect(comingSoon.attributes('aria-disabled')).toBe('true')
    expect(comingSoon.find('a').exists()).toBe(false)
    expect(maintenance.element.tagName).toBe('ARTICLE')
    expect(maintenance.find('a').exists()).toBe(false)
    expect(maintenance.text()).toContain(
      'Temporarily unavailable while improvements are being made.',
    )
    expect(wrapper.find('[data-testid="tool-card-hidden_tool"]').exists()).toBe(false)
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
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Something went wrong. Please try again.',
    )
    expect(wrapper.get('button').text()).toBe('Try again')
  })
})