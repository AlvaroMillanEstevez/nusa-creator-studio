import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectDetailView from '@/views/ProjectDetailView.vue'
import type { CampaignProject, ProjectGenerationHistoryResponse } from '@/types/project'

const project: CampaignProject = {
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

const emptyHistory: ProjectGenerationHistoryResponse = {
  data: [],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
}

async function mountProjectDetail(history: ProjectGenerationHistoryResponse) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
      const body = url.endsWith('/projects/42/generations') ? history : { data: project }

      return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
      })
    }),
  )

  await router.push('/projects/42')
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

  it('renders Spanish project and generation text without losing Unicode characters', async () => {
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

    const wrapper = await mountProjectDetail(history)
    const renderedText = wrapper.text()

    expect(renderedText).toContain('¿Quieres crear contenido rápido?')
    expect(renderedText).toContain('Campaña para pequeños creadores en España')
    expect(renderedText).toContain('Diseñado para creadores que necesitan automatización')
    expect(renderedText).not.toMatch(/Ã¡|Ã±|Â¿|Â¡/)
  })

  it('explains what generating the first saved campaign will create', async () => {
    const wrapper = await mountProjectDetail(emptyHistory)

    expect(wrapper.text()).toContain(
      'No saved generations yet. Generate your first campaign to create hooks, script, caption and hashtags.',
    )
  })
})
