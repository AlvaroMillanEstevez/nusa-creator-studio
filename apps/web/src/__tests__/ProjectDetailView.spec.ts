import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectDetailView from '@/views/ProjectDetailView.vue'
import type {
  AiVoiceoverProject,
  CampaignProject,
  CreatorProject,
  FashionTryOnProject,
  MotionVideoProject,
  ProductToVideoProject,
  ProjectActionsResponse,
  ProjectGenerationHistoryResponse,
  VideoEnhancerProject,
} from '@/types/project'

const campaignProject: CampaignProject = {
  id: 42,
  user_id: 1,
  title: 'Campaña para pequeños creadores en España',
  type: 'affiliate_campaign',
  status: 'draft',
  input_payload: {
    product_name: 'Cámara rápida',
    product_description: 'Diseñado para creadores que necesitan automatización',
    target_audience: 'Pequeños creadores en España',
    language: 'spanish',
    tone: 'natural',
  },
  settings: {
    platform: 'tiktok',
    format: 'vertical_9_16',
  },
  created_at: '2026-07-06T00:00:00.000000Z',
  updated_at: '2026-07-06T00:00:00.000000Z',
}

const videoEnhancerProject: VideoEnhancerProject = {
  id: 77,
  user_id: 1,
  title: 'Morning desk setup',
  type: 'video_enhancer',
  status: 'draft',
  input_payload: {
    video_title: 'Morning desk setup',
    product_name: 'Portable key light',
    video_context: 'A creator shows the desk before and after turning on the key light.',
    spoken_summary: 'The creator explains how the light fits a small filming space.',
    target_audience: 'Creators filming in small rooms',
    language: 'english',
    style: 'educational',
  },
  settings: {
    platform: 'tiktok',
    format: 'vertical_9_16',
  },
  created_at: '2026-07-06T02:00:00.000000Z',
  updated_at: '2026-07-06T02:00:00.000000Z',
}

const productVideoProject: ProductToVideoProject = {
  id: 88,
  user_id: 1,
  title: 'Portable key light',
  type: 'product_to_video',
  status: 'draft',
  input_payload: {
    product_name: 'Portable key light',
    product_description: 'A small light for creator desk setups.',
    key_benefits: 'Portable, adjustable and easy to set up.',
    target_audience: 'Creators filming in small rooms',
    video_goal: 'product_demo',
    video_style: 'storytelling',
    duration_seconds: 45,
    language: 'english',
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-06T04:00:00.000000Z',
  updated_at: '2026-07-06T04:00:00.000000Z',
}


const voiceoverProject: AiVoiceoverProject = {
  id: 91,
  user_id: 1,
  title: 'Spanish creator voiceover',
  type: 'ai_voiceover',
  status: 'draft',
  input_payload: {
    title: 'Spanish creator voiceover',
    source_text: '¿Quieres crear contenido rápido?',
    product_name: 'Cámara Pequeña Ñandú',
    product_description: 'Diseñada para creadores que necesitan automatización',
    target_audience: 'Pequeños creadores en España',
    language: 'spanish',
    voice_style: 'premium_brand',
    tone: 'professional',
    pace: 'fast',
    duration_seconds: 45,
    platform: 'tiktok',
    notes: 'Keep pronunciation clear.',
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-06T06:00:00.000000Z',
  updated_at: '2026-07-06T06:00:00.000000Z',
}

const fashionTryOnProject: FashionTryOnProject = {
  id: 99,
  user_id: 1,
  title: 'Streetwear jacket try-on',
  type: 'fashion_try_on',
  status: 'draft',
  input_payload: {
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
    notes: 'Keep it safe and generic.',
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-07T00:00:00.000000Z',
  updated_at: '2026-07-07T00:00:00.000000Z',
}

const motionVideoProject: MotionVideoProject = {
  id: 123,
  user_id: 1,
  title: 'Portable blender motion video',
  type: 'motion_ai_video',
  status: 'draft',
  input_payload: {
    title: 'Portable blender motion video',
    product_name: 'Portable smoothie blender',
    product_description: 'A compact blender for quick creator product demos.',
    video_goal: 'product_showcase',
    motion_style: 'slow_push_in',
    visual_style: 'realistic_product',
    duration_seconds: 15,
    aspect_ratio: 'vertical_9_16',
    target_audience: 'TikTok affiliate creators',
    platform: 'tiktok',
    language: 'english',
    brand_notes: 'Keep it clean and premium.',
    scene_notes: 'Open with a close product shot.',
    audio_notes: 'Use upbeat pacing later.',
    notes: 'No unrealistic claims.',
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-07T02:00:00.000000Z',
  updated_at: '2026-07-07T02:00:00.000000Z',
}
const emptyHistory: ProjectGenerationHistoryResponse = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
}

const defaultActions: ProjectActionsResponse = {
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
      title: 'Create voiceover',
      description: 'Generate an AI voiceover from this script.',
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
      title: 'Motion AI Video',
      description: 'Generate premium AI product footage from a description, a saved campaign or a product image.',
      service_tier: 'premium',
      output_type: 'video',
      status: 'configure',
      required_source: 'media_asset',
      source_generation_id: null,
      reason: 'Open setup to choose text, campaign-assisted or product image source mode.',
      action_endpoint: '/api/v1/projects/42/generate-motion-ai-video',
      tool_key: 'motion_ai_video',
      tool_status: 'available',
      credit_cost: 3,
    },
    {
      key: 'avatar_presenter',
      title: 'Avatar Presenter',
      description: 'Avatar presenter generation is not implemented yet.',
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

async function mountProjectDetail(
  currentProject: CreatorProject,
  history: ProjectGenerationHistoryResponse,
) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
      { path: '/product-to-video/new', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/video-enhancer/new', component: { template: '<div />' } },
      { path: '/voiceover/new', component: { template: '<div />' } },
      { path: '/fashion-try-on/new', component: { template: '<div />' } },
      { path: '/motion-video/new', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/auto-video-editor', name: 'project-auto-video-editor-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
      const body = url.endsWith('/actions')
        ? defaultActions
        : url.endsWith('/generations')
          ? history
          : url.endsWith('/media-assets')
            ? { data: [] }
            : { data: currentProject }

      return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
      })
    }),
  )

  await router.push('/projects/' + currentProject.id)
  await router.isReady()

  const wrapper = mount(ProjectDetailView, {
    global: {
      plugins: [createPinia(), router],
    },
  })

  await flushPromises()

  return wrapper
}

async function mountUnavailableProject(status: 403 | 404) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: status === 403 ? 'Forbidden' : 'Not Found' }), {
        status,
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
      }),
    ),
  )

  await router.push('/projects/999')
  await router.isReady()

  const wrapper = mount(ProjectDetailView, {
    global: {
      plugins: [createPinia(), router],
    },
  })

  await flushPromises()

  return wrapper
}

describe('ProjectDetailView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it.each([403, 404] as const)(
    'renders the friendly unavailable state for an API %s response',
    async (status) => {
      const wrapper = await mountUnavailableProject(status)

      expect(wrapper.get('h1').text()).toBe('Project unavailable')
      expect(wrapper.text()).toContain(
        'This project does not exist or you do not have access to it.',
      )
      expect(
        wrapper
          .findAll('a[href="/dashboard"]')
          .some((link) => link.text().includes('Back to dashboard')),
      ).toBe(true)
      expect(
        wrapper.findAll('a[href="/tools"]').some((link) => link.text().includes('Browse tools')),
      ).toBe(true)
      expect(wrapper.text()).not.toContain('This page needs another try.')
    },
  )

  it('renders Spanish campaign project and generation text without losing Unicode', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
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
            hooks: ['¿Quieres crear contenido rápido?'],
            script: {
              title: 'Campaña rápida para TikTok',
              duration_seconds: 30,
              scenes: [],
            },
            caption: 'Campaña para pequeños creadores en España',
            hashtags: ['#Creadores'],
            cta: '¡Mira los detalles!',
            notes: ['Diseñado para creadores que necesitan automatización'],
          },
          started_at: '2026-07-06T01:00:00.000000Z',
          finished_at: '2026-07-06T01:00:00.000000Z',
          created_at: '2026-07-06T01:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(campaignProject, history)
    const renderedText = wrapper.text()

    expect(renderedText).toContain('¿Quieres crear contenido rápido?')
    expect(renderedText).toContain('Campaña para pequeños creadores en España')
    expect(renderedText).toContain('Diseñado para creadores que necesitan automatización')
    expect(renderedText).not.toContain('Continue with selected result')
    expect(renderedText).toContain('Create Motion AI Video')
    expect(wrapper.find('[data-testid="project-action-hub"]').exists()).toBe(true)
    expect(renderedText).not.toMatch(/Ã¡|Ã±|Â¿|Â¡/)
  })

  it('explains what generating the first saved campaign will create', async () => {
    const wrapper = await mountProjectDetail(campaignProject, emptyHistory)

    expect(wrapper.get('[data-testid="project-assets"]').text()).toContain('Project assets')
    expect(wrapper.text()).toContain(
      'No saved generations yet. Generate your first campaign to create hooks, script, caption and hashtags.',
    )
  })

  it('renders a video enhancer brief and restores its saved result', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 12,
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
            improved_caption: 'A clearer caption for the portable key light.',
            subtitle_plan: [{ timestamp: '0-3s', text: 'Meet the portable key light' }],
            cut_plan: [
              {
                timestamp: '0-3s',
                edit_direction: 'Open on the illuminated desk.',
                reason: 'Show the context immediately.',
              },
            ],
            on_screen_text: ['Small desk lighting'],
            posting_text: 'A practical desk-lighting edit plan.',
            hashtags: ['#PortableKeyLight'],
            cta: 'Review the product details.',
            notes: ['Match subtitle timing to the real recording.'],
          },
          started_at: '2026-07-06T03:00:00.000000Z',
          finished_at: '2026-07-06T03:00:00.000000Z',
          created_at: '2026-07-06T03:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(videoEnhancerProject, history)
    const renderedText = wrapper.text()

    expect(renderedText).toContain('Recorded video brief')
    expect(renderedText).toContain(
      'A creator shows the desk before and after turning on the key light.',
    )
    expect(renderedText).toContain('The creator explains how the light fits a small filming space.')
    expect(renderedText).toContain('Generate caption pack')
    expect(renderedText).toContain('Improved caption')
    expect(renderedText).toContain('A clearer caption for the portable key light.')
    expect(renderedText).toContain('Subtitle plan')
    expect(renderedText).toContain('Cut plan')
    expect(renderedText).toContain('On-screen text')
    expect(renderedText).toContain('Posting text')
  })
  it('renders a product-to-video brief and restores its script pack', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 15,
          user_id: 1,
          project_id: 88,
          job_type: 'generate_product_video_script',
          provider: 'mock',
          status: 'completed',
          cost_usd: '0.0000',
          credits_charged: 1,
          input_payload: null,
          output_payload: null,
          error_message: null,
          request_payload: null,
          result_payload: {
            concept_title: 'Portable key light production plan',
            video_angle: 'A practical product demo angle.',
            storyboard: [
              {
                scene_number: 1,
                timestamp: '0-3s',
                scene_goal: 'Stop the scroll',
                visual_direction: 'Open on the product.',
                voiceover: 'Meet the portable key light.',
                on_screen_text: 'Portable lighting',
              },
            ],
            shot_list: ['Vertical hero shot'],
            asset_checklist: ['Product and accessories'],
            caption: 'A practical product video plan.',
            hashtags: ['#PortableKeyLight'],
            cta: 'Review the details.',
            editing_notes: ['Keep the cut concise.'],
            compliance_notes: ['This is a production plan, not a rendered video.'],
          },
          started_at: '2026-07-06T05:00:00.000000Z',
          finished_at: '2026-07-06T05:00:00.000000Z',
          created_at: '2026-07-06T05:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(productVideoProject, history)
    const text = wrapper.text()

    expect(text).toContain('Product video brief')
    expect(text).toContain('Portable, adjustable and easy to set up.')
    expect(text).toContain('Generate video script pack')
    expect(text).toContain('Concept title')
    expect(text).toContain('Storyboard')
    expect(text).toContain('Shot list')
    expect(text).toContain('Asset checklist')
    expect(text).toContain('Compliance notes')
  })
  it('renders an AI Voiceover brief and restores its saved narration result', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 21,
          user_id: 1,
          project_id: 91,
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
            voiceover_title: 'Spanish creator voiceover: plan de voz en off',
            narration_script:
              'Antes de seguir, mira de cerca Cámara Pequeña Ñandú. ¿Encaja con tu rutina? ¡Sin presión!',
            scene_timing: [
              {
                timestamp: '0-3s',
                section: 'Gancho',
                narration_focus: 'Abre con el producto.',
                delivery_note: 'Entrada cálida y directa.',
              },
            ],
            voice_direction: 'Usa una voz premium brand con tono professional.',
            pronunciation_notes: ['Pronuncia Cámara Pequeña Ñandú con claridad.'],
            pacing_notes: ['Apunta a unos 45 segundos.'],
            platform_notes: ['Mezcla la narración debajo del vídeo vertical.'],
            estimated_duration_seconds: 45,
            suggested_audio_format: 'MP3 or WAV, 44.1 kHz.',
            usage_notes: ['Este resultado mock es solo un plan de narración.'],
            mock_audio: {
              status: 'placeholder',
              message: 'Real audio generation will be connected later.',
              provider: 'mock',
            },
            compliance_notes: ['Evita afirmaciones exageradas.'],
          },
          started_at: '2026-07-06T07:00:00.000000Z',
          finished_at: '2026-07-06T07:00:00.000000Z',
          created_at: '2026-07-06T07:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(voiceoverProject, history)
    const text = wrapper.text()

    expect(text).toContain('Voiceover brief')
    expect(text).toContain('Spanish creator voiceover')
    expect(text).toContain('¿Quieres crear contenido rápido?')
    expect(text).toContain('Generate another voiceover')
    expect(text).toContain('Narration script')
    expect(text).toContain('Scene timing')
    expect(text).toContain('Mock audio placeholder')
    expect(text).toContain('Real audio generation will be connected later.')
    expect(text).not.toMatch(/Ã¡|Ã±|Â¿|Â¡/)
  })

  it('renders a Fashion Try-On brief and restored result without page-level missing asset warnings', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 41,
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
            model_reference_summary: 'Use model-reference.jpg as the model/person reference.',
            garment_reference_summary: 'Use garment-reference.jpg as the garment/product reference.',
            generated_image: {
              status: 'placeholder',
              message: 'Real try-on image generation will be connected later.',
              provider: 'mock',
            },
            composition_plan: ['Vertical composition for TikTok.'],
            pose_and_framing_notes: ['Relaxed pose.'],
            garment_fit_notes: ['Keep fit believable.'],
            background_and_lighting_notes: ['Soft light.'],
            platform_usage_notes: ['Use as a planning placeholder.'],
            quality_checklist: ['References are image assets.'],
            api_ready_prompt: 'Vertical fashion try-on mockup.',
            negative_prompt: 'distorted body, changed garment design',
            recommended_assets: ['Full-body model image', 'Clear garment image'],
            compliance_notes: ['No real image generated yet.'],
          },
          started_at: '2026-07-07T01:00:00.000000Z',
          finished_at: '2026-07-07T01:00:00.000000Z',
          created_at: '2026-07-07T01:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(fashionTryOnProject, history)
    const text = wrapper.text()

    expect(text).toContain('Fashion try-on brief')
    expect(text).toContain('Everyday creator jacket')
    expect(text).not.toContain('Add a model reference image and garment reference image before generating.')
    expect(text).toContain('Generate another version')
    expect(text).toContain('Try-on preparation complete')
    expect(text).toContain('No fashion image was generated in mock mode')
    expect(text).toContain('Quality checklist')
  })


  it('renders a Motion AI Video brief and restored result without page-level missing asset warnings', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 55,
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
            motion_summary: {
              platform: 'tiktok',
              aspect_ratio: 'vertical_9_16',
              duration: 15,
              visual_style: 'realistic_product',
              motion_style: 'slow_push_in',
              source_type: 'product_image_only',
              creative_angle: 'Prepare a 15-second motion video concept.',
              target_audience: 'TikTok affiliate creators',
              primary_cta: 'See product details',
              render_available: false,
            },
            generation_prompt: {
              main_prompt: 'Vertical product video prompt.',
              product_description: 'A compact blender for quick creator product demos.',
              scene_description: 'Open with a close product shot.',
              camera_motion: 'slow push-in',
              lighting_style: 'soft studio lighting',
              composition_notes: 'Keep the product clear.',
              brand_safety_notes: 'Avoid fake claims.',
              negative_prompt: 'exaggerated claims, warped product shape',
            },
            scene_plan: [
              {
                scene_number: 1,
                start_seconds: 0,
                end_seconds: 2,
                visual_direction: 'Open on the product.',
                product_action: 'Keep the blender readable.',
                camera_direction: 'Use smooth camera movement.',
                overlay_or_caption: 'Meet the blender',
                narration_line: 'Meet the blender.',
                provider_prompt_hint: 'Product remains accurate.',
              },
            ],
            script_plan: {
              short_script: 'Meet the blender.',
              voiceover_ready_script: 'Meet the blender in a quick product motion concept.',
              caption_lines: ['Meet the blender'],
              CTA_line: 'See product details',
            },
            motion_settings: {
              visual_style: 'realistic_product',
              motion_style: 'slow_push_in',
              camera_direction: 'Use smooth camera movement.',
              aspect_ratio: 'vertical_9_16',
              target_duration_seconds: 15,
              scenario_used: false,
              voiceover_planned: true,
            },
            asset_plan: {
              product_image_asset_id: 4,
              scenario_image_asset_id: null,
              campaign_generation_id: null,
              voiceover_generation_id: null,
              recommended_next_assets: ['Scenario image'],
              missing_items: ['Optional scenario image'],
            },
            provider_readiness: {
              provider: 'mock',
              real_provider_required: true,
              render_available: false,
              recommended_provider_type: 'image_to_video',
              future_provider_inputs: ['product_image', 'scenario_image', 'prompt', 'duration', 'aspect_ratio'],
            },
            compliance_notes: ['No real video is generated yet.'],
            metadata: {
              generation_type: 'motion_ai_video',
              provider: 'mock',
              product_image_asset_id: 4,
              scenario_image_asset_id: null,
              campaign_generation_id: null,
              voiceover_generation_id: null,
              platform: 'tiktok',
              aspect_ratio: 'vertical_9_16',
              visual_style: 'realistic_product',
              motion_style: 'slow_push_in',
              target_duration_seconds: 15,
              render_available: false,
              real_provider_enabled: false,
            },
          },
          started_at: '2026-07-07T03:00:00.000000Z',
          finished_at: '2026-07-07T03:00:00.000000Z',
          created_at: '2026-07-07T03:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(motionVideoProject, history)
    const text = wrapper.text()

    expect(text).toContain('Motion video brief')
    expect(text).toContain('Portable smoothie blender')
    expect(text).not.toContain(
      'Add a product image, source video, background reference or brand reference before generating.',
    )
    expect(text).toContain('Create another motion plan')
    expect(text).toContain('Motion AI Video')
    expect(text).toContain('Timeline notes')
    expect(text).toContain('Visual sequence')
    expect(text).toContain('Next workflow')
    expect(text).toContain('Return to project')
    expect(text).toContain('Internal diagnostics')
    expect(text).toContain('Video preview is not available for this generation')
    expect(text).not.toContain('Generated video placeholder')
    expect(text).not.toContain('API-ready payload preview')
  })

  it('shows a friendly failed state for failed Motion AI Video generations', async () => {
    const history: ProjectGenerationHistoryResponse = {
      ...emptyHistory,
      data: [
        {
          id: 56,
          user_id: 1,
          project_id: 123,
          job_type: 'generate_motion_ai_video',
          provider: 'mock',
          status: 'failed',
          cost_usd: '0.0000',
          credits_charged: 3,
          input_payload: null,
          output_payload: null,
          error_message: 'Generation failed due to an internal processing error.',
          request_payload: null,
          result_payload: null,
          started_at: '2026-07-07T03:00:00.000000Z',
          finished_at: '2026-07-07T03:01:00.000000Z',
          created_at: '2026-07-07T03:00:00.000000Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    }

    const wrapper = await mountProjectDetail(motionVideoProject, history)

    expect(wrapper.text()).toContain('Video generation failed. Your credits were refunded if they were charged.')
    expect(wrapper.text()).toContain('No fake output was created.')
    expect(wrapper.text()).toContain('Edit settings')
  })

  it('routes Motion AI Video projects to the setup page instead of direct generation', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/tools', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
        { path: '/login', component: { template: '<div />' } },
        { path: '/register', component: { template: '<div />' } },
        { path: '/projects/:id', component: ProjectDetailView },
        { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      ],
    })
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/projects/123/actions')) return Response.json(defaultActions)
      if (url.endsWith('/projects/123/generations')) return Response.json(emptyHistory)
      if (url.endsWith('/projects/123/media-assets')) return Response.json({ data: [] })
      if (url.endsWith('/tools')) {
        return Response.json({
          data: [
            {
              key: 'motion_ai_video',
              title: 'Motion AI Video',
              short_description: 'Create motion video concepts.',
              long_description: null,
              category: 'ai_video',
              status: 'available',
              route: '/dashboard?tool=motion_ai_video',
              credit_cost: 3,
              credit_cost_label: '3 credits',
              badge_label: 'Available now',
              is_recommended: false,
              sort_order: 60,
            },
          ],
        })
      }

      return Response.json({ data: motionVideoProject })
    })

    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())

    await router.push('/projects/123')
    await router.isReady()

    const wrapper = mount(ProjectDetailView, {
      global: { plugins: [createPinia(), router] },
    })
    await flushPromises()

    const button = wrapper.findAll('button').find((candidate) =>
      candidate.text().includes('Configure Motion AI Video'),
    )

    expect(button).toBeTruthy()
    await button?.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-motion-ai-video-action')
    expect(router.currentRoute.value.params.projectId).toBe('123')
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes('/projects/123/generate-motion'))).toBe(false)
  })
  it('routes Fashion Try-On projects to the setup page instead of direct generation', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/tools', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
        { path: '/login', component: { template: '<div />' } },
        { path: '/register', component: { template: '<div />' } },
        { path: '/projects/:id', component: ProjectDetailView },
        { path: '/projects/:projectId/actions/fashion-try-on', name: 'project-fashion-try-on-action', component: { template: '<div />' } },
        { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      ],
    })
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/projects/99/actions')) return Response.json(defaultActions)
      if (url.endsWith('/projects/99/generations')) return Response.json(emptyHistory)
      if (url.endsWith('/projects/99/media-assets')) return Response.json({ data: [] })
      if (url.endsWith('/tools')) return Response.json({ data: [] })

      return Response.json({ data: fashionTryOnProject })
    })

    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())

    await router.push('/projects/99')
    await router.isReady()

    const wrapper = mount(ProjectDetailView, {
      global: { plugins: [createPinia(), router] },
    })
    await flushPromises()

    const cta = [...wrapper.findAll('a'), ...wrapper.findAll('button')].find((candidate) =>
      candidate.text().includes('Setup Try-On') || candidate.text().includes('AI Fashion Try-On'),
    )

    expect(cta).toBeTruthy()
    await cta?.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-fashion-try-on-action')
    expect(router.currentRoute.value.params.projectId).toBe('99')
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes('/projects/99/generate-fashion-try-on'))).toBe(false)
  })
  it('calls the AI Voiceover generation endpoint from the project detail action', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/tools', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
        { path: '/login', component: { template: '<div />' } },
        { path: '/register', component: { template: '<div />' } },
        { path: '/projects/:id', component: ProjectDetailView },
        { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      ],
    })
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
      const method = init?.method ?? 'GET'

      if (url.endsWith('/projects/91/generate-voiceover') && method === 'POST') {
        return Response.json({
          data: {
            id: 30,
            user_id: 1,
            project_id: 91,
            job_type: 'generate_ai_voiceover',
            provider: 'mock',
            status: 'queued',
            cost_usd: '0.0000',
            credits_charged: 1,
            input_payload: voiceoverProject.input_payload,
            output_payload: null,
            error_message: null,
            started_at: null,
            finished_at: null,
            created_at: '2026-07-06T08:00:00.000000Z',
          },
          meta: { credits_balance: 19 },
        })
      }

      if (url.endsWith('/projects/91/actions')) {
        return Response.json(defaultActions)
      }

      if (url.endsWith('/projects/91/generations')) {
        return Response.json(emptyHistory)
      }

      if (url.endsWith('/projects/91/media-assets')) {
        return Response.json({ data: [] })
      }

      if (url.endsWith('/tools')) {
        return Response.json({
          data: [
            {
              key: 'ai_voiceover',
              title: 'AI Voiceover',
              short_description: 'Generate voiceovers.',
              long_description: null,
              category: 'audio',
              status: 'available',
              route: '/voiceover/new',
              credit_cost: 1,
              credit_cost_label: '1 credit',
              badge_label: 'Available now',
              is_recommended: false,
              sort_order: 40,
            },
          ],
        })
      }

      return Response.json({ data: voiceoverProject })
    })
    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())

    await router.push('/projects/91')
    await router.isReady()

    const wrapper = mount(ProjectDetailView, {
      global: { plugins: [createPinia(), router] },
    })
    await flushPromises()

    const button = wrapper.findAll('button').find((candidate) =>
      candidate.text().includes('Generate voiceover'),
    )

    expect(button).toBeTruthy()
    await button?.trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/projects/91/generate-voiceover'),
      expect.objectContaining({ method: 'POST' }),
    )
    expect(wrapper.text()).toContain('19 credits remaining')
  })
})
