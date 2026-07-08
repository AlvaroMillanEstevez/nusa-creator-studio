import { afterEach, describe, expect, it, vi } from 'vitest'

import { API_BASE_URL } from '@/services/api'
import {
  createAiVoiceoverProject,
  createCampaignProject,
  createFashionTryOnProject,
  createMotionVideoProject,
  generateAiVoiceover,
  generateCampaignProject,
  generateFashionTryOn,
  generateMotionVideo,
  generateVideoEnhancerProject,
  getProjectActions,
  getProjectGenerations,
} from '@/services/projects'
import type {
  AiVoiceoverFormPayload,
  AiVoiceoverGenerationResponse,
  AiVoiceoverProject,
  CampaignFormPayload,
  CampaignGenerationResponse,
  CampaignProject,
  FashionTryOnFormPayload,
  FashionTryOnGenerationResponse,
  FashionTryOnProject,
  MotionVideoFormPayload,
  MotionVideoGenerationResponse,
  MotionVideoProject,
  ProjectActionsResponse,
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
  it('loads contextual project actions', async () => {
    const response: ProjectActionsResponse = {
      data: [
        {
          key: 'generate_voiceover_from_campaign',
          title: 'Create voiceover from this script',
          description: 'Generate a voiceover from the selected script.',
          service_tier: 'advanced',
          output_type: 'audio',
          status: 'available',
          required_source: 'generation',
          source_generation_id: 8,
          reason: 'A completed script is available.',
          action_endpoint: '/api/v1/projects/42/generate-voiceover',
          tool_key: 'ai_voiceover',
          tool_status: 'available',
          credit_cost: 1,
        },
      ],
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(response), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(getProjectActions(42)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/actions',
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
  it('creates the AI Voiceover project with the backend project contract', async () => {
    const brief: AiVoiceoverFormPayload = {
      title: 'Portable key light voiceover',
      source_text: 'Use a warm narration for a product demo.',
      product_name: 'Portable key light',
      product_description: 'A small light for creator desks.',
      target_audience: 'Mobile-first creators',
      language: 'english',
      voice_style: 'natural_creator',
      tone: 'natural',
      pace: 'medium',
      duration_seconds: 30,
      platform: 'tiktok',
      notes: null,
    }

    const project: AiVoiceoverProject = {
      id: 88,
      user_id: 1,
      title: brief.title,
      type: 'ai_voiceover',
      status: 'draft',
      input_payload: brief,
      settings: {
        platform: 'tiktok',
        format: 'vertical_9_16',
      },
      created_at: '2026-07-07T00:00:00.000000Z',
      updated_at: '2026-07-07T00:00:00.000000Z',
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: project }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(createAiVoiceoverProject(brief)).resolves.toEqual(project)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: brief.title,
          type: 'ai_voiceover',
          status: 'draft',
          input_payload: brief,
          settings: {
            platform: 'tiktok',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
  })

  it('posts to the mock AI Voiceover generation endpoint', async () => {
    const response: AiVoiceoverGenerationResponse = {
      data: {
        id: 18,
        user_id: 1,
        project_id: 88,
        job_type: 'generate_ai_voiceover',
        provider: 'mock',
        status: 'completed',
        cost_usd: '0.0000',
        credits_charged: 1,
        input_payload: null,
        output_payload: null,
        error_message: null,
        request_payload: null,
        result_payload: {
          voiceover_title: 'Portable key light voiceover',
          narration_script: 'Before you scroll, meet the portable key light.',
          scene_timing: [
            {
              timestamp: '0-3s',
              section: 'Hook',
              narration_focus: 'Open with the product.',
              delivery_note: 'Warm opening.',
            },
          ],
          voice_direction: 'Natural creator delivery.',
          pronunciation_notes: ['Say the product name clearly.'],
          pacing_notes: ['Keep it near 30 seconds.'],
          platform_notes: ['Mix under vertical-video footage.'],
          estimated_duration_seconds: 30,
          suggested_audio_format: 'MP3 or WAV.',
          usage_notes: ['No real audio generated yet.'],
          mock_audio: {
            status: 'placeholder',
            message: 'Real audio generation will be connected later.',
            provider: 'mock',
          },
          compliance_notes: ['Avoid exaggerated claims.'],
        },
        started_at: '2026-07-07T00:00:00.000000Z',
        finished_at: '2026-07-07T00:00:00.000000Z',
        created_at: '2026-07-07T00:00:00.000000Z',
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

    await expect(generateAiVoiceover(88)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/88/generate-voiceover',
      expect.objectContaining({
        method: 'POST',
        body: '{}',
      }),
    )
  })
  it('posts AI Voiceover source context for project workspace chaining', async () => {
    const response: AiVoiceoverGenerationResponse = {
      data: {
        id: 19,
        user_id: 1,
        project_id: 42,
        job_type: 'generate_ai_voiceover',
        provider: 'mock',
        status: 'queued',
        cost_usd: '0.0000',
        credits_charged: 1,
        input_payload: {
          source_generation_id: 7,
          source_text: 'Voiceover-ready campaign script.',
        },
        output_payload: null,
        error_message: null,
        request_payload: null,
        result_payload: null,
        started_at: null,
        finished_at: null,
        created_at: '2026-07-07T00:00:00.000000Z',
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

    await expect(
      generateAiVoiceover(42, {
        source_generation_id: 7,
        source_text: 'Voiceover-ready campaign script.',
      }),
    ).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/generate-voiceover',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          source_generation_id: 7,
          source_text: 'Voiceover-ready campaign script.',
        }),
      }),
    )
  })
  it('creates the Fashion Try-On project with the backend project contract', async () => {
    const brief: FashionTryOnFormPayload = {
      title: 'Streetwear jacket try-on',
      product_name: 'Everyday creator jacket',
      product_description: 'A lightweight jacket for content shoots.',
      garment_type: 'jacket',
      target_audience: 'Fashion affiliate creators',
      style_goal: 'product_showcase',
      output_style: 'realistic',
      model_notes: 'Use a relaxed standing pose.',
      garment_notes: 'Keep the zipper visible.',
      background_preference: 'clean studio wall',
      language: 'english',
      platform: 'tiktok',
      notes: null,
    }

    const project: FashionTryOnProject = {
      id: 99,
      user_id: 1,
      title: brief.title,
      type: 'fashion_try_on',
      status: 'draft',
      input_payload: brief,
      settings: { platform: 'tiktok', format: 'vertical_9_16' },
      created_at: '2026-07-07T00:00:00.000000Z',
      updated_at: '2026-07-07T00:00:00.000000Z',
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: project }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(createFashionTryOnProject(brief)).resolves.toEqual(project)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: brief.title,
          type: 'fashion_try_on',
          status: 'draft',
          input_payload: brief,
          settings: { platform: 'tiktok', format: 'vertical_9_16' },
        }),
      }),
    )
  })

  it('posts to the mock Fashion Try-On generation endpoint', async () => {
    const response: FashionTryOnGenerationResponse = {
      data: {
        id: 31,
        user_id: 1,
        project_id: 99,
        job_type: 'generate_fashion_try_on',
        provider: 'mock',
        status: 'completed',
        cost_usd: '0.0000',
        credits_charged: 2,
        input_payload: null,
        output_payload: null,
        error_message: null,
        request_payload: null,
        result_payload: {
          try_on_title: 'Streetwear jacket try-on',
          concept_summary: 'Create a realistic try-on concept.',
          model_reference_summary: 'Use model-reference.jpg.',
          garment_reference_summary: 'Use garment-reference.jpg.',
          generated_image: {
            status: 'placeholder',
            message: 'Real try-on image generation will be connected later.',
            provider: 'mock',
          },
          composition_plan: ['Vertical composition.'],
          pose_and_framing_notes: ['Relaxed pose.'],
          garment_fit_notes: ['Keep fit believable.'],
          background_and_lighting_notes: ['Soft light.'],
          platform_usage_notes: ['Use for TikTok planning.'],
          quality_checklist: ['References are images.'],
          api_ready_prompt: 'Vertical fashion try-on mockup.',
          negative_prompt: 'distorted body, changed garment design',
          recommended_assets: ['Model image', 'Garment image'],
          compliance_notes: ['No real image generated yet.'],
        },
        started_at: '2026-07-07T00:00:00.000000Z',
        finished_at: '2026-07-07T00:00:00.000000Z',
        created_at: '2026-07-07T00:00:00.000000Z',
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

    await expect(generateFashionTryOn(99)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/99/generate-fashion-try-on',
      expect.objectContaining({ method: 'POST', body: '{}' }),
    )
  })

  it('creates the Motion AI Video project with the backend project contract', async () => {
    const brief: MotionVideoFormPayload = {
      title: 'Portable blender motion video',
      product_name: 'Portable smoothie blender',
      product_description: 'A compact blender for quick creator product demos.',
      video_goal: 'product_showcase',
      motion_style: 'smooth_camera',
      visual_style: 'ecommerce_clean',
      duration_seconds: 15,
      aspect_ratio: 'vertical_9_16',
      target_audience: 'TikTok affiliate creators',
      platform: 'tiktok',
      language: 'english',
      brand_notes: 'Keep it clean.',
      scene_notes: 'Open on the product.',
      audio_notes: 'Use upbeat pacing later.',
      notes: null,
    }

    const project: MotionVideoProject = {
      id: 123,
      user_id: 1,
      title: brief.title,
      type: 'motion_ai_video',
      status: 'draft',
      input_payload: brief,
      settings: { platform: 'tiktok', format: 'vertical_9_16' },
      created_at: '2026-07-07T00:00:00.000000Z',
      updated_at: '2026-07-07T00:00:00.000000Z',
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: project }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(createMotionVideoProject(brief)).resolves.toEqual(project)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: brief.title,
          type: 'motion_ai_video',
          status: 'draft',
          input_payload: brief,
          settings: { platform: 'tiktok', format: 'vertical_9_16' },
        }),
      }),
    )
  })

  it('posts to the mock Motion AI Video generation endpoint', async () => {
    const response: MotionVideoGenerationResponse = {
      data: {
        id: 44,
        user_id: 1,
        project_id: 123,
        job_type: 'generate_motion_ai_video',
        provider: 'mock',
        status: 'completed',
        cost_usd: '0.0000',
        credits_charged: 3,
        input_payload: null,
        output_payload: null,
        error_message: null,
        request_payload: null,
        result_payload: {
          video_title: 'Portable blender motion video',
          concept_summary: 'Prepare a motion video concept.',
          selected_references: {
            visual_references: [],
            audio_tracks: [],
          },
          generated_video: {
            status: 'placeholder',
            message: 'Real AI video generation will be connected later.',
            provider: 'mock',
          },
          scene_plan: [],
          motion_prompt: 'Motion prompt',
          negative_prompt: 'exaggerated claims',
          camera_motion_notes: ['Use smooth movement.'],
          editing_plan: ['Cut quickly.'],
          audio_sync_notes: ['No audio attached.'],
          platform_usage_notes: ['Use for TikTok planning.'],
          quality_checklist: ['References are attached.'],
          recommended_asset_improvements: ['Upload a product image.'],
          compliance_notes: ['No real video generated yet.'],
          api_ready_payload_preview: {
            provider: 'mock',
            duration_seconds: 15,
            aspect_ratio: 'vertical 9 16',
            prompt: 'Motion prompt',
            negative_prompt: 'exaggerated claims',
            references: { visual_references: [], audio_tracks: [] },
          },
        },
        started_at: '2026-07-07T00:00:00.000000Z',
        finished_at: '2026-07-07T00:00:00.000000Z',
        created_at: '2026-07-07T00:00:00.000000Z',
      },
      meta: { credits_balance: 17 },
    }

    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(response), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchMock)

    await expect(generateMotionVideo(123)).resolves.toEqual(response)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/123/generate-motion-video',
      expect.objectContaining({ method: 'POST', body: '{}' }),
    )
  })
})
