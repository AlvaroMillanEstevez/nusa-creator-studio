import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectActionHub from '@/components/ProjectActionHub.vue'
import { API_BASE_URL } from '@/services/api'
import type {
  CampaignGenerationResponse,
  CreatorGeneration,
  ProjectAction,
  ProjectActionsResponse,
  GenerationResult,
} from '@/types/project'

function action(overrides: Partial<ProjectAction> & Pick<ProjectAction, 'key'>): ProjectAction {
  const { key, ...rest } = overrides

  return {
    key,
    title: 'Action title',
    description: 'Action description.',
    service_tier: 'basic',
    output_type: 'script_pack',
    status: 'available',
    credit_cost: 1,
    user_has_access: true,
    upgrade_required: false,
    access_label: 'Included in every plan',
    locked_reason: null,
    action_mode: 'direct',
    primary_cta: { label: 'Open action', route: null, action: 'open_action' },
    requirements: [],
    recommended_sources: [],
    recommended_assets: [],
    missing_requirements_count: 0,
    required_source: null,
    source_generation_id: null,
    reason: null,
    action_endpoint: null,
    tool_key: null,
    tool_status: 'available',
    ...rest,
  }
}

function generation(overrides: Partial<CreatorGeneration> & Pick<CreatorGeneration, 'id' | 'job_type'>): CreatorGeneration {
  const { id, job_type, ...rest } = overrides

  return {
    id,
    user_id: 1,
    project_id: 42,
    job_type,
    provider: 'mock',
    status: 'completed',
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: null,
    output_payload: null,
    error_message: null,
    request_payload: null,
    result_payload: null,
    started_at: '2026-07-08T01:00:00.000000Z',
    finished_at: '2026-07-08T01:00:00.000000Z',
    created_at: '2026-07-08T01:00:00.000000Z',
    ...rest,
  }
}

const campaignGeneration = generation({
  id: 9,
  job_type: 'generate_affiliate_campaign',
  result_payload: {
    hooks: ['Hook'],
    voiceover_ready_script: 'Voiceover-ready script from the selected campaign.',
    script: { title: 'Campaign script', duration_seconds: 30, scenes: [] },
    caption: 'Caption',
    hashtags: ['#Creator'],
    cta: 'Review details.',
    notes: ['Use real footage.'],
  } as unknown as GenerationResult,
})

const videoAnalysisGeneration = generation({
  id: 31,
  job_type: 'generate_video_to_campaign',
  result_payload: {
    campaign_input_mode: 'video_analysis',
    auto_video_editor_eligible: true,
    source_video_asset_id: 88,
    video_summary: 'Detected useful product footage.',
    campaign_angle: 'Show the product in use.',
    narration_voice_script: 'Short analysis narration.',
    hooks: ['See this feature'],
    captions: ['Useful caption'],
    hashtags: ['#nusa'],
    cta_options: ['See details'],
    observed_product_features: [],
    detected_product_moments: [],
    scene_observations: [],
    recommended_cuts: [{ start_seconds: 2, end_seconds: 5, reason: 'Stable product shot.' }],
    edit_plan: {
      title: 'AI footage edit plan',
      target_duration_seconds: 20,
      opening: 'Open on product reveal.',
      middle: 'Show use.',
      closing: 'End with CTA.',
      timeline: [{ start_seconds: 0, end_seconds: 3, instruction: 'Use stable product shot.' }],
    },
    compliance_notes: [],
    usage_notes: [],
    provider_metadata: { campaign_input_mode: 'video_analysis', source_video_asset_id: 88 },
  } as unknown as GenerationResult,
})

const motionGeneration = generation({
  id: 44,
  job_type: 'generate_motion_ai_video',
  result_payload: {
    final_video_asset_id: 104,
    motion_summary: {
      creative_angle: 'Premium product reveal',
      source_mode: 'text_prompt',
      source_type: 'text_prompt',
      duration: 8,
      aspect_ratio: 'vertical_9_16',
      visual_style: 'premium_brand',
      motion_style: 'slow_push_in',
    },
    generation_prompt: { prompt: 'Prompt', negative_prompt: null },
    scene_plan: [],
    script_plan: { voiceover_ready_script: '', caption_lines: [], CTA_line: 'See details' },
    motion_settings: {},
    asset_plan: { product_image_asset_id: null, scenario_image_asset_id: null, voiceover_generation_id: null },
    provider_readiness: { provider: 'google_veo', status: 'ready', notes: [] },
    compliance_notes: [],
    metadata: { mode: 'real', final_video_asset_id: 104 },
    provider_metadata: { provider: 'google_veo', final_video_asset_id: 104 },
  } as unknown as GenerationResult,
})

const autoEditorGeneration = generation({
  id: 55,
  job_type: 'generate_auto_video_edit',
  result_payload: {
    final_video_placeholder: { provider: 'shotstack', status: 'done', message: 'Rendered.' },
    edit_decision_list: [],
    timeline: [],
    clip_sequence: [],
    captions_plan: { caption_count: 0, sample_captions: [] },
    audio_mix_plan: { mix_notes: [], source_audio_mode: 'low_source', voiceover_mix: 'voiceover_dominant' },
    source_summary: 'Edited result.',
    selected_video_analysis_reference: { type: 'analysis', note: 'Analysis used.' },
    selected_voiceover_reference: { type: 'none', note: 'No voiceover.' },
    cost_breakdown: { base_editor_cost: 2, video_analysis_cost: 0, voiceover_cost: 0, total_credits: 2, base_editor_note: '', video_analysis_note: '', voiceover_note: '' },
    compliance_notes: [],
    usage_notes: [],
  } as unknown as GenerationResult,
})

const baseActions: ProjectActionsResponse = {
  data: [
    action({
      key: 'generate_campaign',
      title: 'Product Content Kit',
      description: 'Create campaign content.',
      action_endpoint: '/api/v1/projects/42/generate-campaign',
      primary_cta: { label: 'Generate campaign', route: null, action: 'generate_campaign' },
      tool_key: 'affiliate_campaign',
    }),
    action({
      key: 'generate_video_to_campaign',
      title: 'Analyze Footage with AI',
      description: 'Analyze product footage.',
      service_tier: 'advanced',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 2,
      primary_cta: { label: 'Analyze footage with AI', route: null, action: 'configure_video_to_campaign' },
      tool_key: 'video_to_campaign',
      reason: 'A project video is available for footage analysis.',
    }),
    action({
      key: 'start_motion_video',
      title: 'Motion AI Video',
      description: 'Generate premium AI product footage.',
      service_tier: 'premium',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 3,
      primary_cta: { label: 'Configure Motion AI Video', route: '/projects/42/actions/motion-ai-video', action: 'configure_motion_ai_video' },
      tool_key: 'motion_ai_video',
    }),
    action({
      key: 'generate_auto_video_edit',
      title: 'Auto Video Editor',
      description: 'Turn product footage into an edited creator video.',
      service_tier: 'premium',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 2,
      primary_cta: { label: 'Configure edit plan', route: null, action: 'configure_auto_video_editor' },
      tool_key: 'auto_video_editor',
      reason: 'Analyze footage first, or use manual edit setup.',
    }),
    action({
      key: 'start_fashion_try_on',
      title: 'AI Fashion Try-On',
      description: 'Create one realistic fashion try-on image.',
      service_tier: 'premium',
      output_type: 'image',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 2,
      primary_cta: { label: 'Set up Fashion Try-On', route: '/projects/42/actions/fashion-try-on', action: 'configure_fashion_try_on' },
      tool_key: 'fashion_try_on',
    }),
    action({
      key: 'create_video_ad',
      title: 'Auto Video Ads',
      description: 'Create paid ad creative plans.',
      service_tier: 'premium',
      status: 'coming_soon',
      action_mode: 'coming_soon',
      credit_cost: null,
      primary_cta: { label: 'Coming soon', route: null, action: null },
      tool_key: 'auto_video_ads',
      tool_status: 'coming_soon',
    }),
    action({
      key: 'generate_voiceover_from_script',
      title: 'Create voiceover',
      description: 'Internal compatibility action.',
      service_tier: 'advanced',
      output_type: 'audio',
      tool_key: 'ai_voiceover',
    }),
  ],
}

const campaignResponse: CampaignGenerationResponse = {
  data: generation({ id: 12, job_type: 'generate_affiliate_campaign', status: 'queued' }) as CampaignGenerationResponse['data'],
  meta: { credits_balance: 19 },
}

async function mountHub(props: Partial<InstanceType<typeof ProjectActionHub>['$props']> = {}) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/auto-video-editor', name: 'project-auto-video-editor-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/fashion-try-on', name: 'project-fashion-try-on-action', component: { template: '<div />' } },
    ],
  })
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/actions')) {
      return Response.json(baseActions)
    }

    if (url.endsWith('/projects/42/generate-campaign') && method === 'POST') {
      return Response.json(campaignResponse, { status: 201 })
    }

    return Response.json({}, { status: 404 })
  })

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push('/')
  await router.isReady()

  const wrapper = mount(ProjectActionHub, {
    props: {
      projectId: 42,
      selectedGeneration: campaignGeneration,
      latestCampaignGeneration: campaignGeneration,
      latestVideoAnalysisGeneration: videoAnalysisGeneration,
      latestMotionAiVideoGeneration: motionGeneration,
      latestAutoVideoEditorGeneration: autoEditorGeneration,
      ...props,
    },
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return { wrapper, fetchMock, router }
}

describe('ProjectActionHub', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('matches the final workflow order and removes top-level supporting tools', async () => {
    const { wrapper } = await mountHub()
    const availableCards = wrapper.findAll('[data-testid^="project-action-"]').filter((card) => card.text().includes('Available now') || !card.text().includes('Coming soon'))
    const text = wrapper.text()

    const creatorSection = wrapper.get('[data-testid="project-creator_content-workflow-section"]')
    const commerceSection = wrapper.get('[data-testid="project-commerce_studio-workflow-section"]')

    expect(creatorSection.text()).toContain('Creator Content')
    expect(creatorSection.text()).toContain('Product Content Kit')
    expect(creatorSection.text()).toContain('Analyze Footage with AI')
    expect(creatorSection.text()).toContain('Motion AI Video')
    expect(creatorSection.text()).toContain('Auto Video Editor')
    expect(commerceSection.text()).toContain('Commerce Studio')
    expect(commerceSection.text()).toContain('AI Fashion Try-On')
    expect(wrapper.findAll('[data-testid="project-action-start_fashion_try_on"]')).toHaveLength(1)
    expect(availableCards.length).toBeGreaterThanOrEqual(5)
    expect(text).not.toContain('Supporting tools')
    expect(text).not.toContain('Create voiceover')
    expect(text).not.toContain('Mock beta')
  })

  it('keeps Auto Video Ads as the only coming soon workspace action', async () => {
    const { wrapper } = await mountHub()
    const comingSoon = wrapper.get('[data-testid="project-coming_soon-workflow-section"]')
    const ads = wrapper.get('[data-testid="project-action-create_video_ad"]')

    expect(comingSoon.text()).toContain('Auto Video Ads')
    expect(comingSoon.text()).toContain('Coming soon')
    expect(ads.find('button').attributes('disabled')).toBeDefined()
  })

  it('runs Product Content Kit explicitly and links to the latest kit', async () => {
    const { wrapper, fetchMock } = await mountHub()
    const campaignCard = wrapper.get('[data-testid="project-action-generate_campaign"]')

    expect(campaignCard.text()).toContain('View latest Product Content Kit')
    expect(wrapper.get('[data-testid="view-latest-product_content_kit"]').attributes('href')).toBe('/projects/42/generations/9')

    await campaignCard.find('button').trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-campaign',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(wrapper.emitted('generated')?.[0]).toEqual([campaignResponse])
  })

  it('opens Motion AI Video with campaign-assisted preselection from a compatible campaign', async () => {
    const { wrapper, router } = await mountHub()

    await wrapper.get('[data-testid="project-action-start_motion_video"]').find('button').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-motion-ai-video-action')
    expect(router.currentRoute.value.query.sourceMode).toBe('campaign_assisted')
    expect(router.currentRoute.value.query.campaignGenerationId).toBe('9')
  })

  it('links latest Motion AI Video MP4 to Analyze Footage without generating', async () => {
    const { wrapper } = await mountHub()
    const link = wrapper.get('[data-testid="project-secondary-motion_ai_video"]')

    expect(link.text()).toContain('Analyze this video with AI')
    expect(link.attributes('href')).toContain('/projects/42/actions/video-to-campaign')
    expect(link.attributes('href')).toContain('sourceVideoAssetId=104')
    expect(link.attributes('href')).toContain('fromGenerationId=44')
  })

  it('opens Auto Video Editor with analysis preselection when a footage analysis exists', async () => {
    const { wrapper, router } = await mountHub({ selectedGeneration: videoAnalysisGeneration })

    await wrapper.get('[data-testid="project-action-generate_auto_video_edit"]').find('button').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-auto-video-editor-action')
    expect(router.currentRoute.value.query.editMode).toBe('ai_video_analysis')
    expect(router.currentRoute.value.query.analysisGenerationId).toBe('31')
    expect(router.currentRoute.value.query.sourceVideoAssetId).toBe('88')
  })


  it('opens AI Fashion Try-On without starting a generation', async () => {
    const { wrapper, router, fetchMock } = await mountHub()

    await wrapper.get('[data-testid="project-action-start_fashion_try_on"]').find('button').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-fashion-try-on-action')
    expect(fetchMock).not.toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-fashion-try-on',
      expect.objectContaining({ method: 'POST' }),
    )
  })
})
