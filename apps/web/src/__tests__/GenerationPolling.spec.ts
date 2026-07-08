import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type {
  CampaignGenerationResult,
  CampaignProject,
  CreatorGeneration,
  GenerationStatus,
} from '@/types/project'
import ProjectDetailView from '@/views/ProjectDetailView.vue'

const project: CampaignProject = {
  id: 42,
  user_id: 1,
  title: 'Portable creator light',
  type: 'affiliate_campaign',
  status: 'draft',
  input_payload: {
    product_name: 'Portable creator light',
    product_description: 'A compact light for creator desks.',
    target_audience: 'Mobile-first creators',
    language: 'english',
    tone: 'natural',
  },
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-06T00:00:00.000000Z',
  updated_at: '2026-07-06T00:00:00.000000Z',
}

const completedOutput: CampaignGenerationResult = {
  hooks: ['A completed async hook'],
  script: {
    title: 'Creator light demo',
    duration_seconds: 15,
    scenes: [
      {
        timestamp: '0-5s',
        voiceover: 'Show the compact light.',
        visual_direction: 'Film the light on a desk.',
        on_screen_text: 'Small setup upgrade',
      },
    ],
  },
  caption: 'A practical lighting idea for compact creator setups.',
  hashtags: ['#CreatorLight'],
  cta: 'Save this setup idea.',
  notes: ['Use natural product footage.', 'Keep claims realistic.'],
}

function generation(
  status: GenerationStatus,
  output: CampaignGenerationResult | null = null,
): CreatorGeneration {
  return {
    id: 900,
    user_id: 1,
    project_id: project.id,
    job_type: 'generate_affiliate_campaign',
    provider: 'mock',
    status,
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: project.input_payload === null ? null : { ...project.input_payload },
    output_payload: output,
    error_message: status === 'failed' ? 'SQLSTATE secret in /var/www/provider.php:42' : null,
    started_at: status === 'queued' ? null : '2026-07-06T00:00:01.000000Z',
    finished_at:
      status === 'completed' || status === 'failed' ? '2026-07-06T00:00:02.000000Z' : null,
    created_at: '2026-07-06T00:00:00.000000Z',
  }
}

async function mountPollingView(detailResponses: CreatorGeneration[]) {
  let detailIndex = 0
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url =
      typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/generate-campaign') && method === 'POST') {
      return Response.json({ data: generation('queued'), meta: { credits_balance: 19 } })
    }

    if (url.endsWith('/generations/900')) {
      const response = detailResponses[Math.min(detailIndex, detailResponses.length - 1)]
      detailIndex += 1
      return Response.json({ data: response })
    }

    if (url.endsWith('/projects/42/generations')) {
      return Response.json({
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
      })
    }

    if (url.endsWith('/projects/42/media-assets')) {
      return Response.json({ data: [] })
    }

    if (url.endsWith('/projects/42/actions')) {
      return Response.json({ data: [] })
    }

    if (url.endsWith('/tools')) {
      return Response.json({
        data: [
          {
            key: 'affiliate_campaign',
            title: 'Affiliate Campaign Generator',
            short_description: 'Generate a campaign.',
            long_description: null,
            category: 'campaign_creation',
            status: 'available',
            route: '/campaigns/new',
            credit_cost: 1,
            credit_cost_label: '1 credit',
            badge_label: 'Available',
            is_recommended: true,
            sort_order: 1,
          },
        ],
      })
    }

    return Response.json({ data: project })
  })

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/register', component: { template: '<div />' } },
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  await router.push('/projects/42')
  await router.isReady()

  const wrapper = mount(ProjectDetailView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return { wrapper, fetchMock }
}

function generateButton(wrapper: ReturnType<typeof mount>) {
  const button = wrapper
    .findAll('button')
    .find((candidate) => candidate.text().includes('Generate campaign'))

  if (!button) {
    throw new Error('Generate campaign button was not rendered.')
  }

  return button
}

function generationDetailCalls(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>): number {
  return fetchMock.mock.calls.filter(([input]) => String(input).endsWith('/generations/900')).length
}

describe('async generation polling', () => {
  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('shows queued and processing states, polls, renders completion, and then stops', async () => {
    vi.useFakeTimers()
    const { wrapper, fetchMock } = await mountPollingView([
      generation('processing'),
      generation('completed', completedOutput),
    ])

    await generateButton(wrapper).trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="generation-status"]').text()).toContain(
      'Your generation is queued.',
    )

    await vi.advanceTimersByTimeAsync(2_000)
    await flushPromises()
    expect(wrapper.get('[data-testid="generation-status"]').text()).toContain(
      'Creating your result...',
    )

    await vi.advanceTimersByTimeAsync(2_000)
    await flushPromises()
    expect(wrapper.text()).toContain('A completed async hook')
    expect(generationDetailCalls(fetchMock)).toBe(2)

    await vi.advanceTimersByTimeAsync(6_000)
    expect(generationDetailCalls(fetchMock)).toBe(2)
  })

  it('shows a friendly failed state without exposing the backend error', async () => {
    vi.useFakeTimers()
    const { wrapper } = await mountPollingView([generation('failed')])

    await generateButton(wrapper).trigger('click')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(2_000)
    await flushPromises()

    expect(wrapper.get('[data-testid="generation-status"]').text()).toContain(
      'Generation failed. Please try again.',
    )
    expect(wrapper.text()).not.toContain('SQLSTATE')
    expect(wrapper.text()).not.toContain('/var/www')
  })

  it('stops a pending polling loop when the project view unmounts', async () => {
    vi.useFakeTimers()
    const { wrapper, fetchMock } = await mountPollingView([generation('processing')])

    await generateButton(wrapper).trigger('click')
    await flushPromises()
    wrapper.unmount()

    await vi.advanceTimersByTimeAsync(6_000)
    expect(generationDetailCalls(fetchMock)).toBe(0)
  })
})
