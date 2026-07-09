import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import type { CampaignGenerationResult as CampaignGenerationResultPayload, ProjectAction } from '@/types/project'

const legacyMockResult: CampaignGenerationResultPayload = {
  hooks: ['Before you scroll, look at this product.'],
  script: {
    title: 'Portable blender demo',
    duration_seconds: 30,
    scenes: [
      {
        timestamp: '0-3s',
        voiceover: 'Meet the portable blender.',
        visual_direction: 'Show a clean product close-up.',
        on_screen_text: 'Meet the blender',
      },
    ],
  },
  caption: 'A quick product idea for creators.',
  hashtags: ['#TikTokShop', '#CreatorTips'],
  cta: 'Check the product details.',
  notes: ['Use real footage.'],
}

const structuredResult: CampaignGenerationResultPayload = {
  campaign_title: 'Honest mini camera campaign',
  product_angle: 'Show the camera as a practical creator tool without exaggerated claims.',
  target_audience_summary: 'Small creators who need a simple product filming setup.',
  hooks: [
    {
      label: 'Hook 1',
      text: '¿Quieres crear contenido rápido?',
      intent: 'Open with the creator problem.',
    },
  ],
  main_script: {
    duration_seconds: 30,
    voiceover_script: 'Voiceover script from the provider.',
    on_screen_text: ['Review details'],
    scenes: [
      {
        time_range: '0-5s',
        voiceover: 'Start with the product on the desk.',
        visual_direction: 'Show a real desk setup.',
        on_screen_text: 'Practical setup',
      },
    ],
  },
  voiceover_ready_script: 'Copy this clean voiceover script.',
  captions: ['Diseñado para creadores que necesitan automatización.'],
  hashtags: ['#Creadores', '#TikTokShop', '#Producto'],
  cta_options: ['Review the product details before deciding.'],
  compliance_notes: ['Do not imply guaranteed results.'],
  usage_notes: ['Use real product footage.'],
  provider_metadata: {
    provider: 'openai',
    model: 'gpt-5.4-nano',
    estimated_cost_usd: 0.0002,
  },
}

const voiceoverAction: ProjectAction = {
  key: 'generate_voiceover_from_campaign',
  title: 'Create voiceover from this script',
  description: 'Generate voiceover from selected campaign.',
  service_tier: 'advanced',
  output_type: 'audio',
  status: 'configure',
  credit_cost: 1,
  user_has_access: true,
  upgrade_required: false,
  access_label: 'Included',
  locked_reason: null,
  action_mode: 'configure',
  primary_cta: { label: 'Create voiceover', route: null, action: 'generate_voiceover_from_campaign' },
  requirements: [],
  recommended_sources: [],
  recommended_assets: [],
  missing_requirements_count: 0,
  required_source: null,
  source_generation_id: 901,
  reason: null,
  action_endpoint: '/api/v1/projects/77/generate-voiceover',
  tool_key: 'ai_voiceover',
  tool_status: 'available',
}

function mountCampaignResult(props: Record<string, unknown> = {}) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
    ],
  })

  return mount(CampaignGenerationResult, {
    props: {
      result: structuredResult,
      ...props,
    },
    global: { plugins: [createPinia(), router] },
  })
}

describe('CampaignGenerationResult', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('renders legacy mock campaign output', () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', component: { template: '<div />' } }],
    })
    const wrapper = mount(CampaignGenerationResult, {
      props: { result: legacyMockResult },
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.text()).toContain('Before you scroll, look at this product.')
    expect(wrapper.text()).toContain('Portable blender demo')
    expect(wrapper.text()).toContain('A quick product idea for creators.')
    expect(wrapper.text()).toContain('Check the product details.')
    expect(wrapper.text()).toContain('Meet the portable blender.')
  })

  it('renders structured provider output with editable narration voice', async () => {
    const writeText = vi.fn<(text: string) => Promise<void>>().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    })

    const wrapper = mountCampaignResult()

    expect(wrapper.text()).toContain('Honest mini camera campaign')
    expect(wrapper.text()).toContain('¿Quieres crear contenido rápido?')
    expect(wrapper.text()).toContain('Narration voice')
    expect(wrapper.text()).toContain('Edit narration before generating voiceover')
    expect(wrapper.find<HTMLTextAreaElement>('[data-testid="narration-voice-textarea"]').element.value).toBe(
      'Copy this clean voiceover script.',
    )
    expect(wrapper.text()).not.toContain('Voiceover-ready script')
    expect(wrapper.text()).not.toContain('Copy into voiceover next')
    expect(wrapper.text()).not.toContain('Use this script as source when ready')
    expect(wrapper.text()).toContain('Diseñado para creadores que necesitan automatización.')
    expect(wrapper.text()).toContain('Do not imply guaranteed results.')
    expect(wrapper.text()).toContain('openai')
    expect(wrapper.text()).toContain('gpt-5.4-nano')

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledWith('Copy this clean voiceover script.')
    expect(wrapper.text()).toContain('Copied')
  })

  it('sends edited narration text when generating AI voiceover and renders compact audio preview', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/projects/77/generate-voiceover')) {
        return Response.json({
          data: {
            id: 990,
            user_id: 1,
            project_id: 77,
            job_type: 'generate_ai_voiceover',
            provider: 'elevenlabs',
            status: 'completed',
            cost_usd: '0.0000',
            credits_charged: 1,
            input_payload: JSON.parse(String(init?.body)),
            output_payload: {
              voiceover_title: 'Edited campaign voiceover',
              narration_script: 'Edited narration for voiceover.',
              source_generation_id: 901,
              source_generation_excerpt: 'Edited narration for voiceover.',
              audio_asset_id: 42,
              scene_timing: [],
              voice_direction: 'Natural',
              pronunciation_notes: [],
              pacing_notes: [],
              platform_notes: [],
              estimated_duration_seconds: 8,
              suggested_audio_format: 'mp3',
              usage_notes: [],
              audio: {
                status: 'generated',
                media_asset_id: 42,
                media_asset_name: 'voiceover-42.mp3',
                stream_url: '/api/v1/media-assets/42/stream',
                download_url: '/api/v1/media-assets/42/download',
                mime_type: 'audio/mpeg',
                format: 'mp3',
                provider: 'elevenlabs',
                provider_model: 'eleven_flash_v2_5',
                provider_voice_id: 'voice_test',
                character_count: 32,
                estimated_cost_usd: null,
                duration_seconds: 8,
              },
              compliance_notes: [],
            },
            error_message: null,
            started_at: null,
            finished_at: null,
            created_at: '2026-07-09T02:00:00.000000Z',
          },
          meta: { credits_balance: 19 },
        }, { status: 201 })
      }

      if (url.endsWith('/media-assets/42/stream')) {
        return new Response(new Blob(['audio-bytes'], { type: 'audio/mpeg' }), { status: 200 })
      }

      return Response.json({ data: [] })
    })

    vi.stubGlobal('fetch', fetchMock)
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn<() => string>(() => 'blob:nusa-audio'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn<() => void>(),
    })

    const wrapper = mountCampaignResult({
      projectId: 77,
      generationId: 901,
      voiceoverAction,
    })

    await wrapper.get('[data-testid="narration-voice-textarea"]').setValue('Edited narration for voiceover.')
    await wrapper.get('[data-testid="generate-narration-voiceover"]').trigger('click')
    await flushPromises()

    const voiceoverCall = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/projects/77/generate-voiceover'))
    expect(voiceoverCall).toBeTruthy()
    expect(JSON.parse(String(voiceoverCall?.[1]?.body))).toMatchObject({
      source_generation_id: 901,
      source_text: 'Edited narration for voiceover.',
    })
    expect(wrapper.text()).toContain('Audio added to project assets: voiceover-42.mp3')
    expect(wrapper.find('[data-testid="campaign-voiceover-preview"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="audio-asset-player"]').attributes('data-compact')).toBe('true')
    expect(wrapper.text()).not.toContain('/api/v1/media-assets/42/stream')
    expect(wrapper.text()).not.toContain('/api/v1/media-assets/42/download')
  })
})