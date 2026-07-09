import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import type { CampaignGenerationResult as CampaignGenerationResultPayload, ProjectAction } from '@/types/project'

const legacyMockResult: CampaignGenerationResultPayload = {
  hooks: ['Before you scroll, look at this product.'],
  script: {
    title: 'Portable blender demo',
    duration_seconds: 30,
    scenes: [
      {
        timestamp: '0-3s',
        voiceover: 'Meet the portable blender.',
        visual_direction: 'Show a clean product close-up.',
        on_screen_text: 'Meet the blender',
      },
    ],
  },
  caption: 'A quick product idea for creators.',
  hashtags: ['#TikTokShop', '#CreatorTips'],
  cta: 'Check the product details.',
  notes: ['Use real footage.'],
}

const structuredResult: CampaignGenerationResultPayload = {
  campaign_title: 'Honest mini camera campaign',
  product_angle: 'Show the camera as a practical creator tool without exaggerated claims.',
  target_audience_summary: 'Small creators who need a simple product filming setup.',
  hooks: [
    {
      label: 'Hook 1',
      text: '¿Quieres crear contenido rápido?',
      intent: 'Open with the creator problem.',
    },
  ],
  main_script: {
    duration_seconds: 30,
    voiceover_script: 'Voiceover script from the provider.',
    on_screen_text: ['Review details'],
    scenes: [
      {
        time_range: '0-5s',
        voiceover: 'Start with the product on the desk.',
        visual_direction: 'Show a real desk setup.',
        on_screen_text: 'Practical setup',
      },
    ],
  },
  voiceover_ready_script: 'Copy this clean voiceover script.',
  captions: ['Diseñado para creadores que necesitan automatización.'],
  hashtags: ['#Creadores', '#TikTokShop', '#Producto'],
  cta_options: ['Review the product details before deciding.'],
  compliance_notes: ['Do not imply guaranteed results.'],
  usage_notes: ['Use real product footage.'],
  provider_metadata: {
    provider: 'openai',
    model: 'gpt-5.4-nano',
    estimated_cost_usd: 0.0002,
  },
}

const voiceoverAction: ProjectAction = {
  key: 'generate_voiceover_from_campaign',
  title: 'Create voiceover from this script',
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
  recommended_sources: [],
  recommended_assets: [],
  missing_requirements_count: 0,
  required_source: null,
  source_generation_id: 901,
  reason: null,
  action_endpoint: '/api/v1/projects/77/generate-voiceover',
  tool_key: 'ai_voiceover',
  tool_status: 'available',
}

async function mountCampaignResult(props: Record<string, unknown> = {}, initialPath = '/projects/77/generations/901') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/projects/:projectId/generations/:generationId', name: 'generation-result', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
    ],
  })

  await router.push(initialPath)
  await router.isReady()

  const wrapper = mount(CampaignGenerationResult, {
    props: {
      result: structuredResult,
      ...props,
    },
    global: { plugins: [createPinia(), router] },
  })

  return { wrapper, router }
}

describe('CampaignGenerationResult', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('renders legacy mock campaign output', () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', component: { template: '<div />' } }],
    })
    const wrapper = mount(CampaignGenerationResult, {
      props: { result: legacyMockResult },
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.text()).toContain('Before you scroll, look at this product.')
    expect(wrapper.text()).toContain('Portable blender demo')
    expect(wrapper.text()).toContain('A quick product idea for creators.')
    expect(wrapper.text()).toContain('Check the product details.')
    expect(wrapper.text()).toContain('Meet the portable blender.')
  })

  it('renders structured provider output with narration voice route CTA', async () => {
    const writeText = vi.fn<(text: string) => Promise<void>>().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    })

    const { wrapper } = await mountCampaignResult()

    expect(wrapper.text()).toContain('Honest mini camera campaign')
    expect(wrapper.text()).toContain('¿Quieres crear contenido rápido?')
    expect(wrapper.text()).toContain('Narration voice')
    expect(wrapper.text()).toContain('Edit the narration before generating voiceover')
    expect(wrapper.text()).toContain('Copy this clean voiceover script.')
    expect(wrapper.find('[data-testid="narration-voice-textarea"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Voiceover-ready script')
    expect(wrapper.text()).not.toContain('Copy into voiceover next')
    expect(wrapper.text()).not.toContain('Use this script as source when ready')
    expect(wrapper.text()).toContain('Diseñado para creadores que necesitan automatización.')
    expect(wrapper.text()).toContain('Do not imply guaranteed results.')
    expect(wrapper.text()).toContain('openai')
    expect(wrapper.text()).toContain('gpt-5.4-nano')

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledWith('Copy this clean voiceover script.')
    expect(wrapper.text()).toContain('Copied')
  })

  it('routes narration voiceover CTA to the dedicated setup page with return context', async () => {
    const { wrapper, router } = await mountCampaignResult({
      projectId: 77,
      generationId: 901,
      voiceoverAction,
    })

    await wrapper.get('[data-testid="generate-narration-voiceover"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('project-voiceover-action')
    expect(router.currentRoute.value.params.projectId).toBe('77')
    expect(router.currentRoute.value.query.source_generation_id).toBe('901')
    expect(router.currentRoute.value.query.return_to).toBe('/projects/77/generations/901')
  })
})