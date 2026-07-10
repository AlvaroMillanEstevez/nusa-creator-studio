import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import type { CreatorGeneration } from '@/types/project'

const baseGeneration: CreatorGeneration = {
  id: 101,
  user_id: 1,
  project_id: 42,
  job_type: 'generate_video_to_campaign',
  provider: 'mock',
  status: 'completed',
  cost_usd: '0.0000',
  credits_charged: 2,
  input_payload: null,
  output_payload: null,
  error_message: null,
  request_payload: null,
  result_payload: null,
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
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/voiceover', name: 'project-voiceover-action', component: { template: '<div />' } },
    ],
  })
}

describe('video workflow result rendering', () => {
  it('renders the Video-to-Campaign processing disclaimer', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        projectId: 42,
        generation: {
          ...baseGeneration,
          status: 'processing',
          result_payload: null,
          output_payload: null,
          finished_at: null,
        },
      },
      global: { plugins: [appRouter] },
    })

    expect(wrapper.get('[data-testid="generation-status"]').text()).toContain(
      'Analyzing your video and creating the campaign.',
    )
    expect(wrapper.text()).toContain(
      'This can take a few minutes depending on video length and provider processing time.',
    )
    expect(wrapper.text()).toContain('Please keep this page open. The result will update automatically.')
  })

  it('renders the Video-to-Campaign failed state with actions and no processing copy', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        projectId: 42,
        generation: {
          ...baseGeneration,
          status: 'failed',
          result_payload: null,
          output_payload: null,
          error_message: 'SQLSTATE secret in /var/www/provider.php:42',
        },
      },
      global: { plugins: [appRouter] },
    })

    const status = wrapper.get('[data-testid="generation-status"]')

    expect(status.text()).toContain('Video analysis failed. Your credits were refunded if they were charged.')
    expect(status.text()).toContain('Back to workspace')
    expect(status.text()).toContain('Try again')
    expect(status.text()).not.toContain('Analyzing your video')
    expect(status.text()).not.toContain('SQLSTATE')
    expect(wrapper.find('a[href="/projects/42"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/projects/42/actions/video-to-campaign"]').exists()).toBe(true)
  })

  it('renders Video-to-Campaign output and voiceover CTA', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        projectId: 42,
        generation: {
          ...baseGeneration,
          result_payload: {
            video_summary: 'Mock summary from source footage.',
            detected_product_moments: [
              { timestamp: '0-3s', moment: 'Show product', campaign_use: 'Opening hook' },
            ],
            scene_observations: [
              { scene: 'Opening', observation: 'Use the clearest shot.', confidence: 'mock' },
            ],
            recommended_cuts: [
              { timestamp: '0-2s', cut: 'Trim pause.', reason: 'Fast context.' },
            ],
            campaign_angle: 'Honest creator review.',
            hooks: ['Turn raw footage into a campaign.'],
            narration_voice_script: 'Start with the clearest product shot.',
            captions: ['Raw footage to campaign.'],
            hashtags: ['#ProductVideo'],
            cta_options: ['Review details.'],
            edit_plan: {
              title: 'Vertical edit plan',
              format: 'vertical_9_16',
              platform: 'tiktok',
              source_video_name: 'raw.mp4',
              timeline: [{ timestamp: '0-3s', action: 'Open with hook.' }],
            },
            compliance_notes: ['Verify claims.'],
            usage_notes: ['Mock output.'],
            source_video_asset_id: 88,
            provider_metadata: { provider: 'mock' },
          },
        },
      },
      global: { plugins: [appRouter] },
    })

    expect(wrapper.get('[data-testid="video-to-campaign-result"]').text()).toContain('Mock summary from source footage.')
    expect(wrapper.text()).toContain('Start with the clearest product shot.')
    expect(wrapper.get('[data-testid="create-voiceover-from-video-campaign"]').attributes('href')).toContain('/projects/42/actions/voiceover')
  })

  it('renders Gemini Video-to-Campaign output safely', async () => {
    const appRouter = router()
    await appRouter.push('/')
    await appRouter.isReady()

    const wrapper = mount(GenerationResultRenderer, {
      props: {
        projectId: 42,
        generation: {
          ...baseGeneration,
          provider: 'gemini',
          result_payload: {
            video_summary: 'Creator desk light video summary.',
            observed_product_features: [
              { feature: 'Compact desk light', evidence: 'Shown on a creator desk.', confidence: 'high' },
            ],
            detected_product_moments: [
              {
                start_seconds: 0,
                end_seconds: 4,
                title: 'Desk setup reveal',
                description: 'The product appears in a creator desk setup.',
                why_it_matters: 'It quickly gives context.',
                visual_quality: 'medium',
              },
            ],
            scene_observations: [
              { start_seconds: 0, end_seconds: 5, observation: 'Clear desk setup shot.', usable_for_edit: true },
            ],
            recommended_cuts: [
              { start_seconds: 0, end_seconds: 2, reason: 'Open directly on the product setup.', priority: 'high' },
            ],
            campaign_angle: 'Use the light to improve a creator desk setup.',
            hooks: [{ hook: 'Open with the before-and-after desk lighting.', style: 'problem_solution', best_for: 'Creator desk audience' }],
            narration_voice_script: 'Show the light honestly, then invite viewers to check the product details.',
            captions: ['A cleaner desk lighting setup for creators.'],
            hashtags: ['#CreatorSetup'],
            cta_options: ['Review the product details before buying.'],
            edit_plan: {
              target_duration_seconds: 30,
              opening: 'Start on the product in the desk setup.',
              middle: 'Show practical angles.',
              closing: 'End with a detail-check CTA.',
              timeline: [{ start_seconds: 0, end_seconds: 3, instruction: 'Open with the clearest desk shot.', caption: 'Creator desk lighting idea' }],
            },
            compliance_notes: ['Verify product specifications before publishing.'],
            usage_notes: ['Use as a draft campaign plan.'],
            missing_information: ['Exact product specifications are not visible.'],
            unverified_claims: [],
            source_video_asset_id: 88,
            provider_metadata: { provider: 'gemini', model: 'gemini-2.5-flash', mode: 'real' },
          },
        },
      },
      global: { plugins: [appRouter] },
    })

    expect(wrapper.get('[data-testid="video-to-campaign-result"]').text()).toContain('Gemini real video analysis')
    expect(wrapper.text()).toContain('Open with the before-and-after desk lighting.')
    expect(wrapper.text()).toContain('0s-4s')
    expect(wrapper.text()).toContain('Observed product features')
    expect(wrapper.text()).toContain('Missing information')
    expect(wrapper.text()).not.toContain('gemini://')
  })

  it('renders Auto Video Editor output', () => {
    const wrapper = mount(GenerationResultRenderer, {
      props: {
        generation: {
          ...baseGeneration,
          id: 102,
          job_type: 'generate_auto_video_edit',
          result_payload: {
            source_summary: 'Use raw-product-demo.mp4 as source footage.',
            selected_video_analysis_reference: { type: 'raw_video_asset', media_asset_id: 88, note: 'Includes video campaign analysis' },
            selected_voiceover_reference: { type: 'none', note: 'No voiceover included' },
            edit_decision_list: [{ order: 1, timestamp: '0-2s', decision: 'Open on product.', reason: 'Fast hook.' }],
            timeline: [{ timestamp: '0-3s', layer: 'video', instruction: 'Hook shot.', text_overlay: 'Meet product' }],
            clip_sequence: [{ clip: 'A-roll', source: 'source video', use: 'opening hook' }],
            captions_plan: { style: 'clean_subtitles', safe_zone: 'Keep lower third clear.', sample_captions: ['Meet product'] },
            transition_plan: ['Use clean cuts.'],
            audio_mix_plan: {
              include_voiceover: false,
              voiceover_reference: { type: 'none', note: 'No voiceover included' },
              music_direction: 'Use licensed music.',
              mix_notes: ['Keep music low.'],
            },
            voiceover_script: null,
            render_settings: {
              platform: 'tiktok',
              format: 'vertical_9_16',
              target_duration_seconds: 30,
              editing_style: 'ugc_natural',
              pacing: 'medium',
              caption_style: 'clean_subtitles',
            },
            final_video_placeholder: {
              status: 'placeholder',
              message: 'Mock edit plan only. Real rendering will be connected later.',
              provider: 'mock',
            },
            cost_breakdown: {
              base_editor_cost: 2,
              base_editor_note: 'Auto Video Editor mock planning workflow',
              video_analysis_cost: 2,
              video_analysis_note: 'Includes video campaign analysis',
              voiceover_cost: 0,
              voiceover_note: 'No voiceover included',
              render_cost_placeholder: 0,
              render_note: 'Mock render',
              total_credits: 4,
            },
            compliance_notes: ['Use owned assets.'],
            usage_notes: ['Plan only.'],
            provider_metadata: { provider: 'mock' },
          },
        },
      },
    })

    expect(wrapper.get('[data-testid="auto-video-editor-result"]').text()).toContain('Edit-ready vertical video plan')
    expect(wrapper.text()).toContain('Mock edit plan only. Real rendering will be connected later.')
    expect(wrapper.text()).toContain('Total internal credits')
  })
})
