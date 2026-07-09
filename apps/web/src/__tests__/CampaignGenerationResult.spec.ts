import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import CampaignGenerationResult from '@/components/CampaignGenerationResult.vue'
import type { CampaignGenerationResult as CampaignGenerationResultPayload } from '@/types/project'

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

describe('CampaignGenerationResult', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders legacy mock campaign output', () => {
    const wrapper = mount(CampaignGenerationResult, {
      props: { result: legacyMockResult },
    })

    expect(wrapper.text()).toContain('Before you scroll, look at this product.')
    expect(wrapper.text()).toContain('Portable blender demo')
    expect(wrapper.text()).toContain('A quick product idea for creators.')
    expect(wrapper.text()).toContain('Check the product details.')
    expect(wrapper.text()).toContain('Meet the portable blender.')
  })

  it('renders structured provider output and copies the voiceover-ready script', async () => {
    const writeText = vi.fn<(text: string) => Promise<void>>().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    })

    const wrapper = mount(CampaignGenerationResult, {
      props: { result: structuredResult },
    })

    expect(wrapper.text()).toContain('Honest mini camera campaign')
    expect(wrapper.text()).toContain('¿Quieres crear contenido rápido?')
    expect(wrapper.text()).toContain('Narration script')
    expect(wrapper.text()).toContain('Copy this clean voiceover script.')
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
})