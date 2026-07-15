import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import type { AutoVideoAdResult, CreatorGeneration } from '@/types/project'
import { generationFriendlyLabel } from '@/utils/generations'

const adResult: AutoVideoAdResult = {
  ad_summary: {
    platform: 'tiktok_ads',
    format: 'vertical_9_16',
    duration: 15,
    ad_goal: 'sales',
    visual_source_type: 'hybrid',
    creative_angle: 'Position the Creator desk light around a practical desk setup message.',
    target_audience: 'TikTok affiliate creators',
    main_cta: 'See product details',
  },
  hook_options: [
    {
      text: 'Before you skip, see how this desk light fits a creator workflow.',
      style: 'benefit_first',
      why_it_works: 'It opens with a practical pattern interrupt.',
    },
  ],
  ad_script: {
    opening_hook: 'Quick look at the Creator desk light.',
    problem_or_context: 'Show the creator desk context before presenting the product.',
    product_benefit_sequence: [
      'Introduce the product with a clear visual.',
      'Show the compact desk setup benefit.',
      'Show one practical usage moment.',
    ],
    proof_or_demo_line: 'Use visual proof from the source asset.',
    CTA: 'See product details',
    voiceover_ready_script: 'Quick look at the Creator desk light. See product details.',
  },
  storyboard: [
    {
      scene_number: 1,
      start_seconds: 0,
      end_seconds: 3,
      visual_direction: 'Open with the clearest product shot.',
      overlay_text: 'Worth a quick look?',
      narration_line: 'Here is the Creator desk light in context.',
      asset_role: 'product_video',
      transition_note: 'Fast clean cut into benefit.',
    },
  ],
  overlay_plan: [
    {
      text: 'Creator desk light in action',
      placement: 'upper third',
      timing: '0-3s',
      style: 'clean_product',
      purpose: 'Establish product context quickly.',
    },
  ],
  caption_variants: {
    short_caption: 'Creator desk light in a simple product demo.',
    direct_response_caption: 'A clearer way to show your creator desk setup.',
    soft_sell_caption: 'A practical look without exaggerated claims.',
  },
  hashtags: ['#ProductDemo', '#CreatorTools'],
  cta_options: ['See product details', 'Learn more before you buy'],
  compliance_notes: ['Do not invent discounts, prices, guarantees or performance claims.'],
  render_plan: {
    renderer: 'shotstack_future',
    render_ready: false,
    missing_items: ['Final Shotstack render pass is not implemented for Auto Video Ads yet.'],
    recommended_assets: ['Product image for end card', 'Source video for demo clips'],
    timeline_strategy: 'Use a fast hook, two proof beats and a CTA end card.',
    overlay_style: 'clean_product',
    end_card_strategy: 'End on product and one verified CTA.',
  },
  metadata: {
    generation_type: 'auto_video_ad',
    provider: 'mock',
    visual_source_type: 'hybrid',
    campaign_generation_id: 31,
    product_image_asset_id: 90,
    source_video_asset_id: 88,
    voiceover_generation_id: 44,
    target_duration_seconds: 15,
    ad_format: 'vertical_9_16',
    platform: 'tiktok_ads',
    ad_goal: 'sales',
    tone: 'direct_response',
    visual_style: 'clean_product',
    hook_style: 'benefit_first',
    render_available: false,
    shotstack_render_pending: false,
    voiceover_ready: true,
    campaign_source_used: true,
  },
}

const generation: CreatorGeneration = {
  id: 203,
  user_id: 1,
  project_id: 42,
  job_type: 'generate_auto_video_ad',
  provider: 'mock',
  status: 'completed',
  cost_usd: '0.0000',
  credits_charged: 12,
  input_payload: null,
  output_payload: null,
  error_message: null,
  request_payload: null,
  result_payload: adResult,
  started_at: '2026-07-08T01:00:00.000000Z',
  finished_at: '2026-07-08T01:00:00.000000Z',
  created_at: '2026-07-08T01:00:00.000000Z',
}

function router() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/auto-video-ads', name: 'project-auto-video-ads-action', component: { template: '<div />' } },
    ],
  })
}

describe('Auto Video Ads generation result', () => {
  it('renders ad summary, storyboard, overlays and future render messaging', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        generation,
        projectId: 42,
      },
      global: { plugins: [appRouter] },
    })

    expect(wrapper.get('[data-testid="auto-video-ad-result"]').text()).toContain('Paid ad creative plan')
    expect(wrapper.text()).toContain('Storyboard timeline')
    expect(wrapper.text()).toContain('Overlay plan')
    expect(wrapper.text()).toContain('Final MP4 rendering coming next')
    expect(wrapper.text()).toContain('shotstack_future')
    expect(wrapper.text()).not.toContain('provider-asset')
    expect(wrapper.text()).not.toContain('.mp4')
  })

  it('uses friendly visual-source labels for image, video and hybrid ad generations', () => {
    expect(generationFriendlyLabel({
      ...generation,
      result_payload: { ...adResult, metadata: { ...adResult.metadata, visual_source_type: 'image' } },
    })).toBe('Auto Video Ads · Image ad')

    expect(generationFriendlyLabel({
      ...generation,
      result_payload: { ...adResult, metadata: { ...adResult.metadata, visual_source_type: 'video' } },
    })).toBe('Auto Video Ads · Video ad')

    expect(generationFriendlyLabel(generation)).toBe('Auto Video Ads · Hybrid ad')
  })
})

describe('Rendered Auto Video Ads result', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the rendered video preview and download before the ad plan', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(new Response(new Blob(['mp4'], { type: 'video/mp4' }), {
      status: 200,
      headers: { 'Content-Type': 'video/mp4' },
    })))
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn<(object: Blob | MediaSource) => string>(() => 'blob:auto-video-ad-preview'),
      revokeObjectURL: vi.fn<(url: string) => void>(),
    })

    const renderedResult: AutoVideoAdResult = {
      ...adResult,
      render_plan: {
        ...adResult.render_plan,
        renderer: 'shotstack',
        render_ready: true,
        render_available: true,
        missing_items: [],
      },
      metadata: {
        ...adResult.metadata,
        provider: 'shotstack',
        render_available: true,
        shotstack_render_pending: false,
        provider_render_status: 'done',
        final_video_asset_id: 501,
        final_duration_seconds: 18.5,
      },
      rendered_video_asset: {
        id: 501,
        stream_url: '/api/v1/media-assets/501/stream',
        download_url: '/api/v1/media-assets/501/download',
        original_name: 'auto-video-ad-203.mp4',
        mime_type: 'video/mp4',
        size_bytes: 1024,
      },
      final_video_asset_id: 501,
      final_video_stream_url: '/api/v1/media-assets/501/stream',
      final_video_download_url: '/api/v1/media-assets/501/download',
      timeline: [
        {
          order: 1,
          timeline_range: '0-3s',
          timeline_start_seconds: 0,
          timeline_end_seconds: 3,
          source: 'source_video',
          source_range: '2-5s',
          source_start_seconds: 2,
          source_end_seconds: 5,
          instruction: 'Open with the clearest product shot.',
          text_overlay: 'Worth a quick look?',
        },
      ],
      captions_plan: [{ start: 0.4, length: 2.2, text: 'Here is the product.', type: 'caption' }],
      provider_metadata: {
        provider: 'shotstack',
        workflow: 'auto_video_ads',
        provider_render_status: 'done',
        final_duration_seconds: 18.5,
        repeated_clip_count: 0,
        diverse_source_segments_used: true,
        caption_schedule: [{ start: 0.4, length: 2.2, text: 'Here is the product.', type: 'caption' }],
        render_settings: { selected_clip_count: 1, cta_used: 'See product details' },
      },
    }

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        generation: {
          ...generation,
          provider: 'shotstack',
          result_payload: renderedResult,
        },
        projectId: 42,
      },
      global: { plugins: [appRouter] },
    })

    await flushPromises()

    expect(wrapper.get('[data-testid="auto-video-ad-final-preview"]').text()).toContain('Rendered auto video ad')
    expect(wrapper.get('[data-testid="auto-video-ad-player"]').attributes('src')).toBe('blob:auto-video-ad-preview')
    expect(wrapper.get('[data-testid="auto-video-ad-download"]').text()).toContain('Download video')
    expect(wrapper.text()).toContain('Paid ad creative plan')
    expect(wrapper.text()).toContain('Timeline / clips used')
    expect(wrapper.text()).not.toContain('Final MP4 rendering coming next')
    expect(wrapper.text()).not.toContain('shotstack-output')
    expect(wrapper.text()).not.toContain('provider-assets')
  })
})