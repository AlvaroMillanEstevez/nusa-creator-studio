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
    implementation_stage: 'real_provider',
    status: 'available',
    route: '/voiceover/new',
    credit_cost: 1,
    credit_cost_label: '1 credit',
    badge_label: 'Core beta',
    is_recommended: false,
    sort_order: 20,
  },
  {
    key: 'video_to_campaign',
    title: 'Video-to-Campaign',
    short_description: 'Analyze raw product footage and turn it into a campaign plan.',
    long_description: 'Turn product footage into campaign hooks, narration, captions and an edit plan.',
    category: 'assisted_media',
    service_tier: 'advanced',
    output_type: 'assisted_media',
    implementation_stage: 'beta',
    status: 'available',
    route: '/dashboard?tool=video_to_campaign',
    credit_cost: 2,
    credit_cost_label: '2 credits',
    badge_label: 'Core beta',
    is_recommended: false,
    sort_order: 25,
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
    implementation_stage: 'future',
    status: 'coming_soon',
    route: '/fashion-try-on/new',
    credit_cost: 2,
    credit_cost_label: '2 credits preview',
    badge_label: 'Preview workflow',
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
    implementation_stage: 'future',
    status: 'coming_soon',
    route: '/motion-video/new',
    credit_cost: 3,
    credit_cost_label: '3 credits preview',
    badge_label: 'Preview workflow',
    is_recommended: false,
    sort_order: 38,
  },
  {
    key: 'auto_video_editor',
    title: 'Auto Video Editor',
    short_description: 'Create an edit-ready vertical video plan.',
    long_description: 'Turn product footage, campaign context and optional voiceover into an edit plan.',
    category: 'ai_video',
    service_tier: 'premium',
    output_type: 'video',
    implementation_stage: 'beta',
    status: 'available',
    route: '/dashboard?tool=auto_video_editor',
    credit_cost: 2,
    credit_cost_label: '2 credits',
    badge_label: 'New mock',
    is_recommended: false,
    sort_order: 37,
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
      { path: '/media', component: placeholder },
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

  it('loads tools from the API and presents category sections instead of service tiers', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const card = wrapper.get('[data-testid="tool-card-affiliate_campaign"]')

    expect(wrapper.text()).toContain('What do you want to create today?')
    expect(wrapper.text()).toContain('Campaign & Content')
    expect(wrapper.text()).toContain('Voice')
    expect(wrapper.text()).toContain('Video')
    expect(wrapper.text()).toContain('Assets')
    expect(wrapper.text()).toContain('Coming Soon / Roadmap')
    expect(wrapper.find('[data-testid="tool-tier-section-basic"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Service tier')
    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/dashboard?tool=affiliate_campaign')
    expect(card.text()).toContain('Product Content Kit')
    expect(card.text()).not.toContain('Affiliate Campaign Generator')
    expect(card.text()).toContain('Script pack')
    expect(card.text()).not.toContain('Mock')
    expect(card.text()).toContain('Open tool')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('groups active beta tools, media library and roadmap cards into product categories', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const campaignSection = wrapper.get('[data-testid="tool-category-section-campaign_content"]')
    const voiceSection = wrapper.get('[data-testid="tool-category-section-voice"]')
    const videoSection = wrapper.get('[data-testid="tool-category-section-video"]')
    const assetsSection = wrapper.get('[data-testid="tool-category-section-assets"]')
    const roadmapSection = wrapper.get('[data-testid="tool-category-section-roadmap"]')

    expect(campaignSection.text()).toContain('Product Content Kit')
    expect(campaignSection.text()).toContain('Video-to-Campaign Insight')
    expect(campaignSection.text()).toContain('Hook Generator')
    expect(campaignSection.text()).toContain('Caption & Hashtag Pack')
    expect(campaignSection.text()).toContain('Script Variations')
    expect(voiceSection.text()).toContain('AI Voiceover')
    expect(videoSection.text()).toContain('Auto Video Editor')
    expect(assetsSection.text()).toContain('Media Library')
    expect(roadmapSection.text()).toContain('Fashion Studio')
    expect(roadmapSection.text()).toContain('Product Photo Studio')
    expect(roadmapSection.text()).toContain('AI Avatar Presenter')
    expect(roadmapSection.text()).toContain('Motion AI Video')
    expect(wrapper.find('[data-testid="tool-card-video_enhancer"]').exists()).toBe(false)
  })

  it('links active tools and media library for authenticated users', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)

    expect(wrapper.get('[data-testid="tool-card-video_to_campaign"]').attributes('href')).toBe('/dashboard?tool=video_to_campaign')
    expect(wrapper.get('[data-testid="tool-card-video_to_campaign"]').text()).toContain('Video-to-Campaign Insight')
    expect(wrapper.get('[data-testid="tool-card-auto_video_editor"]').attributes('href')).toBe('/dashboard?tool=auto_video_editor')
    expect(wrapper.get('[data-testid="tool-card-ai_voiceover"]').attributes('href')).toBe('/dashboard?tool=ai_voiceover')
    expect(wrapper.get('[data-testid="tool-card-media_library"]').attributes('href')).toBe('/media')
  })

  it('sends guests through login with the intended destination', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock, null)

    expect(wrapper.get('[data-testid="tool-card-affiliate_campaign"]').attributes('href')).toBe('/login?redirect=/dashboard?tool=affiliate_campaign')
    expect(wrapper.get('[data-testid="tool-card-affiliate_campaign"]').text()).toContain('Log in to use')
    expect(wrapper.get('[data-testid="tool-card-media_library"]').attributes('href')).toBe('/login?redirect=/media')
  })

  it('keeps roadmap tools non-clickable and hides disabled tools', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const hookGenerator = wrapper.get('[data-testid="tool-card-hook_generator"]')
    const fashion = wrapper.get('[data-testid="tool-card-fashion_try_on"]')
    const motion = wrapper.get('[data-testid="tool-card-motion_ai_video"]')

    expect(hookGenerator.element.tagName).toBe('ARTICLE')
    expect(hookGenerator.attributes('aria-disabled')).toBe('true')
    expect(fashion.element.tagName).toBe('ARTICLE')
    expect(fashion.find('a').exists()).toBe(false)
    expect(motion.element.tagName).toBe('ARTICLE')
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