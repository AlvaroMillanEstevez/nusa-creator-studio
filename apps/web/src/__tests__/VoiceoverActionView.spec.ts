import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { CreatorGeneration, CreatorProject, ProjectActionsResponse } from '@/types/project'
import VoiceoverActionView from '@/views/VoiceoverActionView.vue'

const project: CreatorProject = {
  id: 42,
  user_id: 1,
  title: 'Creator desk launch',
  type: 'workspace',
  status: 'draft',
  input_payload: {
    product_name: 'Creator desk light',
    content_idea: 'Compact creator desk lighting kit',
    product_description: 'A compact desk light for mobile-first creators.',
    target_audience: 'TikTok affiliate creators',
    language: 'english',
    tone: 'natural',
    platform: 'tiktok',
    notes: null,
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-09T01:00:00.000000Z',
  updated_at: '2026-07-09T01:00:00.000000Z',
}

const sourceGeneration: CreatorGeneration = {
  id: 9,
  user_id: 1,
  project_id: 42,
  job_type: 'generate_affiliate_campaign',
  provider: 'mock',
  status: 'completed',
  cost_usd: '0.0000',
  credits_charged: 1,
  input_payload: { language: 'spanish' },
  output_payload: {
    hooks: ['Hook'],
    voiceover_ready_script: 'Voiceover-ready script from the selected campaign.',
    script: { title: 'Campaign script', duration_seconds: 30, scenes: [] },
    caption: 'Caption',
    hashtags: ['#Creator'],
    cta: 'Review details.',
    notes: ['Use real footage.'],
  },
  error_message: null,
  request_payload: null,
  result_payload: null,
  started_at: null,
  finished_at: null,
  created_at: '2026-07-08T01:00:00.000000Z',
}

const actions: ProjectActionsResponse = {
  data: [
    {
      key: 'generate_voiceover_from_campaign',
      title: 'Create voiceover',
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
      recommended_sources: [
        {
          generation_id: 9,
          job_type: 'generate_affiliate_campaign',
          title: 'Campaign script source',
          created_at: '2026-07-08T01:00:00.000000Z',
          excerpt: 'Voiceover-ready script from the selected campaign.',
        },
      ],
      recommended_assets: [],
      missing_requirements_count: 0,
      required_source: 'source_generation',
      source_generation_id: 9,
      reason: null,
      action_endpoint: '/api/v1/projects/42/generate-voiceover',
      tool_key: 'ai_voiceover',
      tool_status: 'available',
    },
  ],
}

const noSourceActions: ProjectActionsResponse = {
  data: [
    {
      ...actions.data[0]!,
      recommended_sources: [],
      source_generation_id: null,
    },
  ],
}

function completedVoiceover(inputPayload: Record<string, unknown>): CreatorGeneration {
  return {
    id: 30,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_ai_voiceover',
    provider: 'elevenlabs',
    status: 'completed',
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: inputPayload,
    output_payload: {
      voiceover_title: 'Campaign voiceover',
      narration_script: 'Voiceover-ready script from the selected campaign.',
      source_generation_id: typeof inputPayload.source_generation_id === 'number' ? inputPayload.source_generation_id : null,
      source_generation_excerpt: null,
      audio_asset_id: 99,
      scene_timing: [],
      voice_direction: 'Natural creator delivery.',
      pronunciation_notes: [],
      pacing_notes: [],
      platform_notes: [],
      estimated_duration_seconds: 8,
      suggested_audio_format: 'mp3',
      usage_notes: [],
      audio: {
        status: 'generated',
        media_asset_id: 99,
        media_asset_name: 'voiceover-99.mp3',
        stream_url: '/api/v1/media-assets/99/stream',
        download_url: '/api/v1/media-assets/99/download',
        mime_type: 'audio/mpeg',
        format: 'mp3',
        provider: 'elevenlabs',
        provider_model: 'eleven_flash_v2_5',
        provider_voice_id: 'voice_test',
        character_count: 54,
        estimated_cost_usd: null,
        duration_seconds: 8,
      },
      compliance_notes: [],
    },
    error_message: null,
    request_payload: null,
    result_payload: null,
    started_at: null,
    finished_at: null,
    created_at: '2026-07-09T02:00:00.000000Z',
  }
}

async function mountVoiceoverAction(
  path = '/projects/42/actions/voiceover?source_generation_id=9&return_to=/projects/42/generations/9',
  actionResponse: ProjectActionsResponse = actions,
) {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42')) {
      return Response.json({ data: project })
    }

    if (url.endsWith('/projects/42/actions')) {
      return Response.json(actionResponse)
    }

    if (url.endsWith('/generations/9')) {
      return Response.json({ data: sourceGeneration })
    }

    if (url.endsWith('/projects/42/generate-voiceover') && method === 'POST') {
      return Response.json({
        data: completedVoiceover(JSON.parse(String(init?.body))),
        meta: { credits_balance: 19 },
      }, { status: 201 })
    }

    if (url.endsWith('/media-assets/99/stream')) {
      return new Response(new Blob(['audio-bytes'], { type: 'audio/mpeg' }), { status: 200 })
    }

    return Response.json({ data: [] }, { status: 404 })
  })
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: vi.fn<() => string>(() => 'blob:nusa-audio'),
  })
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: vi.fn<() => void>(),
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/media', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
      { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: VoiceoverActionView },
    ],
  })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(VoiceoverActionView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return { wrapper, router, fetchMock }
}

describe('VoiceoverActionView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the focused create voiceover setup with selected source', async () => {
    const { wrapper } = await mountVoiceoverAction()

    expect(wrapper.get('h1').text()).toBe('Create voiceover')
    expect(wrapper.text()).toContain('Generate AI narration from a saved campaign script or custom text.')
    expect(wrapper.text()).toContain('Select source')
    expect(wrapper.text()).toContain('Use saved generation')
    expect(wrapper.text()).toContain('Campaign script source · Creator desk light · Jul 8')
    expect(wrapper.find<HTMLSelectElement>('[data-testid="voiceover-source-select"]').element.value).toBe('9')
    expect(wrapper.get<HTMLSelectElement>('[data-testid="voiceover-language-select"]').element.disabled).toBe(true)
    expect(wrapper.get<HTMLSelectElement>('[data-testid="voiceover-language-select"]').element.value).toBe('spanish')
    expect(wrapper.get('[data-testid="voiceover-language-helper"]').text()).toBe('Voiceover language follows the selected campaign script.')
    expect(wrapper.text()).toContain('Cost and access')
    expect(wrapper.text()).toContain('Included')
    expect(wrapper.text()).toContain('1 credit')
    expect(wrapper.text()).not.toContain('Advanced workflow')
    expect(wrapper.find('a[href="/projects/42/generations/9"]').text()).toContain('Back to campaign result')
  })

  it('validates a missing source when no saved source is available', async () => {
    const { wrapper } = await mountVoiceoverAction('/projects/42/actions/voiceover', noSourceActions)

    expect(wrapper.text()).toContain('Select a source or write custom text.')
    expect(wrapper.get<HTMLButtonElement>('[data-testid="voiceover-action-submit"]').element.disabled).toBe(true)
  })

  it('submits a selected source generation and shows compact audio success', async () => {
    const { wrapper, fetchMock } = await mountVoiceoverAction()

    await wrapper.get('[data-testid="voiceover-action-submit"]').trigger('click')
    await flushPromises()

    const voiceoverCall = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/projects/42/generate-voiceover'))
    expect(voiceoverCall).toBeTruthy()
    expect(JSON.parse(String(voiceoverCall?.[1]?.body))).toMatchObject({
      source_generation_id: 9,
      language: 'spanish',
      tone: 'natural',
      pace: 'medium',
      style: 'natural_creator',
    })
    expect(wrapper.get('[data-testid="voiceover-action-success"]').text()).toContain('Audio added to project assets')
    expect(wrapper.text()).toContain('voiceover-99.mp3')
    expect(wrapper.find('[data-testid="audio-asset-player"]').attributes('data-compact')).toBe('true')
  })

  it('submits manual source text when manual mode is selected', async () => {
    const { wrapper, fetchMock } = await mountVoiceoverAction('/projects/42/actions/voiceover', noSourceActions)

    expect(wrapper.get<HTMLSelectElement>('[data-testid="voiceover-language-select"]').element.disabled).toBe(false)
    expect(wrapper.get('[data-testid="voiceover-language-helper"]').text()).toBe('Manual text will be spoken as written. Translation is not applied automatically yet.')
    await wrapper.get('[data-testid="voiceover-source-text"]').setValue('Manual narration for this product campaign.')
    await wrapper.get('[data-testid="voiceover-action-submit"]').trigger('click')
    await flushPromises()

    const voiceoverCall = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/projects/42/generate-voiceover'))
    expect(voiceoverCall).toBeTruthy()
    expect(JSON.parse(String(voiceoverCall?.[1]?.body))).toMatchObject({
      source_text: 'Manual narration for this product campaign.',
    })
  })
})