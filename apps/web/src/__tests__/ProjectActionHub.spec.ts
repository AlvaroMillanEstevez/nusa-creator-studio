import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import ProjectActionHub from '@/components/ProjectActionHub.vue'
import { API_BASE_URL } from '@/services/api'
import type {
  AiVoiceoverGenerationResponse,
  CreatorGeneration,
  ProjectActionsResponse,
} from '@/types/project'

const actionsResponse: ProjectActionsResponse = {
  data: [
    {
      key: 'generate_campaign',
      title: 'Generate campaign',
      description: 'Create campaign content.',
      service_tier: 'basic',
      output_type: 'script_pack',
      status: 'available',
      required_source: null,
      source_generation_id: null,
      reason: 'Project has enough product brief context.',
      action_endpoint: '/api/v1/projects/42/generate-campaign',
      tool_key: 'affiliate_campaign',
      tool_status: 'available',
      credit_cost: 1,
    },
    {
      key: 'generate_voiceover_from_campaign',
      title: 'Create voiceover from this script',
      description: 'Generate an AI voiceover using a completed script.',
      service_tier: 'advanced',
      output_type: 'audio',
      status: 'available',
      required_source: 'generation',
      source_generation_id: 9,
      reason: 'A completed script is available for voiceover generation.',
      action_endpoint: '/api/v1/projects/42/generate-voiceover',
      tool_key: 'ai_voiceover',
      tool_status: 'available',
      credit_cost: 1,
    },
    {
      key: 'start_video_ad',
      title: 'Create video ad',
      description: 'Use campaign context later.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'coming_soon',
      required_source: 'generation',
      source_generation_id: null,
      reason: 'Video ad assembly is planned for later.',
      action_endpoint: null,
      tool_key: null,
      tool_status: null,
      credit_cost: null,
    },
    {
      key: 'start_fashion_try_on',
      title: 'Start Fashion Try-On',
      description: 'Use project assets for try-on.',
      service_tier: 'premium',
      output_type: 'image',
      status: 'needs_assets',
      required_source: 'media_asset',
      source_generation_id: null,
      reason: 'Add model and garment reference images first.',
      action_endpoint: '/api/v1/projects/42/generate-fashion-try-on',
      tool_key: 'fashion_try_on',
      tool_status: 'available',
      credit_cost: 2,
    },
    {
      key: 'start_motion_video',
      title: 'Generate Motion AI Video',
      description: 'Use project visuals for motion video.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'needs_assets',
      required_source: 'media_asset',
      source_generation_id: null,
      reason: 'Add a visual reference first.',
      action_endpoint: '/api/v1/projects/42/generate-motion-video',
      tool_key: 'motion_ai_video',
      tool_status: 'available',
      credit_cost: 3,
    },
    {
      key: 'avatar_presenter',
      title: 'Avatar Presenter',
      description: 'Turn a script into an avatar presenter later.',
      service_tier: 'premium',
      output_type: 'avatar',
      status: 'coming_soon',
      required_source: 'generation',
      source_generation_id: null,
      reason: 'Avatar presenter generation is not implemented yet.',
      action_endpoint: null,
      tool_key: null,
      tool_status: null,
      credit_cost: null,
    },
  ],
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

const voiceoverResponse: AiVoiceoverGenerationResponse = {
  data: {
    id: 22,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_ai_voiceover',
    provider: 'mock',
    status: 'queued',
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: {
      source_generation_id: 9,
      source_text: 'Voiceover-ready script from the selected campaign.',
    },
    output_payload: null,
    error_message: null,
    request_payload: null,
    result_payload: null,
    started_at: null,
    finished_at: null,
    created_at: '2026-07-08T01:10:00.000000Z',
  },
  meta: { credits_balance: 18 },
}

describe('ProjectActionHub', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders available, needs-assets and coming-soon actions', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(Response.json(actionsResponse)),
    )

    const wrapper = mount(ProjectActionHub, {
      props: {
        projectId: 42,
        selectedGeneration: selectedCampaignGeneration,
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Continue with this project')
    expect(wrapper.text()).toContain('Continue with selected result')
    expect(wrapper.text()).toContain('Generate campaign')
    expect(wrapper.text()).toContain('Create voiceover from this script')
    expect(wrapper.text()).toContain('Needs assets')
    expect(wrapper.text()).toContain('Coming soon')
  })

  it('posts source_generation_id and source_text when creating a voiceover from the selected result', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/projects/42/actions')) {
        return Response.json(actionsResponse)
      }

      if (url.endsWith('/projects/42/generate-voiceover') && init?.method === 'POST') {
        return Response.json(voiceoverResponse, { status: 201 })
      }

      return Response.json({}, { status: 404 })
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectActionHub, {
      props: {
        projectId: 42,
        selectedGeneration: selectedCampaignGeneration,
      },
    })
    await flushPromises()

    const button = wrapper.findAll('button').find((candidate) =>
      candidate.text().includes('Create voiceover from this script'),
    )

    expect(button).toBeTruthy()
    await button?.trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-voiceover',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          source_generation_id: 9,
          source_text: 'Voiceover-ready script from the selected campaign.',
        }),
      }),
    )
    expect(wrapper.emitted('generated')?.[0]).toEqual([voiceoverResponse])
  })
})