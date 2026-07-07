import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import FashionTryOnGenerationResult from '@/components/FashionTryOnGenerationResult.vue'
import type { FashionTryOnResult } from '@/types/project'

describe('FashionTryOnGenerationResult', () => {
  it('renders a completed Fashion Try-On mock payload', () => {
    const result: FashionTryOnResult = {
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
      pose_and_framing_notes: ['Relaxed three-quarter pose.'],
      garment_fit_notes: ['Keep fit believable.'],
      background_and_lighting_notes: ['Soft even lighting.'],
      platform_usage_notes: ['Use as a planning placeholder.'],
      quality_checklist: ['References are image assets.'],
      api_ready_prompt: 'Vertical fashion try-on mockup.',
      negative_prompt: 'distorted body, changed garment design',
      recommended_assets: ['Full-body model image', 'Clear garment image'],
      compliance_notes: ['No real image generated yet.'],
    }

    const wrapper = mount(FashionTryOnGenerationResult, { props: { result } })
    const text = wrapper.text()

    expect(text).toContain('Try-on concept')
    expect(text).toContain('Generated image placeholder')
    expect(text).toContain('Real try-on image generation will be connected later.')
    expect(text).toContain('API-ready prompt')
    expect(text).toContain('Quality checklist')
    expect(text).toContain('Compliance notes')
  })
})