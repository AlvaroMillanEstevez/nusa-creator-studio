import { afterEach, describe, expect, it, vi } from 'vitest'

import { API_BASE_URL } from '@/services/api'
import { createCampaignProject } from '@/services/projects'
import type { CampaignFormPayload, CampaignProject } from '@/types/project'

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
})
