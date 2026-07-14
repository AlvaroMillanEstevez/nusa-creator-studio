import { afterEach, describe, expect, it, vi } from 'vitest'

import { mount } from '@vue/test-utils'

import AutoVideoEditorGenerationResult from '@/components/AutoVideoEditorGenerationResult.vue'
import type { AutoVideoEditorResult } from '@/types/project'

const result: AutoVideoEditorResult = {
  source_summary: 'Shotstack rendered a beta vertical edit from source footage.',
  selected_video_analysis_reference: {
    type: 'video_to_campaign_generation',
    generation_id: 31,
    note: 'Video campaign analysis reused',
  },
  selected_voiceover_reference: {
    type: 'none',
    note: 'No voiceover included',
  },
  edit_decision_list: [],
  timeline: [
    {
      timestamp: '0-4s',
      timeline_range: '0-4s',
      source_range: '12-16s',
      layer: 'video',
      instruction: 'Use the stable product close-up.',
      text_overlay: 'RGB lighting',
      timeline_start_seconds: 0,
      timeline_end_seconds: 4,
      source_start_seconds: 12,
      source_end_seconds: 16,
      duration_seconds: 4,
      source: 'recommended_cuts',
    },
  ],
  clip_sequence: [],
  captions_plan: {
    style: 'premium_clean',
    safe_zone: 'Bottom safe area.',
    caption_count: 3,
    caption_schedule: [
      { start: 0.7, length: 2, text: 'RGB lighting', type: 'hook' },
      { start: 16.8, length: 2.4, text: 'Check details', type: 'cta' },
    ],
    sample_captions: ['RGB lighting', 'Check details'],
  },
  transition_plan: [],
  audio_mix_plan: {
    include_voiceover: false,
    source_audio_mode: 'low_source',
    source_audio_volume: 0.14,
    voiceover_mix: 'voiceover_dominant',
    voiceover_volume: 0,
    audio_cleanup_strategy: 'low_source_audio_mix',
    original_audio_muted: false,
    music_direction: 'No music track added.',
    mix_notes: [],
  },
  timeline_summary: {
    edit_mode: 'ai_video_analysis',
    overlay_style: 'product_highlights',
    transition_style: 'snappy_social',
    motion_polish: 'product_focus',
    clip_diversity: 'more_unique_shots',
    end_screen: 'product_hold_cta',
    final_duration_seconds: 20,
    caption_count: 3,
    final_caption_count: 3,
    overlays_count: 3,
    unique_source_ranges: 4,
    timeline_validated: true,
    timeline_validation: {
      valid: true,
      clips_checked: 6,
      max_end_seconds: 20,
      captions_overlap_prevented: true,
    },
    edit_recipe: {
      edit_mode: 'ai_video_analysis',
      overlay_style: 'product_highlights',
      transition_style: 'snappy_social',
      motion_polish: 'product_focus',
      clip_diversity: 'more_unique_shots',
      end_screen: 'product_hold_cta',
      source_audio: 'low_source',
      voiceover_mix: 'voiceover_dominant',
      captions_used: 3,
      overlays_used: 3,
      unique_clips_used: 4,
    },
  },
  creative_brief: null,
  voiceover_script: null,
  render_settings: {
    provider: 'shotstack',
    platform: 'tiktok',
    format: 'vertical_9_16',
    target_duration_seconds: 20,
    actual_duration_seconds: 20,
    final_duration_seconds: 20,
    editing_style: 'ugc_natural',
    pacing: 'medium',
    caption_style: 'premium_clean',
    overlay_style: 'product_highlights',
    overlays_count: 3,
    unique_source_ranges: 4,
    timeline_validated: true,
  },
  final_video_placeholder: {
    status: 'placeholder',
    message: 'Mock edit plan only. Real rendering will be connected later.',
    provider: 'shotstack',
  },
  cost_breakdown: {
    base_editor_cost: 2,
    base_editor_note: 'Auto Video Editor beta render workflow',
    video_analysis_cost: 0,
    video_analysis_note: 'Video campaign analysis reused',
    voiceover_cost: 0,
    voiceover_note: 'No voiceover included',
    render_cost_placeholder: 0,
    render_note: 'Mock edit plan only',
    total_credits: 2,
  },
  compliance_notes: [],
  usage_notes: [],
}

describe('AutoVideoEditorGenerationResult', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders compact edit recipe summary with overlay style metadata', () => {
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn<() => string>(),
      revokeObjectURL: vi.fn<() => void>(),
    })

    const wrapper = mount(AutoVideoEditorGenerationResult, {
      props: { result, generationId: 77 },
    })

    const recipe = wrapper.get('[data-testid="auto-video-audio-edit-summary"]').text()
    const provider = wrapper.get('[data-testid="auto-video-provider-summary"]').text()

    expect(recipe).toContain('Edit recipe')
    expect(recipe).toContain('Overlay style')
    expect(recipe).toContain('Product Highlights')
    expect(recipe).toContain('Overlays used')
    expect(recipe).toContain('3')
    expect(recipe).toContain('Unique clips used')
    expect(recipe).toContain('4')
    expect(provider).toContain('Timeline validated')
    expect(provider).toContain('Yes')
    expect(wrapper.text()).not.toContain('Edit decision list')
    expect(wrapper.text()).not.toContain('Clip sequence')
  })
})