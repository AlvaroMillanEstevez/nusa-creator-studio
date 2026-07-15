import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { MediaAsset } from '@/types/media'
import type { CreatorGeneration, CreatorProject, ProjectAction, ProjectActionsResponse } from '@/types/project'
import MotionAiVideoActionView from '@/views/MotionAiVideoActionView.vue'

const project: CreatorProject = {
  id: 42,
  user_id: 1,
  title: 'Hydration bottle workspace',
  type: 'workspace',
  status: 'draft',
  input_payload: {
    product_name: 'Hydration bottle',
    content_idea: 'Short motion concept',
    product_description: 'A simple bottle for creator desk routines.',
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
  original_name: 'hydration-bottle.jpg',
  mime_type: 'image/jpeg',
  size_bytes: 2048,
  size_label: '2.0 KB',
  metadata: null,
  created_at: '2026-07-08T01:00:00.000000Z',
  updated_at: '2026-07-08T01:00:00.000000Z',
}

const scenarioImage: MediaAsset = {
  ...productImage,
  id: 91,
  usage_role: 'background_reference',
  storage_identifier: 'media-91',
  original_name: 'desk-scenario.jpg',
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
    campaign_title: 'Hydration routine',
    hooks: ['Make hydration visible on your desk.'],
    script: { title: 'Hydration bottle', duration_seconds: 30, scenes: [] },
    caption: 'Desk hydration idea.',
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
  result_payload: {
    voiceover_title: 'Hydration voiceover',
    narration_script: 'Keep hydration visible in your daily setup.',
    estimated_duration_seconds: 8,
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

function action(overrides: Partial<ProjectAction> = {}): ProjectAction {
  return {
    key: 'start_motion_video',
    title: 'Motion AI Video',
    description: 'Create motion-video concepts from product images.',
    service_tier: 'premium',
    output_type: 'video',
    status: 'configure',
    credit_cost: 3,
    user_has_access: true,
    upgrade_required: false,
    access_label: 'Included in your plan',
    locked_reason: null,
    action_mode: 'configure',
    primary_cta: { label: 'Configure Motion AI Video', route: null, action: 'configure_motion_ai_video' },
    requirements: [],
    recommended_sources: [],
    recommended_assets: [],
    missing_requirements_count: 0,
    required_source: 'media_asset',
    source_generation_id: null,
    reason: 'Ready to configure Motion AI Video.',
    action_endpoint: '/api/v1/projects/42/generate-motion-ai-video',
    tool_key: 'motion_ai_video',
    tool_status: 'available',
    ...overrides,
  }
}

const actions: ProjectActionsResponse = { data: [action()] }

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
      {
        path: '/projects/:projectId/actions/motion-ai-video',
        name: 'project-motion-ai-video-action',
        component: MotionAiVideoActionView,
      },
      { path: '/media', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
    ],
  })
}

function createFetchMock(
  mediaAssets: MediaAsset[] = [productImage, scenarioImage],
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

    if (url.endsWith('/media-assets') && method === 'POST') {
      return Response.json({ data: { ...productImage, id: 900, original_name: 'uploaded-product.jpg' } }, { status: 201 })
    }

    if (url.endsWith('/projects/42/generate-motion-ai-video') && method === 'POST') {
      return Response.json(
        {
          data: {
            ...campaignGeneration,
            id: 203,
            job_type: 'generate_motion_ai_video',
            provider: 'mock',
            status: 'queued',
            credits_charged: 3,
            result_payload: null,
          },
          meta: { credits_balance: 17 },
        },
        { status: 201 },
      )
    }

    return Response.json({}, { status: 404 })
  })
}

async function mountView(
  mediaAssets: MediaAsset[] = [productImage, scenarioImage],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, campaignGeneration],
) {
  const router = routerFor()
  const fetchMock = createFetchMock(mediaAssets, generationHistory)

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push('/projects/42/actions/motion-ai-video')
  await router.isReady()

  const wrapper = mount(MotionAiVideoActionView, {
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return { wrapper, fetchMock, router }
}

function submittedPayload(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-motion-ai-video'))

  return JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>
}

describe('MotionAiVideoActionView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the Motion AI Video action view', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Motion AI Video')
    expect(wrapper.text()).toContain('← Back to workspace')
    expect(wrapper.text()).toContain('Product image')
    expect(wrapper.text()).toContain('Generate Motion Video')
    expect(wrapper.text()).toContain('Costs 3 credits')
  })

  it('requires a product image before submitting', async () => {
    const { wrapper, fetchMock } = await mountView([], [])

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Upload or select a product image')
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith('/projects/42/generate-motion-ai-video'))).toBe(false)
  })

  it('shows manual script textarea when manual script source is selected', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[data-testid="motion-script-source-select"]').setValue('manual_script')

    expect(wrapper.find('[data-testid="motion-manual-script"]').exists()).toBe(true)
  })

  it('uploads and selects a product image inside the Motion AI form', async () => {
    const { wrapper, fetchMock } = await mountView([], [])
    const input = wrapper.findAll<HTMLInputElement>('[data-testid="project-asset-file"]')[0]

    if (!input) {
      throw new Error('Expected product image file input')
    }

    Object.defineProperty(input.element, 'files', {
      value: [new File(['image'], 'uploaded-product.jpg', { type: 'image/jpeg' })],
      configurable: true,
    })

    await input.trigger('change')
    await wrapper.findAll('[data-testid="project-asset-upload"]')[0]?.trigger('click')
    await flushPromises()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find(([input, init]) => String(input).endsWith('/media-assets') && init?.method === 'POST')
    const formData = uploadCall?.[1]?.body as FormData

    expect(formData.get('project_id')).toBe('42')
    expect(formData.get('usage_role')).toBe('product_image')
    expect(submittedPayload(fetchMock)).toMatchObject({ product_image_asset_id: 900 })
  })

  it('submits expected payload with product, scenario, campaign and voiceover selections', async () => {
    const { wrapper, fetchMock, router } = await mountView()

    await wrapper.findAll('[data-testid="project-asset-option-91"]')[1]?.trigger('click')
    await wrapper.get('[data-testid="motion-campaign-select"]').setValue('31')
    await wrapper.get('[data-testid="motion-voiceover-select"]').setValue('44')
    await wrapper.get('[data-testid="motion-platform-select"]').setValue('instagram_reels')
    await wrapper.get('[data-testid="motion-duration-select"]').setValue('10')
    await wrapper.get('[data-testid="motion-visual-style-select"]').setValue('premium_brand')
    await wrapper.get('[data-testid="motion-style-select"]').setValue('product_orbit')
    await wrapper.get('[data-testid="motion-primary-cta-input"]').setValue('Shop the bottle')
    await wrapper.get('[data-testid="motion-creative-notes"]').setValue('Make the clip premium and realistic.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(submittedPayload(fetchMock)).toMatchObject({
      product_image_asset_id: 90,
      scenario_image_asset_id: 91,
      campaign_generation_id: 31,
      voiceover_generation_id: 44,
      platform: 'instagram_reels',
      target_duration_seconds: 10,
      script_source: 'campaign_script',
      creative_notes: 'Make the clip premium and realistic.',
      visual_style: 'premium_brand',
      motion_style: 'product_orbit',
      aspect_ratio: 'vertical_9_16',
      include_voiceover_plan: true,
      include_caption_plan: true,
      primary_cta: 'Shop the bottle',
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/203')
  })
})
