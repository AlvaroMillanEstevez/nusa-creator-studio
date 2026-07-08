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

const tools: CreatorTool[] = [
  {
    key: 'affiliate_campaign',
    title: 'Affiliate Campaign Generator',
    short_description: 'Generate a complete campaign pack.',
    long_description: 'Turn a brief into structured TikTok campaign content.',
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
  },
  {
    key: 'ai_voiceover',
    title: 'AI Voiceover',
    short_description: 'Generate voiceover scripts and narration notes.',
    long_description: 'Turn a script or product brief into an audio-ready narration plan.',
    category: 'audio',
    service_tier: 'advanced',
    output_type: 'audio',
    implementation_stage: 'mock',
    status: 'available',
    route: '/voiceover/new',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'New mock',
    is_recommended: false,
    sort_order: 20,
  },
  {
    key: 'video_enhancer',
    title: 'Video Enhancer / Caption Pack',
    short_description: 'Polish an existing video.',
    long_description: null,
    category: 'video_production',
    service_tier: 'advanced',
    output_type: 'assisted_media',
    implementation_stage: 'mock',
    status: 'maintenance',
    route: '/video-enhancer/new',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'Maintenance',
    is_recommended: false,
    sort_order: 30,
  },
  {
    key: 'fashion_try_on',
    title: 'Fashion Try-On',
    short_description: 'Create try-on concepts.',
    long_description: null,
    category: 'fashion',
    service_tier: 'premium',
    output_type: 'image',
    implementation_stage: 'mock',
    status: 'available',
    route: '/fashion-try-on/new',
    credit_cost: 2,
    credit_cost_label: '2 credits',
    badge_label: 'New mock',
    is_recommended: false,
    sort_order: 35,
  },
  {
    key: 'motion_ai_video',
    title: 'Motion AI Video',
    short_description: 'Create motion-led concepts.',
    long_description: 'Turn visual references into a structured motion video plan.',
    category: 'ai_video',
    service_tier: 'premium',
    output_type: 'video',
    implementation_stage: 'mock',
    status: 'available',
    route: '/motion-video/new',
    credit_cost: 3,
    credit_cost_label: '3 credits',
    badge_label: 'New mock',
    is_recommended: false,
    sort_order: 38,
  },
  {
    key: 'future_tool',
    title: 'Future studio tool',
    short_description: 'Coming later.',
    long_description: null,
    category: 'future',
    service_tier: 'premium',
    output_type: 'avatar',
    implementation_stage: 'future',
    status: 'coming_soon',
    route: null,
    credit_cost: 0,
    credit_cost_label: 'Credit cost coming soon',
    badge_label: 'Coming soon',
    is_recommended: false,
    sort_order: 39,
  },
  {
    key: 'ai_avatar_presenter',
    title: 'AI Avatar Presenter',
    short_description: 'Create product presenter videos using an AI avatar and voice.',
    long_description: 'Future premium workflow for avatar-led product ads.',
    category: 'avatar',
    service_tier: 'premium',
    output_type: 'avatar',
    implementation_stage: 'future',
    status: 'coming_soon',
    route: null,
    credit_cost: 0,
    credit_cost_label: 'Credit cost coming soon',
    badge_label: 'Future',
    is_recommended: false,
    sort_order: 39,
  },
  {
    key: 'hidden_tool',
    title: 'Disabled internal tool',
    short_description: 'Should never be displayed.',
    long_description: null,
    category: 'internal',
    service_tier: 'basic',
    output_type: 'text_insight',
    implementation_stage: 'future',
    status: 'disabled',
    route: '/hidden',
    credit_cost: 0,
    credit_cost_label: null,
    badge_label: null,
    is_recommended: false,
    sort_order: 40,
  },
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
      { path: '/campaigns/new', component: placeholder },
      { path: '/video-enhancer/new', component: placeholder },
      { path: '/voiceover/new', component: placeholder },
      { path: '/fashion-try-on/new', component: placeholder },
      { path: '/motion-video/new', component: placeholder },
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
    expect(card.text()).toContain('Basic')
    expect(card.text()).toContain('Script pack')
    expect(card.text()).toContain('Mock')
    expect(card.text()).toContain('Open tool')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('groups tools by Basic, Advanced and Premium service tiers', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const basicSection = wrapper.get('[data-testid="tool-tier-section-basic"]')
    const advancedSection = wrapper.get('[data-testid="tool-tier-section-advanced"]')
    const premiumSection = wrapper.get('[data-testid="tool-tier-section-premium"]')

    expect(basicSection.text()).toContain('Basic')
    expect(basicSection.text()).toContain('Affiliate Campaign Generator')
    expect(advancedSection.text()).toContain('Advanced')
    expect(advancedSection.text()).toContain('AI Voiceover')
    expect(premiumSection.text()).toContain('Premium')
    expect(premiumSection.text()).toContain('Fashion Try-On')
    expect(premiumSection.text()).toContain('AI Avatar Presenter')
    expect(premiumSection.text()).toContain('Future')
  })

  it('links available AI Voiceover to the protected creation route for authenticated users', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const card = wrapper.get('[data-testid="tool-card-ai_voiceover"]')

    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/voiceover/new')
    expect(card.text()).toContain('AI Voiceover')
    expect(card.text()).toContain('Open tool')
  })
  it('links available Fashion Try-On to the protected creation route for authenticated users', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const card = wrapper.get('[data-testid="tool-card-fashion_try_on"]')

    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/fashion-try-on/new')
    expect(card.text()).toContain('Fashion Try-On')
    expect(card.text()).toContain('Open tool')
  })

  it('links available Motion AI Video to the protected creation route for authenticated users', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const card = wrapper.get('[data-testid="tool-card-motion_ai_video"]')

    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/motion-video/new')
    expect(card.text()).toContain('Motion AI Video')
    expect(card.text()).toContain('Open tool')
  })

  it('sends guests through login with the intended tool route', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock, null)
    const card = wrapper.get('[data-testid="tool-card-affiliate_campaign"]')
    const voiceoverCard = wrapper.get('[data-testid="tool-card-ai_voiceover"]')
    const fashionCard = wrapper.get('[data-testid="tool-card-fashion_try_on"]')
    const motionCard = wrapper.get('[data-testid="tool-card-motion_ai_video"]')

    expect(card.attributes('href')).toBe('/login?redirect=/campaigns/new')
    expect(card.text()).toContain('Log in to use')
    expect(voiceoverCard.attributes('href')).toBe('/login?redirect=/voiceover/new')
    expect(voiceoverCard.text()).toContain('Log in to use')
    expect(fashionCard.attributes('href')).toBe('/login?redirect=/fashion-try-on/new')
    expect(fashionCard.text()).toContain('Log in to use')
    expect(motionCard.attributes('href')).toBe('/login?redirect=/motion-video/new')
    expect(motionCard.text()).toContain('Log in to use')
  })

  it('keeps coming-soon and maintenance tools non-clickable and hides disabled tools', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const comingSoon = wrapper.get('[data-testid="tool-card-future_tool"]')
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
    expect(wrapper.get('[role=alert] button').text()).toBe('Try again')
  })
})
