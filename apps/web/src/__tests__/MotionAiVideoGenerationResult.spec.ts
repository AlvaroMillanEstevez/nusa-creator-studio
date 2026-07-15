import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import MotionAiVideoGenerationResult from '@/components/MotionAiVideoGenerationResult.vue'
import type { MotionVideoResult } from '@/types/project'

const result: MotionVideoResult = {
  motion_summary: {
    platform: 'tiktok',
    aspect_ratio: 'vertical_9_16',
    duration: 8,
    visual_style: 'realistic_product',
    motion_style: 'slow_push_in',
    source_type: 'campaign_assisted',
    creative_angle: 'Show the bottle in a fast creator desk routine.',
    target_audience: 'TikTok affiliate creators',
    primary_cta: 'See product details',
    render_available: false,
  },
  generation_prompt: {
    main_prompt: 'Create a short image-to-video concept for the bottle.',
    product_description: 'A simple bottle for creator desks.',
    scene_description: 'Clean creator desk scene.',
    camera_motion: 'slow push-in',
    lighting_style: 'soft studio lighting',
    composition_notes: 'Keep the product centered.',
    brand_safety_notes: 'Avoid invented claims.',
    negative_prompt: 'warped product, fake logos',
  },
  scene_plan: [
    {
      scene_number: 1,
      start_seconds: 0,
      end_seconds: 3,
      visual_direction: 'Open on the product image.',
      product_action: 'Keep the bottle readable.',
      camera_direction: 'Slow push-in.',
      overlay_or_caption: 'Meet the bottle',
      narration_line: 'Meet the bottle.',
      provider_prompt_hint: 'Bottle remains accurate.',
    },
  ],
  script_plan: {
    short_script: 'Meet the bottle.',
    voiceover_ready_script: 'Meet the bottle and see how it fits your desk.',
    caption_lines: ['Meet the bottle', 'See product details'],
    CTA_line: 'See product details',
  },
  motion_settings: {
    visual_style: 'realistic_product',
    motion_style: 'slow_push_in',
    camera_direction: 'Slow push-in.',
    aspect_ratio: 'vertical_9_16',
    target_duration_seconds: 8,
    scenario_used: false,
    voiceover_planned: true,
  },
  asset_plan: {
    product_image_asset_id: 90,
    scenario_image_asset_id: null,
    campaign_generation_id: 31,
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
  compliance_notes: ['Do not invent product claims.'],
  metadata: {
    generation_type: 'motion_ai_video',
    provider: 'mock',
    product_image_asset_id: 90,
    scenario_image_asset_id: null,
    campaign_generation_id: 31,
    voiceover_generation_id: null,
    platform: 'tiktok',
    aspect_ratio: 'vertical_9_16',
    visual_style: 'realistic_product',
    motion_style: 'slow_push_in',
    target_duration_seconds: 8,
    render_available: false,
    real_provider_enabled: false,
  },
}

describe('MotionAiVideoGenerationResult', () => {
  it('renders motion summary, prompt, scene plan and provider readiness without fake video controls', () => {
    const wrapper = mount(MotionAiVideoGenerationResult, {
      props: { result },
    })

    expect(wrapper.text()).toContain('Motion AI Video')
    expect(wrapper.text()).toContain('Show the bottle in a fast creator desk routine.')
    expect(wrapper.text()).toContain('Motion prompt')
    expect(wrapper.text()).toContain('Scene plan timeline')
    expect(wrapper.text()).toContain('Open on the product image.')
    expect(wrapper.text()).toContain('Provider readiness')
    expect(wrapper.text()).toContain('Real motion video rendering coming next')
    expect(wrapper.text()).not.toContain('Download')
    expect(wrapper.text()).not.toContain('Generated video placeholder')
  })
})
