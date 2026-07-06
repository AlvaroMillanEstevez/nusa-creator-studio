import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectDetailView from '@/views/ProjectDetailView.vue'
import type {
  CampaignProject,
  CreatorProject,
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
      plugins: [router],
    },
  })

  await flushPromises()

  return wrapper
}

describe('ProjectDetailView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

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
})
