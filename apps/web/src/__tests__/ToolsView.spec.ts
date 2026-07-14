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

  it('renders exactly the four closed-beta workflow cards', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const workflowCards = wrapper.findAll('[data-workflow-card="main"]')

    expect(wrapper.text()).toContain('Create from one product workspace')
    expect(workflowCards).toHaveLength(4)
    expect(wrapper.get('[data-testid="workflow-card-product_content_kit"]').text()).toContain('Product Content Kit')
    expect(wrapper.get('[data-testid="workflow-card-auto_video_editor"]').text()).toContain('Auto Video Editor')
    expect(wrapper.get('[data-testid="workflow-card-auto_video_ads"]').text()).toContain('Auto Video Ads')
    expect(wrapper.get('[data-testid="workflow-card-motion_ai_video"]').text()).toContain('Motion AI Video')
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/tools',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('does not render supporting or legacy tools as main workflow cards', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const mainSection = wrapper.get('[data-testid="beta-workflow-section"]')

    expect(wrapper.find('[data-testid="workflow-card-video_to_campaign"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="workflow-card-media_library"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="workflow-card-hook_generator"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="workflow-card-caption_hashtag_pack"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="workflow-card-script_variations"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tool-card-video_to_campaign"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tool-card-media_library"]').exists()).toBe(false)
    expect(mainSection.text()).not.toContain('Video-to-Campaign Insight')
    expect(mainSection.text()).not.toContain('Hook Generator')
    expect(mainSection.text()).not.toContain('Caption & Hashtag Pack')
    expect(mainSection.text()).not.toContain('Script Variations')
    expect(mainSection.text()).not.toContain('Media Library')
    expect(wrapper.text()).not.toContain('Basic')
    expect(wrapper.text()).not.toContain('Advanced')
    expect(wrapper.text()).not.toContain('Premium')
  })

  it('frames Product Content Kit and Auto Video Editor with the right inputs and outputs', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const productKit = wrapper.get('[data-testid="workflow-card-product_content_kit"]')
    const autoEditor = wrapper.get('[data-testid="workflow-card-auto_video_editor"]')

    expect(productKit.text()).toContain('From brief, product image or product video')
    expect(productKit.text()).toContain('Brief, Product image, Product video')
    expect(productKit.text()).toContain('Hooks')
    expect(autoEditor.text()).toContain('raw product footage')
    expect(autoEditor.text()).toContain('organic creator video')
    expect(autoEditor.text()).toContain('Video analysis')
  })

  it('links active workflows for authenticated users and keeps beta-next workflows disabled', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const productKit = wrapper.get('[data-testid="workflow-card-product_content_kit"]')
    const autoEditor = wrapper.get('[data-testid="workflow-card-auto_video_editor"]')
    const ads = wrapper.get('[data-testid="workflow-card-auto_video_ads"]')
    const motion = wrapper.get('[data-testid="workflow-card-motion_ai_video"]')

    expect(productKit.element.tagName).toBe('A')
    expect(productKit.attributes('href')).toBe('/dashboard?tool=product_content_kit')
    expect(autoEditor.element.tagName).toBe('A')
    expect(autoEditor.attributes('href')).toBe('/dashboard?tool=auto_video_editor')
    expect(ads.element.tagName).toBe('ARTICLE')
    expect(ads.text()).toContain('Planned for closed beta')
    expect(ads.find('a').exists()).toBe(false)
    expect(motion.element.tagName).toBe('ARTICLE')
    expect(motion.text()).toContain('Planned for closed beta')
    expect(motion.find('a').exists()).toBe(false)
  })

  it('sends guests through login with the intended workflow destination', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock, null)
    const productKit = wrapper.get('[data-testid="workflow-card-product_content_kit"]')

    expect(productKit.attributes('href')).toBe('/login?redirect=/dashboard?tool=product_content_kit')
    expect(productKit.text()).toContain('Log in to start')
  })

  it('keeps the later roadmap small and separate from main workflows', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: tools }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    const wrapper = await mountToolsView(fetchMock)
    const later = wrapper.get('[data-testid="later-workflow-section"]')

    expect(later.text()).toContain('Fashion Studio')
    expect(later.text()).toContain('Product Photo Studio')
    expect(later.text()).toContain('Brand Kit')
    expect(later.text()).toContain('Content Calendar')
    expect(later.text()).not.toContain('Product Content Kit')
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
