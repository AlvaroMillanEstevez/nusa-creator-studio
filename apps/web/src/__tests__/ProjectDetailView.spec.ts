import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectDetailView from '@/views/ProjectDetailView.vue'
import type {
  CampaignProject,
  CreatorProject,
  ProductToVideoProject,
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

const emptyHistory: ProjectGenerationHistoryResponse = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
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
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
      const body = url.endsWith('/generations') ? history : { data: currentProject }

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
    expect(renderedText).not.toMatch(/Ã¡|Ã±|Â¿|Â¡/)
  })

  it('explains what generating the first saved campaign will create', async () => {
    const wrapper = await mountProjectDetail(campaignProject, emptyHistory)

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
})
