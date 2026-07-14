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
    campaign_angle: 'Turn the Pocket Light into a practical creator desk demo.',
    hooks: ['Start with motion.'],
    narration_voice_script: 'Show the product in use first.',
    captions: ['Pocket light demo'],
    hashtags: ['#CreatorTools'],
    cta_options: ['Check the details.'],
    edit_plan: {
      title: 'Vertical edit plan',
      format: 'vertical_9_16',
      platform: 'tiktok',
      target_duration_seconds: 15,
      editing_rhythm: 'medium',
      editing_style: 'ugc_natural',
      source_video_name: 'raw-product-demo.mp4',
      timeline: [{ start_seconds: 0, end_seconds: 3, instruction: 'Open on the clearest product shot.', caption: 'Pocket light demo' }],
    },
    detected_product_moments: [{ start_seconds: 1, end_seconds: 3, moment: 'Stable product close-up', product_visibility: 'high', stability: 'high', visual_quality: 'high', use_for_final_edit: true }],
    scene_observations: [],
    recommended_cuts: [{ start_seconds: 0, end_seconds: 3, reason: 'Open with product clarity.', visual_reason: 'The product is centered.' }],
    compliance_notes: [],
    usage_notes: [],
    source_video_asset_id: 88,
    campaign_input_mode: 'video_analysis',
    has_source_video: true,
    has_edit_plan: true,
    auto_video_editor_eligible: true,
    provider_metadata: {
      provider: 'mock',
      campaign_input_mode: 'video_analysis',
      has_source_video: true,
      has_edit_plan: true,
      auto_video_editor_eligible: true,
    },
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
    mock_audio: {
      status: 'placeholder',
      message: 'Mock voiceover audio placeholder.',
      provider: 'mock',
    },
    usage_notes: [],
    compliance_notes: [],
  },
}

const alternateVideoCampaignGeneration: CreatorGeneration = {
  ...videoCampaignGeneration,
  id: 32,
  created_at: '2026-07-09T02:00:00.000000Z',
  result_payload: {
    video_summary: 'Alternate source analysis.',
    campaign_angle: 'A different campaign angle.',
    hooks: ['Start with another angle.'],
    narration_voice_script: 'Show the product from a different angle.',
    captions: ['Another creator angle'],
    hashtags: ['#CreatorTools'],
    cta_options: ['Buy only if it fits your setup.'],
    edit_plan: {
      title: 'Alternate vertical edit plan',
      format: 'vertical_9_16',
      platform: 'tiktok',
      target_duration_seconds: 30,
      source_video_name: 'raw-product-demo.mp4',
      timeline: [{ start_seconds: 0, end_seconds: 3, instruction: 'Open on the clearest product shot.', caption: 'Pocket light demo' }],
    },
    detected_product_moments: [],
    scene_observations: [],
    recommended_cuts: [{ start_seconds: 0, end_seconds: 3, reason: 'Open with product clarity.', visual_reason: 'The product is centered.' }],
    compliance_notes: [],
    usage_notes: [],
    source_video_asset_id: 88,
    campaign_input_mode: 'video_analysis',
    has_source_video: true,
    has_edit_plan: true,
    auto_video_editor_eligible: true,
    provider_metadata: {
      provider: 'mock',
      campaign_input_mode: 'video_analysis',
      has_source_video: true,
      has_edit_plan: true,
      auto_video_editor_eligible: true,
    },
  },
}

const imageCampaignGeneration: CreatorGeneration = {
  ...videoCampaignGeneration,
  id: 33,
  job_type: 'generate_affiliate_campaign',
  credits_charged: 1,
  created_at: '2026-07-10T02:00:00.000000Z',
  result_payload: {
    campaign_title: 'Image-assisted content kit',
    product_angle: 'A useful product positioning kit.',
    target_audience_summary: 'Creators who need caption and script ideas.',
    hooks: ['Use this product image for content positioning.'],
    main_script: { duration_seconds: 30, voiceover_script: 'Show the product honestly.', on_screen_text: [], scenes: [] },
    voiceover_ready_script: 'Show the product honestly.',
    scene_breakdown: [],
    captions: ['Image-assisted caption idea.'],
    hashtags: ['#CreatorTools'],
    cta_options: ['Check the product details.'],
    compliance_notes: [],
    usage_notes: [],
    campaign_input_mode: 'image_assisted',
    has_source_video: false,
    source_video_asset_id: null,
    source_image_asset_id: 90,
    has_edit_plan: false,
    auto_video_editor_eligible: false,
    provider_metadata: {
      provider: 'mock',
      campaign_input_mode: 'image_assisted',
      auto_video_editor_eligible: false,
    },
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

function createFetchMock(
  mediaAssets: MediaAsset[] = [sourceVideo],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, videoCampaignGeneration],
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

async function mountView(
  component: typeof VideoToCampaignActionView | typeof AutoVideoEditorActionView,
  path: string,
  mediaAssets: MediaAsset[] = [sourceVideo],
  generationHistory: CreatorGeneration[] = [voiceoverGeneration, videoCampaignGeneration],
) {
  const router = routerFor()
  const fetchMock = createFetchMock(mediaAssets, generationHistory)
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

  return { wrapper, fetchMock, router }
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

  it('submits Video-to-Campaign with the selected source video and redirects to generation detail', async () => {
    const { wrapper, fetchMock, router } = await mountView(VideoToCampaignActionView, '/projects/42/actions/video-to-campaign')

    expect(wrapper.text()).toContain('← Back to workspace')
    expect(wrapper.text()).not.toContain('? Back to workspace')

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-video-campaign'))
    const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

    expect(body.source_video_asset_id).toBe(88)
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/101')
    expect(wrapper.find('[data-testid="video-to-campaign-success"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('View generated campaign')
  })

  it('renders Auto Video Editor source, voiceover and cost controls', async () => {
    const { wrapper } = await mountView(AutoVideoEditorActionView, '/projects/42/actions/auto-video-editor', [sourceVideo, voiceoverAudio])

    expect(wrapper.text()).toContain('Auto Video Editor')
    expect(wrapper.text()).toContain('← Back to workspace')
    expect(wrapper.text()).not.toContain('? Back to workspace')
    expect(wrapper.text()).toContain('Use AI video analysis')
    expect(wrapper.text()).toContain('Manual edit setup')
    expect(wrapper.text()).toContain('Reuse saved voiceover generation')
    expect(wrapper.text()).toContain('Cost breakdown')
    expect(wrapper.text()).toContain('Audio cleanup')
    expect(wrapper.text()).toContain('Mute original video audio')
    expect(wrapper.text()).toContain('Beta cleanup controls volume and mixing. Advanced AI denoise will come later.')
    expect(wrapper.text()).toContain('Visual style')
    expect(wrapper.text()).toContain('Product highlights')
    expect(wrapper.get<HTMLSelectElement>('[data-testid="auto-video-overlay-style-select"]').element.value).toBe('product_highlights')
    expect(wrapper.text()).toContain('Highlight product benefits and visible features.')
    expect(wrapper.text()).toContain('Edit polish')
    expect(wrapper.text()).toContain('Snappy social cuts')
    expect(wrapper.text()).toContain('Rendering your video. This can take a few minutes depending on video length and provider processing time.')
    expect(wrapper.text()).toContain('Total credits: 2')
    expect(wrapper.get('[data-testid="auto-video-cta-input"]').element).toHaveProperty('value', 'Check the details.')
    expect(wrapper.get('[data-testid="auto-video-analysis-select"]').text()).toContain('Video campaign · Pocket Light')
    expect(wrapper.get('[data-testid="auto-video-voiceover-select"]').text()).toContain('Voiceover · Pocket Light · 20s')
    const durationSelect = wrapper.get<HTMLSelectElement>('[data-testid="auto-video-duration-select"]')

    expect(durationSelect.element.value).toBe('15')
    expect(durationSelect.text()).toContain('15s · extends to 20s')

    const sourceAudioModes = wrapper.findAll<HTMLInputElement>('[data-testid="auto-video-source-audio-mode"]')
    const muteSource = sourceAudioModes.find((input) => input.element.value === 'mute_source')
    const lowSource = sourceAudioModes.find((input) => input.element.value === 'low_source')

    expect(muteSource?.element.checked).toBe(true)
    expect(lowSource?.element.checked).toBe(false)
    expect(wrapper.get<HTMLSelectElement>('[data-testid="auto-video-voiceover-mix-select"]').element.value).toBe('voiceover_dominant')

    const syncHelp = wrapper.get('[data-testid="auto-video-voiceover-sync-help"]').text()

    expect(syncHelp).toContain('Final video will extend to about 20s to fit the voiceover.')

    await durationSelect.setValue('30')
    await flushPromises()

    expect(wrapper.get('[data-testid="auto-video-voiceover-sync-help"]').text()).toContain('Voiceover is about 20s. Extra video time will be filled with additional product footage and CTA visuals.')

    await wrapper.get('input[value="new"]').setValue(true)
    await flushPromises()

    expect(wrapper.text()).toContain('Total credits: 3')
    expect(wrapper.text()).toContain('For the beta render, generate a voiceover first and select it here.')
  })

  it('submits Auto Video Editor and navigates directly to the generation result', async () => {
    const { wrapper, fetchMock, router } = await mountView(AutoVideoEditorActionView, '/projects/42/actions/auto-video-editor', [sourceVideo, voiceoverAudio])

    await wrapper.findAll<HTMLInputElement>('[data-testid="auto-video-source-audio-mode"]')
      .find((input) => input.element.value === 'product_sounds')
      ?.setValue(true)
    await wrapper.get('[data-testid="auto-video-voiceover-mix-select"]').setValue('balanced')
    await wrapper.get('[data-testid="auto-video-transition-style-select"]').setValue('premium_smooth')
    await wrapper.get('[data-testid="auto-video-motion-polish-select"]').setValue('dynamic_crop')
    await wrapper.get('[data-testid="auto-video-clip-diversity-select"]').setValue('more_unique_shots')
    await wrapper.get('[data-testid="auto-video-end-screen-select"]').setValue('simple_cta')
    await wrapper.get('[data-testid="auto-video-overlay-style-select"]').setValue('premium_brand')

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-auto-video-edit'))
    const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

    expect(body.edit_mode).toBe('ai_video_analysis')
    expect(body.source_video_to_campaign_generation_id).toBe(31)
    expect(body.source_video_asset_id).toBeNull()
    expect(body.target_duration_seconds).toBe(20)
    expect(body.requested_target_duration_seconds).toBe(15)
    expect(body.effective_target_duration_seconds).toBe(20)
    expect(body.source_audio_mode).toBe('product_sounds')
    expect(body.voiceover_mix).toBe('balanced')
    expect(body.transition_style).toBe('premium_smooth')
    expect(body.motion_polish).toBe('dynamic_crop')
    expect(body.clip_diversity).toBe('more_unique_shots')
    expect(body.end_screen).toBe('simple_cta')
    expect(body.overlay_style).toBe('premium_brand')
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/102')
    expect(wrapper.find('[data-testid="auto-video-editor-success"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Edit plan saved')
    expect(wrapper.text()).not.toContain('View edit plan')
  })

  it('sends canonical Auto Video Editor overlay style values', async () => {
    const canonicalStyles = ['minimal_clean', 'ugc_native', 'product_highlights', 'promo_badges', 'premium_brand']

    for (const style of canonicalStyles) {
      const { wrapper, fetchMock } = await mountView(AutoVideoEditorActionView, '/projects/42/actions/auto-video-editor', [sourceVideo, voiceoverAudio])
      const select = wrapper.get<HTMLSelectElement>('[data-testid="auto-video-overlay-style-select"]')

      expect(select.findAll('option').map((option) => option.element.value)).toEqual(canonicalStyles)
      expect(select.text()).toContain('Premium brand')

      await select.setValue(style)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-auto-video-edit'))
      const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

      expect(body.overlay_style).toBe(style)
      wrapper.unmount()
      vi.unstubAllGlobals()
    }
  })
  it('sends canonical Auto Video Editor caption style values', async () => {
    const canonicalStyles = ['clean_subtitles', 'bold_hook', 'minimal', 'premium_clean', 'ugc_native']

    for (const style of canonicalStyles) {
      const { wrapper, fetchMock } = await mountView(AutoVideoEditorActionView, '/projects/42/actions/auto-video-editor', [sourceVideo, voiceoverAudio])
      const select = wrapper.get<HTMLSelectElement>('[data-testid="auto-video-caption-style-select"]')

      expect(select.findAll('option').map((option) => option.element.value)).toEqual(canonicalStyles)
      expect(select.text()).toContain('Bold hook captions')

      await select.setValue(style)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-auto-video-edit'))
      const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

      expect(body.caption_style).toBe(style)
      wrapper.unmount()
      vi.unstubAllGlobals()
    }
  })

  it('only lists eligible video-analysis generations in Auto Video Editor AI mode', async () => {
    const { wrapper } = await mountView(
      AutoVideoEditorActionView,
      '/projects/42/actions/auto-video-editor',
      [sourceVideo, voiceoverAudio],
      [voiceoverGeneration, imageCampaignGeneration, videoCampaignGeneration],
    )

    expect(wrapper.text()).toContain('Use AI video analysis')
    expect(wrapper.text()).toContain('This campaign was created from text or images, so it does not include video cuts.')
    expect(wrapper.get('[data-testid="auto-video-analysis-select"]').text()).toContain('Video campaign · Pocket Light')
    expect(wrapper.get('[data-testid="auto-video-analysis-select"]').text()).not.toContain('Image-assisted content kit')
  })

  it('defaults to manual edit mode when no eligible video analysis exists and submits the manual source video', async () => {
    const { wrapper, fetchMock, router } = await mountView(
      AutoVideoEditorActionView,
      '/projects/42/actions/auto-video-editor',
      [sourceVideo, voiceoverAudio],
      [voiceoverGeneration, imageCampaignGeneration],
    )

    const sourceModes = wrapper.findAll<HTMLInputElement>('[data-testid="auto-video-source-mode"]')
    const manualMode = sourceModes.find((input) => input.element.value === 'manual_edit')

    expect(manualMode?.element.checked).toBe(true)
    expect(wrapper.find('[data-testid="auto-video-analysis-select"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('For best results, generate a Video-to-Campaign Insight first.')
    expect(wrapper.get('[data-testid="auto-video-manual-source-select"]').text()).toContain('raw-product-demo.mp4')

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/42/generate-auto-video-edit'))
    const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>

    expect(body.edit_mode).toBe('manual_edit')
    expect(body.source_video_asset_id).toBe(88)
    expect(body.source_video_to_campaign_generation_id).toBeNull()
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/102')
  })

  it('requires a source video in manual edit mode', async () => {
    const { wrapper, fetchMock } = await mountView(
      AutoVideoEditorActionView,
      '/projects/42/actions/auto-video-editor',
      [],
      [voiceoverGeneration, imageCampaignGeneration],
    )

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Select or upload a source video first.')
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith('/projects/42/generate-auto-video-edit'))).toBe(false)
  })
  it('does not overwrite a manually edited Auto Video Editor CTA when switching analysis', async () => {
    const { wrapper } = await mountView(
      AutoVideoEditorActionView,
      '/projects/42/actions/auto-video-editor',
      [sourceVideo, voiceoverAudio],
      [voiceoverGeneration, videoCampaignGeneration, alternateVideoCampaignGeneration],
    )
    const ctaInput = wrapper.get<HTMLInputElement>('[data-testid="auto-video-cta-input"]')

    await ctaInput.setValue('Manual creator CTA')
    await wrapper.get('[data-testid="auto-video-analysis-select"]').setValue('32')
    await flushPromises()

    expect(ctaInput.element.value).toBe('Manual creator CTA')
  })
})
