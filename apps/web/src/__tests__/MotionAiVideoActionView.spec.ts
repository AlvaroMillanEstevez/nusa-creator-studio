
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
    provider_capabilities: {
      provider_mode: 'google_veo',
      real_enabled: true,
      request_mode: 'text_only',
      product_image_required: false,
      product_image_supported: 'context_only',
      campaign_supported: true,
      text_prompt_supported: true,
      image_context_supported: true,
      image_to_video_supported: true,
      image_to_video_enabled: false,
      reference_images_supported: true,
      reference_images_enabled: false,
      max_reference_images: 3,
      image_reference_experimental: false,
      provider_label: 'Google Veo',
      default_duration_seconds: 8,
      default_aspect_ratio: '9:16',
      supports_real_rendering: true,
      source_modes: ['text_prompt', 'campaign_assisted', 'image_context', 'image_first_frame', 'image_reference_experimental'],
    },
    ...overrides,
  }
}

function imageEnabledActions(): ProjectActionsResponse {
  return {
    data: [
      action({
        provider_capabilities: {
          ...action().provider_capabilities!,
          product_image_supported: 'first_frame',
          image_to_video_enabled: true,
        },
      }),
    ],
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
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
    ],
  })
}

function createFetchMock(
  mediaAssets: MediaAsset[] = [productImage],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, campaignGeneration],
  actionResponse: ProjectActionsResponse = actions,
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
      return Response.json(actionResponse)
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
  mediaAssets: MediaAsset[] = [productImage],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, campaignGeneration],
  actionResponse: ProjectActionsResponse = actions,
) {
  const router = routerFor()
  const fetchMock = createFetchMock(mediaAssets, generationHistory, actionResponse)

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

  if (!call) {
    throw new Error('Expected Motion AI Video submission')
  }

  return JSON.parse(String(call[1]?.body)) as Record<string, unknown>
}

function hasSubmission(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>): boolean {
  return fetchMock.mock.calls.some(([input]) => String(input).endsWith('/projects/42/generate-motion-ai-video'))
}

describe('MotionAiVideoActionView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the progressive creator-first Motion AI Video form', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Motion AI Video')
    expect(wrapper.text()).toContain('Back to workspace')
    expect(wrapper.text()).toContain('How should the video start?')
    expect(wrapper.text()).toContain('Describe a video')
    expect(wrapper.text()).toContain('Use a saved campaign')
    expect(wrapper.text()).toContain('Describe the video you want')
    expect(wrapper.text()).toContain('Basic video settings')
    expect(wrapper.text()).toContain('Post-production')
    expect(wrapper.text()).toContain('Generate video')
    expect(wrapper.find('[data-testid="motion-source-mode-image_context"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="motion-source-mode-image_reference_experimental"]').exists()).toBe(false)
  })

  it('shows campaign selector directly below the campaign source and requires a selection', async () => {
    const { wrapper, fetchMock } = await mountView([], [])

    await wrapper.get('[data-testid="motion-source-mode-campaign_assisted"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="motion-campaign-inline-panel"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="motion-submit-helper"]').text()).toContain('Choose a saved campaign first')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Choose a saved campaign first')
    expect(hasSubmission(fetchMock)).toBe(false)
  })

  it('submits saved campaign mode without a product image', async () => {
    const { wrapper, fetchMock, router } = await mountView()

    await wrapper.get('[data-testid="motion-source-mode-campaign_assisted"]').trigger('click')
    await flushPromises()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(submittedPayload(fetchMock)).toMatchObject({
      source_mode: 'campaign_assisted',
      product_image_asset_id: null,
      campaign_generation_id: 31,
      script_source: 'campaign_script',
      scene_description: 'Short motion concept',
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/203')
  })

  it('submits text description mode with the main description mapped to scene_description', async () => {
    const { wrapper, fetchMock, router } = await mountView([], [])

    await wrapper.get('[data-testid="motion-main-description"]').setValue('Create a clean prompt-only showcase with a slow reveal.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(submittedPayload(fetchMock)).toMatchObject({
      source_mode: 'text_prompt',
      product_image_asset_id: null,
      campaign_generation_id: null,
      script_source: 'project_brief',
      manual_script: null,
      scene_description: 'Create a clean prompt-only showcase with a slow reveal.',
      target_duration_seconds: 8,
      aspect_ratio: 'vertical_9_16',
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/203')
  })

  it('hides image-first-frame mode until the capability is enabled', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-testid="motion-source-mode-image_first_frame"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Product image animation appears here only when the verified first-frame provider capability is enabled')
  })

  it('renders image selector inline when first-frame capability is enabled', async () => {
    const { wrapper } = await mountView([productImage], [], imageEnabledActions())

    await wrapper.get('[data-testid="motion-source-mode-image_first_frame"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="motion-image-inline-panel"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('The selected image becomes the starting frame')
    expect(wrapper.find('[data-testid="motion-source-mode-image_reference_experimental"]').exists()).toBe(false)
  })

  it('requires exactly one product image for image-first-frame mode', async () => {
    const { wrapper, fetchMock } = await mountView([], [], imageEnabledActions())

    await wrapper.get('[data-testid="motion-source-mode-image_first_frame"]').trigger('click')
    await flushPromises()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Choose one product image first')
    expect(hasSubmission(fetchMock)).toBe(false)
  })

  it('uploads and submits an image-first-frame request with canonical source mode', async () => {
    const { wrapper, fetchMock, router } = await mountView([], [], imageEnabledActions())

    await wrapper.get('[data-testid="motion-source-mode-image_first_frame"]').trigger('click')
    await flushPromises()

    const input = wrapper.find<HTMLInputElement>('[data-testid="project-asset-file"]')

    Object.defineProperty(input.element, 'files', {
      value: [new File(['image'], 'uploaded-product.jpg', { type: 'image/jpeg' })],
      configurable: true,
    })

    await input.trigger('change')
    await wrapper.find('[data-testid="project-asset-upload"]').trigger('click')
    await flushPromises()
    await wrapper.get('[data-testid="motion-main-description"]').setValue('Animate the product from the first frame.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find(([input, init]) => String(input).endsWith('/media-assets') && init?.method === 'POST')
    const formData = uploadCall?.[1]?.body as FormData

    expect(formData.get('project_id')).toBe('42')
    expect(formData.get('usage_role')).toBe('product_image')
    expect(submittedPayload(fetchMock)).toMatchObject({
      source_mode: 'image_first_frame',
      product_image_asset_id: 900,
      campaign_generation_id: null,
      scene_description: 'Animate the product from the first frame.',
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/203')
  })

  it('keeps reference images and image context out of the primary consumer flow even with stale capabilities', async () => {
    const staleActions: ProjectActionsResponse = {
      data: [
        action({
          provider_capabilities: {
            ...action().provider_capabilities!,
            request_mode: 'reference_images_experimental',
            reference_images_enabled: true,
            image_reference_experimental: true,
          },
        }),
      ],
    }
    const { wrapper } = await mountView([productImage], [], staleActions)

    expect(wrapper.find('[data-testid="motion-source-mode-image_context"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="motion-source-mode-image_reference_experimental"]').exists()).toBe(false)
  })

  it('keeps advanced settings collapsed and uses non-technical labels', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.get('[data-testid="motion-advanced-toggle"]').attributes('aria-expanded')).toBe('false')
    await wrapper.get('[data-testid="motion-advanced-toggle"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="motion-advanced-toggle"]').attributes('aria-expanded')).toBe('true')
    expect(wrapper.text()).toContain('Things to avoid')
    expect(wrapper.text()).toContain('Additional notes')
    expect(wrapper.text()).not.toContain('Negative prompt')
  })

  it('uses honest post-production wording for captions and voiceover', async () => {
    const { wrapper } = await mountView([], [])

    expect(wrapper.text()).toContain('Captions are not burned into the Veo MP4')
    expect(wrapper.text()).toContain('The audio is added during post-production')
    expect(wrapper.get('[data-testid="motion-voiceover-plan-toggle"]').attributes('disabled')).toBeDefined()
  })
})
