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

const selectedCampaignGeneration: CreatorGeneration = {
  id: 9,
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
    hooks: ['Hook'],
    voiceover_ready_script: 'Voiceover-ready script from the selected campaign.',
    script: { title: 'Campaign script', duration_seconds: 30, scenes: [] },
    caption: 'Caption',
    hashtags: ['#Creator'],
    cta: 'Review details.',
    notes: ['Use real footage.'],
  },
  started_at: '2026-07-08T01:00:00.000000Z',
  finished_at: '2026-07-08T01:00:00.000000Z',
  created_at: '2026-07-08T01:00:00.000000Z',
}

const selectedVoiceoverGeneration: CreatorGeneration = {
  ...selectedCampaignGeneration,
  id: 22,
  job_type: 'generate_ai_voiceover',
  result_payload: null,
}

const baseActions: ProjectActionsResponse = {
  data: [
    action({
      key: 'generate_campaign',
      title: 'Generate campaign',
      description: 'Create campaign content.',
      service_tier: 'basic',
      output_type: 'script_pack',
      primary_cta: { label: 'Generate another campaign version', route: null, action: 'generate_campaign' },
      action_endpoint: '/api/v1/projects/42/generate-campaign',
      tool_key: 'affiliate_campaign',
    }),
    action({
      key: 'generate_voiceover_from_script',
      title: 'Create voiceover',
      description: 'Generate an AI voiceover using a completed script.',
      service_tier: 'advanced',
      output_type: 'audio',
      primary_cta: { label: 'Create another voiceover', route: null, action: 'generate_voiceover' },
      required_source: 'source_generation',
      source_generation_id: 9,
      action_endpoint: '/api/v1/projects/42/generate-voiceover',
      tool_key: 'ai_voiceover',
      recommended_sources: [
        {
          generation_id: 9,
          job_type: 'generate_affiliate_campaign',
          title: 'Campaign script source',
          created_at: '2026-07-08T01:00:00.000000Z',
          excerpt: 'Voiceover-ready script from the selected campaign.',
        },
      ],
    }),
    action({
      key: 'generate_video_to_campaign',
      title: 'Generate campaign from video',
      description: 'Turn source footage into a campaign.',
      service_tier: 'advanced',
      output_type: 'assisted_media',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 2,
      primary_cta: { label: 'Generate video campaign', route: null, action: 'configure_video_to_campaign' },
      required_source: 'media_asset',
      action_endpoint: '/api/v1/projects/42/generate-video-campaign',
      tool_key: 'video_to_campaign',
      reason: 'A project video is available for campaign analysis.',
      recommended_assets: [
        {
          media_asset_id: 88,
          type: 'video',
          usage_role: 'campaign_source_video',
          original_name: 'raw-product-demo.mp4',
          mime_type: 'video/mp4',
          created_at: '2026-07-08T01:00:00.000000Z',
        },
      ],
    }),
    action({
      key: 'generate_auto_video_edit',
      title: 'Auto Video Editor',
      description: 'Create an edit-ready vertical video plan.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'configure',
      action_mode: 'configure',
      credit_cost: 2,
      primary_cta: { label: 'Configure edit plan', route: null, action: 'configure_auto_video_editor' },
      required_source: 'source_generation',
      action_endpoint: '/api/v1/projects/42/generate-auto-video-edit',
      tool_key: 'auto_video_editor',
      reason: 'Can reuse existing video campaign analysis.',
      recommended_sources: [
        {
          generation_id: 31,
          job_type: 'generate_video_to_campaign',
          title: 'Reusable edit plan',
          created_at: '2026-07-08T02:00:00.000000Z',
          excerpt: 'Reusable narration.',
        },
      ],
    }),
    action({
      key: 'start_fashion_try_on',
      title: 'Start Fashion Try-On',
      description: 'Use project assets for try-on.',
      service_tier: 'premium',
      output_type: 'image',
      status: 'coming_soon',
      action_mode: 'coming_soon',
      credit_cost: 2,
      primary_cta: { label: 'Coming soon', route: null, action: null },
      required_source: 'media_asset',
      action_endpoint: '/api/v1/projects/42/generate-fashion-try-on',
      tool_key: 'fashion_try_on',
      requirements: [
        {
          type: 'media_asset',
          key: 'model_reference',
          label: 'Model reference image',
          satisfied: false,
          help_text: 'Upload a clear model/person reference image in the launcher.',
          suggested_usage_role: 'model_reference',
        },
        {
          type: 'media_asset',
          key: 'garment_reference',
          label: 'Garment reference image',
          satisfied: false,
          help_text: 'Upload a clear garment/product reference image in the launcher.',
          suggested_usage_role: 'garment_reference',
        },
      ],
      missing_requirements_count: 2,
    }),    action({
      key: 'start_motion_video',
      title: 'Generate Motion AI Video',
      description: 'Use project visuals for motion video.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'coming_soon',
      action_mode: 'coming_soon',
      credit_cost: 3,
      primary_cta: { label: 'Coming soon', route: null, action: null },
      required_source: 'media_asset',
      action_endpoint: '/api/v1/projects/42/generate-motion-video',
      tool_key: 'motion_ai_video',
      requirements: [
        {
          type: 'media_asset',
          key: 'product_image',
          label: 'Product image',
          satisfied: false,
          help_text: 'Upload or choose a product image for the motion video source.',
          suggested_usage_role: 'product_image',
        },
      ],
      missing_requirements_count: 1,
    }),
    action({
      key: 'create_video_ad',
      title: 'Create video ad',
      description: 'Use campaign context later.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'coming_soon',
      action_mode: 'coming_soon',
      credit_cost: null,
      primary_cta: { label: 'Coming soon', route: null, action: null },
      required_source: 'source_generation',
      tool_status: null,
    }),
    action({
      key: 'avatar_presenter',
      title: 'Avatar Presenter',
      description: 'Turn a script into an avatar presenter later.',
      service_tier: 'premium',
      output_type: 'avatar',
      status: 'coming_soon',
      action_mode: 'coming_soon',
      credit_cost: null,
      primary_cta: { label: 'Coming soon', route: null, action: null },
      required_source: 'source_generation',
      tool_status: null,
    }),
  ],
}

const campaignResponse: CampaignGenerationResponse = {
  data: {
    id: 12,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_affiliate_campaign',
    provider: 'mock',
    status: 'queued',
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: null,
    output_payload: null,
    error_message: null,
    request_payload: null,
    result_payload: null,
    started_at: null,
    finished_at: null,
    created_at: '2026-07-08T01:05:00.000000Z',
  },
  meta: { credits_balance: 19 },
}


async function mountHub(
  actions: ProjectActionsResponse = baseActions,
  selectedGeneration: CreatorGeneration | null = selectedCampaignGeneration,
  initialPath = '/',
) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/auto-video-editor', name: 'project-auto-video-editor-action', component: { template: '<div />' } },
    ],
  })
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/actions')) {
      return Response.json(actions)
    }

    if (url.endsWith('/projects/42/generate-campaign') && method === 'POST') {
      return Response.json(campaignResponse, { status: 201 })
    }


    return Response.json({}, { status: 404 })
  })
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push(initialPath)
  await router.isReady()

  const wrapper = mount(ProjectActionHub, {
    props: {
      projectId: 42,
      selectedGeneration,
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

  it('shows active beta workflow credit and access badges without roadmap clutter', async () => {
    const { wrapper } = await mountHub()
    const text = wrapper.text()

    expect(text).not.toContain('Basic workflow')
    expect(text).not.toContain('Advanced workflow')
    expect(text).not.toContain('Premium workflow')
    expect(text).toContain('1 credit')
    expect(text).toContain('2 credits')
    expect(text).toContain('Included in your plan')
    expect(text).toContain('Generate video insight')
    expect(text).toContain('Configure Auto Video Editor')
    expect(text).not.toContain('Coming soon')
    expect(wrapper.find('[data-testid="project-action-start_fashion_try_on"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="project-action-start_motion_video"]').exists()).toBe(false)
    expect(text).not.toContain('Upload a clear model/person reference image in the launcher.')
    expect(text).not.toContain('Upload a clear garment/product reference image in the launcher.')
    expect(text).not.toContain('Upload or choose a product image for the motion video source.')
  })

  it('runs the direct campaign action and emits the new generation', async () => {
    const { wrapper, fetchMock } = await mountHub()

    const campaignButton = wrapper
      .get('[data-testid="project-action-generate_campaign"]')
      .find('button')

    expect(campaignButton.text()).toContain('Generate another Product Content Kit')
    expect(wrapper.get('[data-testid="view-generated-campaign"]').attributes('href')).toBe('/projects/42/generations/9')
    await campaignButton.trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-campaign',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(wrapper.emitted('generated')?.[0]).toEqual([campaignResponse])
  })

  it('does not render premium roadmap actions in the focused workspace hub', async () => {
    const { wrapper } = await mountHub()

    expect(wrapper.find('[data-testid="project-action-start_fashion_try_on"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="project-action-start_motion_video"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="project-action-avatar_presenter"]').exists()).toBe(false)
  })
  it('routes voiceover actions to the dedicated setup page with selected source context', async () => {
    const { wrapper, fetchMock, router } = await mountHub(
      baseActions,
      selectedCampaignGeneration,
      '/projects/42/generations/9',
    )

    const voiceoverButton = wrapper
      .get('[data-testid="project-action-generate_voiceover_from_script"]')
      .find('button')

    expect(voiceoverButton.text()).toContain('Create another voiceover')
    await voiceoverButton.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-voiceover-action')
    expect(router.currentRoute.value.params.projectId).toBe('42')
    expect(router.currentRoute.value.query.source_generation_id).toBe('9')
    expect(router.currentRoute.value.query.return_to).toBe('/projects/42/generations/9')
    expect(wrapper.find('[data-testid="project-action-launcher"]').exists()).toBe(false)
    expect(fetchMock).not.toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-voiceover',
      expect.anything(),
    )
  })

  it('routes voiceover setup without source query when no saved source exists', async () => {
    const noSourceActions: ProjectActionsResponse = {
      data: baseActions.data.map((item) => item.key === 'generate_voiceover_from_script'
        ? {
            ...item,
            status: 'configure',
            action_mode: 'configure',
            source_generation_id: null,
            recommended_sources: [],
            primary_cta: { label: 'Create voiceover', route: null, action: 'generate_voiceover' },
          }
        : item),
    }
    const { wrapper, router } = await mountHub(noSourceActions, null)

    await wrapper
      .get('[data-testid="project-action-generate_voiceover_from_script"]')
      .find('button')
      .trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-voiceover-action')
    expect(router.currentRoute.value.query.source_generation_id).toBeUndefined()
  })

  it('shows a latest voiceover link when a completed voiceover generation is available', async () => {
    const { wrapper } = await mountHub(baseActions, selectedCampaignGeneration)

    await wrapper.setProps({ latestVoiceoverGeneration: { ...selectedVoiceoverGeneration, status: 'completed' } })
    await flushPromises()

    expect(wrapper.get('[data-testid="view-latest-voiceover"]').attributes('href')).toBe('/projects/42/generations/22')
    expect(wrapper.text()).toContain('View latest voiceover')
  })



  it('routes video workflow actions to dedicated setup pages', async () => {
    const { wrapper, router } = await mountHub()

    await wrapper
      .get('[data-testid="project-action-generate_video_to_campaign"]')
      .find('button')
      .trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-video-to-campaign-action')
    expect(router.currentRoute.value.params.projectId).toBe('42')

    await router.push('/')
    await flushPromises()

    await wrapper
      .get('[data-testid="project-action-generate_auto_video_edit"]')
      .find('button')
      .trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-auto-video-editor-action')
    expect(router.currentRoute.value.params.projectId).toBe('42')
    expect(wrapper.text()).toContain('Can reuse existing video campaign analysis.')
  })

})