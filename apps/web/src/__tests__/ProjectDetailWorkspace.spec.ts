import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import ProjectDetailView from '@/views/ProjectDetailView.vue'

async function mountWorkspaceProject() {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

    if (url.endsWith('/projects/77/generations')) {
      return Response.json({
        data: [],
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
      })
    }

    if (url.endsWith('/projects/77/media-assets')) {
      return Response.json({ data: [] })
    }

    if (url.endsWith('/projects/77/actions')) {
      return Response.json({
        data: [
          {
            key: 'generate_campaign',
            title: 'Generate campaign',
            description: 'Create campaign content from this project brief.',
            service_tier: 'basic',
            output_type: 'script_pack',
            status: 'available',
            credit_cost: 1,
            user_has_access: true,
            upgrade_required: false,
            access_label: 'Included in every plan',
            locked_reason: null,
            action_mode: 'direct',
            primary_cta: { label: 'Generate campaign', route: null, action: 'generate_campaign' },
            requirements: [],
            recommended_sources: [],
            recommended_assets: [],
            missing_requirements_count: 0,
            required_source: null,
            source_generation_id: null,
            reason: null,
            action_endpoint: '/api/v1/projects/77/generate-campaign',
            tool_key: 'affiliate_campaign',
            tool_status: 'available',
          },
        ],
      })
    }

    if (url.endsWith('/tools')) {
      return Response.json({ data: [] })
    }

    return Response.json({
      data: {
        id: 77,
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
          notes: 'Keep claims practical.',
        },
        settings: { platform: 'tiktok', format: 'vertical_9_16' },
        created_at: '2026-07-09T01:00:00.000000Z',
        updated_at: '2026-07-09T01:00:00.000000Z',
      },
    })
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
      { path: '/media', component: { template: '<div />' } },
      { path: '/account', component: { template: '<div />' } },
      { path: '/projects/:id', component: ProjectDetailView },
    ],
  })

  await router.push('/projects/77')
  await router.isReady()

  const wrapper = mount(ProjectDetailView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return { wrapper }
}

describe('ProjectDetailView workspace project', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders generic workspace context and project actions safely', async () => {
    const { wrapper } = await mountWorkspaceProject()
    const text = wrapper.text()

    expect(text).toContain('Creator desk launch')
    expect(text).toContain('Generic workspace')
    expect(text).toContain('Compact creator desk lighting kit')
    expect(text).toContain('A compact desk light for mobile-first creators.')
    expect(text).toContain('TikTok affiliate creators')
    expect(text).toContain('Generate campaign')
    expect(text).not.toContain('Turn one product into your next campaign')
  })
})