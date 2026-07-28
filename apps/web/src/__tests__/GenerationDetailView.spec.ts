import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import GenerationDetailView from '@/views/GenerationDetailView.vue'

async function mountGenerationDetail() {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

    if (url.endsWith('/projects/77')) {
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
            notes: null,
          },
          settings: { platform: 'tiktok', format: 'vertical_9_16' },
          created_at: '2026-07-09T01:00:00.000000Z',
          updated_at: '2026-07-09T01:00:00.000000Z',
        },
      })
    }

    if (url.endsWith('/generations/501')) {
      return Response.json({
        data: {
          id: 501,
          user_id: 1,
          project_id: 77,
          job_type: 'generate_affiliate_campaign',
          provider: 'mock',
          status: 'completed',
          cost_usd: '0.0000',
          credits_charged: 1,
          input_payload: null,
          output_payload: {
            campaign_title: 'Creator desk campaign',
            product_angle: 'Practical creator setup.',
            target_audience_summary: 'TikTok affiliate creators.',
            hooks: ['A focused hook for a creator desk.'],
            main_script: {
              title: 'Creator desk campaign',
              duration_seconds: 30,
              voiceover_script: 'Narration from dedicated campaign detail.',
              scenes: [
                {
                  time_range: '0-5s',
                  voiceover: 'Show the desk light in a real setup.',
                  visual_direction: 'Desk close-up.',
                  on_screen_text: 'Simple creator setup',
                },
              ],
            },
            voiceover_ready_script: 'Narration from dedicated campaign detail.',
            captions: ['Caption for the result view.'],
            hashtags: ['#NusaCreator'],
            cta_options: ['Review the product details.'],
            compliance_notes: ['Avoid guaranteed results.'],
            usage_notes: ['Use real footage.'],
          },
          error_message: null,
          started_at: null,
          finished_at: null,
          created_at: '2026-07-09T01:30:00.000000Z',
        },
      })
    }

    if (url.endsWith('/projects/77/media-assets')) {
      return Response.json({ data: [] })
    }

    if (url.endsWith('/projects/77/actions')) {
      return Response.json({
        data: [
          {
            key: 'generate_voiceover_from_campaign',
            title: 'Create voiceover',
            description: 'Generate a voiceover from this selected campaign.',
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
            required_source: 'source_generation',
            source_generation_id: 501,
            reason: null,
            action_endpoint: '/api/v1/projects/77/generate-voiceover',
            tool_key: 'ai_voiceover',
            tool_status: 'available',
          },
          {
            key: 'generate_campaign',
            title: 'Generate campaign',
            description: 'Create another campaign.',
            service_tier: 'basic',
            output_type: 'script_pack',
            status: 'available',
            credit_cost: 1,
            user_has_access: true,
            upgrade_required: false,
            access_label: 'Included',
            locked_reason: null,
            action_mode: 'direct',
            primary_cta: { label: 'Generate another campaign', route: null, action: 'generate_campaign' },
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

    return Response.json({ data: [] })
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
      { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/auto-video-editor', name: 'project-auto-video-editor-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/motion-ai-video', name: 'project-motion-ai-video-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: GenerationDetailView },
    ],
  })

  await router.push('/projects/77/generations/501')
  await router.isReady()

  const wrapper = mount(GenerationDetailView, {
    global: { plugins: [createPinia(), router] },
  })
  await flushPromises()

  return { wrapper }
}

describe('GenerationDetailView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders a focused campaign result view with workspace navigation and action hub', async () => {
    const { wrapper } = await mountGenerationDetail()
    const text = wrapper.text()

    expect(text).toContain('Creator desk campaign')
    expect(text).toContain('A focused hook for a creator desk.')
    expect(text).toContain('Narration voice')
    expect(text).toContain('Back to workspace')
    expect(wrapper.find('a[href="/projects/77"]').text()).toContain('Back to workspace')
    expect(wrapper.find('[data-testid="project-action-hub"]').exists()).toBe(true)
    expect(text).toContain('Create Motion AI Video')
  })
})