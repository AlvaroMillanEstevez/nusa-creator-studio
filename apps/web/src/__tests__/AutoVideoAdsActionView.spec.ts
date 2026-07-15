import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { MediaAsset } from '@/types/media'
import type { CreatorGeneration, CreatorProject, ProjectAction, ProjectActionsResponse } from '@/types/project'
import AutoVideoAdsActionView from '@/views/AutoVideoAdsActionView.vue'

const project: CreatorProject = {
  id: 42,
  user_id: 1,
  title: 'Creator desk light',
  type: 'workspace',
  status: 'draft',
  input_payload: {
    product_name: 'Creator desk light',
    content_idea: 'Paid ad for creator desks',
    product_description: 'A compact creator desk light.',
    target_audience: 'TikTok affiliate creators',
    language: 'english',
    tone: 'natural',
    platform: 'tiktok',
    cta_preference: 'See product details',
    notes: null,
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-08T01:00:00.000000Z',
  updated_at: '2026-07-08T01:00:00.000000Z',
}

const productImage: MediaAsset = {
  id: 90,
  user_id: 1,
  project_id: 42,
  type: 'image',
  usage_role: 'product_image',
  disk: 'local',
  storage_identifier: 'media-90',
  original_name: 'creator-desk-light.jpg',
  mime_type: 'image/jpeg',
  size_bytes: 2048,
  size_label: '2.0 KB',
  metadata: null,
  created_at: '2026-07-08T01:00:00.000000Z',
  updated_at: '2026-07-08T01:00:00.000000Z',
}

const sourceVideo: MediaAsset = {
  ...productImage,
  id: 88,
  type: 'video',
  usage_role: 'campaign_source_video',
  storage_identifier: 'media-88',
  original_name: 'creator-desk-light-demo.mp4',
  mime_type: 'video/mp4',
}

const campaignGeneration: CreatorGeneration = {
  id: 31,
  user_id: 1,
  project_id: 42,
  job_type: 'generate_affiliate_campaign',
  provider: 'mock',
  status: 'completed',
  cost_usd: '0.0000',
  credits_charged: 1,
  input_payload: null,
  output_payload: null,
  error_message: null,
  request_payload: null,
  result_payload: {
    campaign_title: 'Creator desk ad angle',
    hooks: ['Make your desk videos easier to light.'],
    script: { title: 'Creator desk light', duration_seconds: 30, scenes: [] },
    caption: 'Creator desk lighting idea.',
    hashtags: ['#CreatorTools'],
    cta: 'See product details',
    notes: ['Keep claims factual.'],
  },
  started_at: '2026-07-08T02:00:00.000000Z',
  finished_at: '2026-07-08T02:00:00.000000Z',
  created_at: '2026-07-08T02:00:00.000000Z',
}

const voiceoverGeneration: CreatorGeneration = {
  ...campaignGeneration,
  id: 44,
  job_type: 'generate_ai_voiceover',
  credits_charged: 1,
  result_payload: {
    voiceover_title: 'Creator desk voiceover',
    narration_script: 'Show the light honestly, then invite viewers to check the product details.',
    estimated_duration_seconds: 18,
    scene_timing: [],
    voice_direction: 'Natural creator delivery.',
    pronunciation_notes: [],
    pacing_notes: [],
    platform_notes: [],
    suggested_audio_format: 'mp3',
    mock_audio: { status: 'placeholder', provider: 'mock', message: 'Mock audio.' },
    usage_notes: [],
    compliance_notes: [],
  },
}

function action(overrides: Partial<ProjectAction> & Pick<ProjectAction, 'key'>): ProjectAction {
  return {
    title: 'Auto Video Ads',
    description: 'Create a paid ad creative and render when beta provider is enabled.',
    service_tier: 'premium',
    output_type: 'ad_plan',
    status: 'configure',
    credit_cost: 12,
    user_has_access: true,
    upgrade_required: false,
    access_label: 'Included in your plan',
    locked_reason: null,
    action_mode: 'configure',
    primary_cta: { label: 'Generate Auto Video Ad', route: null, action: 'configure_auto_video_ad' },
    requirements: [],
    recommended_sources: [],
    recommended_assets: [],
    missing_requirements_count: 0,
    required_source: 'media_asset',
    source_generation_id: null,
    reason: 'Ready to create an auto video ad from project visuals.',
    action_endpoint: '/api/v1/projects/42/generate-auto-video-ad',
    tool_key: 'auto_video_ads',
    tool_status: 'available',
    ...overrides,
  }
}

const actions: ProjectActionsResponse = {
  data: [action({ key: 'create_video_ad' })],
}

function routerFor() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/projects/:projectId', component: { template: '<div />' } },
      {
        path: '/projects/:projectId/generations/:generationId',
        name: 'generation-result',
        component: { template: '<div />' },
      },
      { path: '/projects/:projectId/actions/auto-video-ads', component: AutoVideoAdsActionView },
      { path: '/media', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
    ],
  })
}

function createFetchMock(
  mediaAssets: MediaAsset[] = [productImage, sourceVideo],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, campaignGeneration],
) {
  return vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42') && method === 'GET') {
      return Response.json({ data: project })
    }

    if (url.endsWith('/projects/42/media-assets')) {
      return Response.json({ data: mediaAssets })
    }

    if (url.endsWith('/projects/42/generations')) {
      return Response.json({ data: generationHistory })
    }

    if (url.endsWith('/projects/42/actions')) {
      return Response.json(actions)
    }

    if (url.endsWith('/projects/42/generate-auto-video-ad') && method === 'POST') {
      return Response.json(
        {
          data: {
            ...campaignGeneration,
            id: 203,
            job_type: 'generate_auto_video_ad',
            provider: 'mock',
            status: 'queued',
            credits_charged: 12,
            result_payload: null,
          },
        },
        { status: 201 },
      )
    }

    return Response.json({}, { status: 404 })
  })
}

async function mountView(
  mediaAssets: MediaAsset[] = [productImage, sourceVideo],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, campaignGeneration],
) {
  const router = routerFor()
  const fetchMock = createFetchMock(mediaAssets, generationHistory)

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push('/projects/42/actions/auto-video-ads')
  await router.isReady()

  const wrapper = mount(AutoVideoAdsActionView, {
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return { wrapper, fetchMock, router }
}

function submittedPayload(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-auto-video-ad'))

  return JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>
}

describe('AutoVideoAdsActionView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the Auto Video Ads action setup', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Auto Video Ads')
    expect(wrapper.text()).toContain('← Back to workspace')
    expect(wrapper.text()).toContain('Choose visual assets')
    expect(wrapper.text()).toContain('Generate Auto Video Ad')
    expect(wrapper.get('[data-testid="auto-video-ad-visual-type"]').text()).toContain('Hybrid ad')
  })

  it('requires at least one image or video asset before submitting', async () => {
    const { wrapper, fetchMock } = await mountView([], [])

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Select a product image, a source video, or both')
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith('/projects/42/generate-auto-video-ad'))).toBe(false)
  })

  it('submits an image-based ad plan payload', async () => {
    const { wrapper, fetchMock, router } = await mountView([productImage], [])

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    const body = submittedPayload(fetchMock)

    expect(body.product_image_asset_id).toBe(90)
    expect(body.source_video_asset_id).toBeNull()
    expect(body.campaign_generation_id).toBeNull()
    expect(body.voiceover_generation_id).toBeNull()
    expect(body.ad_format).toBe('vertical_9_16')
    expect(body.platform).toBe('tiktok_ads')
    expect(body.ad_goal).toBe('sales')
    expect(body.target_duration_seconds).toBe(15)
    expect(body.tone).toBe('direct_response')
    expect(body.visual_style).toBe('clean_product')
    expect(body.hook_style).toBe('benefit_first')
    expect(body.include_voiceover_script).toBe(true)
    expect(body.include_caption_variants).toBe(true)
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/203')
  })

  it('submits a video-based ad plan payload', async () => {
    const { wrapper, fetchMock } = await mountView([sourceVideo], [])

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const body = submittedPayload(fetchMock)

    expect(body.product_image_asset_id).toBeNull()
    expect(body.source_video_asset_id).toBe(88)
  })

  it('submits a hybrid ad plan with optional campaign and voiceover sources', async () => {
    const { wrapper, fetchMock } = await mountView()

    await wrapper.get('[data-testid="auto-video-ad-platform-select"]').setValue('instagram_ads')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const body = submittedPayload(fetchMock)

    expect(body.product_image_asset_id).toBe(90)
    expect(body.source_video_asset_id).toBe(88)
    expect(body.campaign_generation_id).toBe(31)
    expect(body.voiceover_generation_id).toBe(44)
    expect(body.platform).toBe('instagram_ads')
  })

  it('allows campaign and voiceover sources to stay optional', async () => {
    const { wrapper, fetchMock } = await mountView([productImage, sourceVideo], [])

    expect(wrapper.get('[data-testid="auto-video-ad-campaign-select"]').text()).toContain('No campaign source')
    expect(wrapper.get('[data-testid="auto-video-ad-voiceover-select"]').text()).toContain('No voiceover')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const body = submittedPayload(fetchMock)

    expect(body.campaign_generation_id).toBeNull()
    expect(body.voiceover_generation_id).toBeNull()
  })
})
