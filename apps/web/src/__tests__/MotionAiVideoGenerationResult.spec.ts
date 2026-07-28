import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

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

const routerLinkStub = {
  template: '<a><slot /></a>',
}

const realResult: MotionVideoResult = {
  ...result,
  motion_summary: {
    ...result.motion_summary,
    render_available: true,
    visual_first_clip: true,
    final_video_asset_id: 777,
  },
  provider_readiness: {
    provider: 'google_veo',
    real_provider_required: false,
    render_available: true,
    recommended_provider_type: 'image_to_video',
    future_provider_inputs: ['product_image', 'scenario_image', 'prompt', 'duration', 'aspect_ratio'],
  },
  metadata: {
    ...result.metadata,
    provider: 'google_veo',
    mode: 'real',
    model: 'veo-test',
    render_available: true,
    real_provider_enabled: true,
    visual_first_clip: true,
    final_video_asset_id: 777,
    provider_render_status: 'done',
  },
  rendered_video_asset: {
    id: 777,
    stream_url: '/api/v1/media-assets/777/stream',
    download_url: '/api/v1/media-assets/777/download',
    original_name: 'motion-ai-video-777.mp4',
    mime_type: 'video/mp4',
    size_bytes: 24000,
  },
  final_video_asset_id: 777,
  final_video_stream_url: '/api/v1/media-assets/777/stream',
  final_video_download_url: '/api/v1/media-assets/777/download',
  provider_metadata: {
    provider: 'google_veo',
    workflow: 'motion_ai_video',
    model: 'veo-test',
    mode: 'real',
    visual_first_clip: true,
    final_video_asset_id: 777,
    response_status: 'done',
    native_audio_ignored: true,
  },
}

describe('MotionAiVideoGenerationResult', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders motion summary, timeline notes and post-production suggestions without fake video controls', () => {
    const wrapper = mount(MotionAiVideoGenerationResult, {
      props: { result },
      global: { stubs: { RouterLink: routerLinkStub } },
    })

    expect(wrapper.text()).toContain('Motion AI Video')
    expect(wrapper.text()).toContain('Show the bottle in a fast creator desk routine.')
    expect(wrapper.text()).toContain('Timeline notes')
    expect(wrapper.text()).toContain('Visual sequence')
    expect(wrapper.text()).toContain('Open on the product image.')
    expect(wrapper.text()).toContain('Post-production text')
    expect(wrapper.text()).toContain('Suggested CTA')
    expect(wrapper.text()).toContain('Internal diagnostics')
    expect(wrapper.text()).not.toContain('Negative prompt')
    expect(wrapper.find('[data-testid="motion-final-preview"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Download MP4')
    expect(wrapper.text()).not.toContain('Generated video placeholder')
  })



  it('does not present caption suggestions as included when captions were disabled', () => {
    const captionsDisabled: MotionVideoResult = {
      ...result,
      metadata: {
        ...result.metadata,
        captions_requested: false,
      },
      script_plan: {
        ...result.script_plan,
        caption_lines: ['Meet the bottle'],
      },
    }

    const wrapper = mount(MotionAiVideoGenerationResult, {
      props: { result: captionsDisabled },
      global: { stubs: { RouterLink: routerLinkStub } },
    })

    expect(wrapper.text()).toContain('Captions preparedNo')
    expect(wrapper.text()).not.toContain('Suggested captions for post-production')
    expect(wrapper.text()).not.toContain('Suggested post-production caption')
  })

  it('renders secure preview and download controls for real Google Veo output', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(new Blob(['mp4'], { type: 'video/mp4' }), {
          status: 200,
          headers: { 'Content-Type': 'video/mp4' },
        }),
      ),
    )
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn<(object: Blob) => string>(() => 'blob:motion-video'),
      revokeObjectURL: vi.fn<(objectUrl: string) => void>(),
    })

    const wrapper = mount(MotionAiVideoGenerationResult, {
      props: { result: realResult },
      global: { stubs: { RouterLink: routerLinkStub } },
    })
    await flushPromises()

    expect(wrapper.find('[data-testid="motion-final-preview"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('MP4 preview ready')
    expect(wrapper.text()).toContain('Download MP4')
    expect(wrapper.text()).toContain('Google Veo')
    expect(wrapper.text()).toContain('veo-test')
    expect(wrapper.text()).not.toContain('provider_transport')
    expect(wrapper.get('[data-testid="motion-video-player"]').attributes('src')).toBe('blob:motion-video')
    expect(fetch).toHaveBeenCalled()
  })
})