import { afterEach, describe, expect, it, vi } from 'vitest'

import { API_BASE_URL } from '@/services/api'
import {
  createCampaignProject,
  generateCampaignProject,
  generateVideoEnhancerProject,
  getProjectGenerations,
} from '@/services/projects'
import type {
  CampaignFormPayload,
  CampaignGenerationResponse,
  CampaignProject,
  ProjectGenerationHistoryResponse,
  VideoEnhancerGenerationResponse,
} from '@/types/project'

describe('project service', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('creates the affiliate campaign with the backend project contract', async () => {
    const campaign: CampaignFormPayload = {
      product_name: 'Portable smoothie blender',
      product_description: 'A rechargeable blender for quick smoothies.',
      target_audience: 'Busy students who want healthy breakfasts.',
      language: 'english',
      tone: 'problem_solution',
    }

    const project: CampaignProject = {
      id: 42,
      user_id: 1,
      title: campaign.product_name,
      type: 'affiliate_campaign',
      status: 'draft',
      input_payload: campaign,
      settings: {
        platform: 'tiktok',
        format: 'vertical_9_16',
      },
      created_at: '2026-07-05T00:00:00.000000Z',
      updated_at: '2026-07-05T00:00:00.000000Z',
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: project }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(createCampaignProject(campaign)).resolves.toEqual(project)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: campaign.product_name,
          type: 'affiliate_campaign',
          status: 'draft',
          input_payload: campaign,
          settings: {
            platform: 'tiktok',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
  })

  it('posts to the mock campaign generation endpoint', async () => {
    const response: CampaignGenerationResponse = {
      data: {
        id: 7,
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
          script: { title: 'Script', duration_seconds: 30, scenes: [] },
          caption: 'Caption',
          hashtags: ['#Product'],
          cta: 'See details.',
          notes: ['Use real footage.'],
        },
        started_at: '2026-07-05T00:00:00.000000Z',
        finished_at: '2026-07-05T00:00:00.000000Z',
        created_at: '2026-07-05T00:00:00.000000Z',
      },
      meta: { credits_balance: 19 },
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(response), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(generateCampaignProject(42)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-campaign',
      expect.objectContaining({
        method: 'POST',
        body: '{}',
      }),
    )
  })

  it('preserves the insufficient-credit API error code', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(
          JSON.stringify({
            message: 'You do not have enough platform credits to generate this campaign.',
            code: 'insufficient_credits',
          }),
          {
            status: 402,
            headers: { 'Content-Type': 'application/json' },
          },
        ),
      ),
    )

    await expect(generateCampaignProject(42)).rejects.toMatchObject({
      name: 'ApiError',
      status: 402,
      code: 'insufficient_credits',
    })
  })

  it('loads the paginated generation history for a project', async () => {
    const response: ProjectGenerationHistoryResponse = {
      data: [
        {
          id: 8,
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
            hooks: ['Saved hook'],
            script: { title: 'Saved script', duration_seconds: 30, scenes: [] },
            caption: 'Saved caption',
            hashtags: ['#Saved'],
            cta: 'See details.',
            notes: ['Use real footage.'],
          },
          started_at: '2026-07-05T01:00:00.000000Z',
          finished_at: '2026-07-05T01:00:00.000000Z',
          created_at: '2026-07-05T01:00:00.000000Z',
        },
      ],
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(response), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(getProjectGenerations(42)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generations',
      expect.objectContaining({
        headers: expect.any(Headers),
      }),
    )
  })
  it('posts to the mock video enhancer generation endpoint', async () => {
    const response: VideoEnhancerGenerationResponse = {
      data: {
        id: 10,
        user_id: 1,
        project_id: 77,
        job_type: 'generate_video_enhancer',
        provider: 'mock',
        status: 'completed',
        cost_usd: '0.0000',
        credits_charged: 1,
        input_payload: null,
        output_payload: null,
        error_message: null,
        request_payload: null,
        result_payload: {
          improved_caption: 'Improved caption',
          subtitle_plan: [{ timestamp: '0-3s', text: 'Opening subtitle' }],
          cut_plan: [
            {
              timestamp: '0-3s',
              edit_direction: 'Open on the product.',
              reason: 'Immediate context.',
            },
          ],
          on_screen_text: ['Meet the product'],
          posting_text: 'Posting text',
          hashtags: ['#Product'],
          cta: 'Review the details.',
          notes: ['Match timing to the real recording.'],
        },
        started_at: '2026-07-06T02:00:00.000000Z',
        finished_at: '2026-07-06T02:00:00.000000Z',
        created_at: '2026-07-06T02:00:00.000000Z',
      },
      meta: { credits_balance: 18 },
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(response), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(generateVideoEnhancerProject(77)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/77/generate-video-enhancer',
      expect.objectContaining({
        method: 'POST',
        body: '{}',
      }),
    )
  })
})
