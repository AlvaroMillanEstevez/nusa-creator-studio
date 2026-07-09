import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { MediaAsset } from '@/types/media'
import type { CreatorGeneration, CreatorProject, ProjectAction, ProjectActionsResponse } from '@/types/project'
import AutoVideoEditorActionView from '@/views/AutoVideoEditorActionView.vue'
import VideoToCampaignActionView from '@/views/VideoToCampaignActionView.vue'

const project: CreatorProject = {
  id: 42,
  user_id: 1,
  title: 'Portable light workspace',
  type: 'workspace',
  status: 'draft',
  input_payload: {
    product_name: 'Pocket Light',
    content_idea: 'Short product demo campaign',
    product_description: 'A compact creator light.',
    target_audience: 'TikTok affiliate creators',
    language: 'english',
    tone: 'natural',
    platform: 'tiktok',
    notes: null,
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-08T01:00:00.000000Z',
  updated_at: '2026-07-08T01:00:00.000000Z',
}

const sourceVideo: MediaAsset = {
  id: 88,
  user_id: 1,
  project_id: 42,
  type: 'video',
  usage_role: 'campaign_source_video',
  disk: 'local',
  storage_identifier: 'media-88',
  original_name: 'raw-product-demo.mp4',
  mime_type: 'video/mp4',
  size_bytes: 2048,
  size_label: '2.0 KB',
  metadata: null,
  created_at: '2026-07-08T01:00:00.000000Z',
  updated_at: '2026-07-08T01:00:00.000000Z',
}

const voiceoverAudio: MediaAsset = {
  ...sourceVideo,
  id: 89,
  type: 'audio',
  usage_role: 'voiceover_audio',
  storage_identifier: 'media-89',
  original_name: 'voiceover.mp3',
  mime_type: 'audio/mpeg',
}

const videoCampaignGeneration: CreatorGeneration = {
  id: 31,
  user_id: 1,
  project_id: 42,
  job_type: 'generate_video_to_campaign',
  provider: 'mock',
  status: 'completed',
  cost_usd: '0.0000',
  credits_charged: 2,
  input_payload: null,
  output_payload: null,
  error_message: null,
  request_payload: null,
  result_payload: {
    video_summary: 'Useful source analysis.',
    hooks: ['Start with motion.'],
    narration_voice_script: 'Show the product in use first.',
    captions: ['Pocket light demo'],
    hashtags: ['#CreatorTools'],
    cta_options: ['Check the details.'],
    edit_plan: {
      title: 'Vertical edit plan',
      format: 'vertical_9_16',
      platform: 'tiktok',
      source_video_name: 'raw-product-demo.mp4',
      timeline: [],
    },
    detected_product_moments: [],
    scene_observations: [],
    recommended_cuts: [],
    compliance_notes: [],
    usage_notes: [],
    source_video_asset_id: 88,
    provider_metadata: { provider: 'mock' },
  },
  started_at: '2026-07-08T02:00:00.000000Z',
  finished_at: '2026-07-08T02:00:00.000000Z',
  created_at: '2026-07-08T02:00:00.000000Z',
}

const voiceoverGeneration: CreatorGeneration = {
  ...videoCampaignGeneration,
  id: 44,
  job_type: 'generate_ai_voiceover',
  credits_charged: 1,
  result_payload: {
    voiceover_title: 'Pocket Light voiceover',
    narration_script: 'Show the product in use first.',
    source_generation_id: 31,
    source_generation_excerpt: 'Show the product in use first.',
    audio_asset_id: 89,
    scene_timing: [],
    voice_direction: 'Natural creator delivery.',
    pronunciation_notes: [],
    pacing_notes: [],
    platform_notes: [],
    estimated_duration_seconds: 20,
    suggested_audio_format: 'mp3',
    usage_notes: [],
    compliance_notes: [],
  },
}

function action(overrides: Partial<ProjectAction> & Pick<ProjectAction, 'key'>): ProjectAction {
  return {
    title: 'Action',
    description: 'Action description.',
    service_tier: 'advanced',
    output_type: 'assisted_media',
    status: 'configure',
    credit_cost: 1,
    user_has_access: true,
    upgrade_required: false,
    access_label: 'Included in your plan',
    locked_reason: null,
    action_mode: 'configure',
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
    ...overrides,
  }
}

const actions: ProjectActionsResponse = {
  data: [
    action({ key: 'generate_video_to_campaign', title: 'Generate campaign from video', credit_cost: 2, tool_key: 'video_to_campaign' }),
    action({ key: 'generate_auto_video_edit', title: 'Auto Video Editor', credit_cost: 2, service_tier: 'premium', output_type: 'video', tool_key: 'auto_video_editor' }),
    action({ key: 'generate_voiceover_from_script', title: 'Create voiceover', credit_cost: 1, tool_key: 'ai_voiceover' }),
  ],
}

function routerFor() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/projects/:id', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', component: VideoToCampaignActionView },
      { path: '/projects/:projectId/actions/auto-video-editor', component: AutoVideoEditorActionView },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
    ],
  })
}

function createFetchMock(mediaAssets: MediaAsset[] = [sourceVideo]) {
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
      return Response.json({ data: [voiceoverGeneration, videoCampaignGeneration] })
    }

    if (url.endsWith('/projects/42/actions')) {
      return Response.json(actions)
    }

    if (url.endsWith('/projects/42/generate-video-campaign') && method === 'POST') {
      return Response.json({ data: { ...videoCampaignGeneration, id: 101, status: 'queued' } }, { status: 202 })
    }

    if (url.endsWith('/projects/42/generate-auto-video-edit') && method === 'POST') {
      return Response.json({ data: { ...videoCampaignGeneration, id: 102, job_type: 'generate_auto_video_edit', status: 'queued' } }, { status: 202 })
    }

    return Response.json({}, { status: 404 })
  })
}

async function mountView(component: typeof VideoToCampaignActionView | typeof AutoVideoEditorActionView, path: string, mediaAssets: MediaAsset[] = [sourceVideo]) {
  const router = routerFor()
  const fetchMock = createFetchMock(mediaAssets)
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push(path)
  await router.isReady()

  const wrapper = mount(component, {
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return { wrapper, fetchMock }
}

describe('video workflow action views', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders Video-to-Campaign source setup and validates missing source video', async () => {
    const { wrapper } = await mountView(VideoToCampaignActionView, '/projects/42/actions/video-to-campaign', [])

    expect(wrapper.text()).toContain('Generate campaign from video')
    expect(wrapper.text()).toContain('Choose source video')
    expect(wrapper.text()).toContain('No project videos yet. Upload one below to continue.')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Select or upload a source video first.')
  })

  it('submits Video-to-Campaign with the selected source video', async () => {
    const { wrapper, fetchMock } = await mountView(VideoToCampaignActionView, '/projects/42/actions/video-to-campaign')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-video-campaign'))
    const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

    expect(body.source_video_asset_id).toBe(88)
    expect(wrapper.get('[data-testid="video-to-campaign-success"]').text()).toContain('View generated campaign')
  })

  it('renders Auto Video Editor source, voiceover and cost controls', async () => {
    const { wrapper } = await mountView(AutoVideoEditorActionView, '/projects/42/actions/auto-video-editor', [sourceVideo, voiceoverAudio])

    expect(wrapper.text()).toContain('Auto Video Editor')
    expect(wrapper.text()).toContain('Reuse Video-to-Campaign analysis')
    expect(wrapper.text()).toContain('Reuse saved voiceover generation')
    expect(wrapper.text()).toContain('Cost breakdown')
    expect(wrapper.text()).toContain('Total credits: 2')

    await wrapper.get('input[value="new"]').setValue(true)
    await flushPromises()

    expect(wrapper.text()).toContain('Total credits: 3')
  })
})
