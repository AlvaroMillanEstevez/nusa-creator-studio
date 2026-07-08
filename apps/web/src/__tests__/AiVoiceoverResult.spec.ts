import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import { API_ORIGIN } from '@/services/api'
import type { CreatorGeneration } from '@/types/project'

describe('AI Voiceover generation result rendering', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('shows a completed AI Voiceover result payload', () => {
    const generation: CreatorGeneration = {
      id: 1,
      user_id: 1,
      project_id: 91,
      job_type: 'generate_ai_voiceover',
      provider: 'mock',
      status: 'completed',
      cost_usd: '0.0000',
      credits_charged: 1,
      input_payload: null,
      output_payload: {
        voiceover_title: 'Spanish creator voiceover',
        narration_script: '¿Quieres crear contenido rápido? ¡Usa una voz clara!',
        scene_timing: [
          {
            timestamp: '0-3s',
            section: 'Gancho',
            narration_focus: 'Abrir con el producto.',
            delivery_note: 'Entrada cálida.',
          },
        ],
        voice_direction: 'Usa una voz natural.',
        pronunciation_notes: ['Pronuncia Cámara Pequeña Ñandú con claridad.'],
        pacing_notes: ['Apunta a 30 segundos.'],
        platform_notes: ['Mezcla debajo de vídeo vertical.'],
        estimated_duration_seconds: 30,
        suggested_audio_format: 'MP3 or WAV.',
        usage_notes: ['No se genera audio real todavía.'],
        mock_audio: {
          status: 'placeholder',
          message: 'Real audio generation will be connected later.',
          provider: 'mock',
        },
        compliance_notes: ['Evita afirmaciones exageradas.'],
      },
      error_message: null,
      started_at: '2026-07-07T00:00:00.000000Z',
      finished_at: '2026-07-07T00:00:02.000000Z',
      created_at: '2026-07-07T00:00:00.000000Z',
    }

    const wrapper = mount(GenerationResultRenderer, {
      props: { generation },
    })

    expect(wrapper.text()).toContain('Spanish creator voiceover')
    expect(wrapper.text()).toContain('¿Quieres crear contenido rápido?')
    expect(wrapper.text()).toContain('Mock audio placeholder')
    expect(wrapper.text()).not.toMatch(/Ã¡|Ã±|Â¿|Â¡/)
  })

  it('shows generated ElevenLabs audio playback and metadata without exposing storage paths', async () => {
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn<() => string>(() => 'blob:voiceover-audio'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn<() => void>(),
    })
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(new Blob(['mp3'], { type: 'audio/mpeg' }), {
        status: 200,
        headers: { 'Content-Type': 'audio/mpeg' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const generation: CreatorGeneration = {
      id: 3,
      user_id: 1,
      project_id: 91,
      job_type: 'generate_ai_voiceover',
      provider: 'elevenlabs',
      status: 'completed',
      cost_usd: '0.0000',
      credits_charged: 1,
      input_payload: null,
      output_payload: {
        voiceover_title: 'Real product narration',
        narration_script: 'Generate narration audio from a script.',
        source_generation_id: 7,
        source_generation_excerpt: 'Generate narration audio from a script.',
        audio_asset_id: 123,
        scene_timing: [],
        voice_direction: 'Generated with the configured ElevenLabs default voice.',
        pronunciation_notes: [],
        pacing_notes: [],
        platform_notes: ['Audio is saved as a reusable project asset.'],
        estimated_duration_seconds: 30,
        suggested_audio_format: 'MP3 audio saved privately in project assets.',
        audio: {
          status: 'generated',
          media_asset_id: 123,
          media_asset_name: 'voiceover-3.mp3',
          stream_url: '/api/v1/media-assets/123/stream',
          download_url: '/api/v1/media-assets/123/download',
          mime_type: 'audio/mpeg',
          format: 'mp3',
          provider: 'elevenlabs',
          provider_model: 'eleven_flash_v2_5',
          provider_voice_id: 'voice_test',
          character_count: 42,
          estimated_cost_usd: 0.0021,
          duration_seconds: null,
        },
        usage_notes: ['Generated audio was saved to this project assets list and the Media Library.'],
        compliance_notes: ['Review the narration for factual product claims before publishing.'],
      },
      error_message: null,
      started_at: '2026-07-07T00:00:00.000000Z',
      finished_at: '2026-07-07T00:00:02.000000Z',
      created_at: '2026-07-07T00:00:00.000000Z',
    }

    const wrapper = mount(GenerationResultRenderer, {
      props: { generation },
    })
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_ORIGIN + '/api/v1/media-assets/123/stream',
      expect.objectContaining({ credentials: 'include' }),
    )
    expect(wrapper.text()).toContain('Generated audio')
    expect(wrapper.text()).toContain('Audio saved to project assets')
    expect(wrapper.text()).toContain('#123')
    expect(wrapper.text()).toContain('voiceover-3.mp3')
    expect(wrapper.text()).toContain('elevenlabs')
    expect(wrapper.text()).toContain('eleven_flash_v2_5')
    expect(wrapper.text()).toContain('voice_test')
    expect(wrapper.text()).toContain('Character count')
    expect(wrapper.text()).toContain('Download audio')
    expect(wrapper.find('[data-testid="audio-controls"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Private storage paths are intentionally hidden')
    expect(wrapper.text()).not.toContain('users/1/generated/voiceovers')
  })

  it('shows the friendly failed state without exposing raw AI Voiceover errors', () => {
    const generation: CreatorGeneration = {
      id: 2,
      user_id: 1,
      project_id: 91,
      job_type: 'generate_ai_voiceover',
      provider: 'mock',
      status: 'failed',
      cost_usd: '0.0000',
      credits_charged: 1,
      input_payload: null,
      output_payload: null,
      error_message: 'SQLSTATE leaked path /var/www/provider.php:99',
      started_at: '2026-07-07T00:00:00.000000Z',
      finished_at: '2026-07-07T00:00:02.000000Z',
      created_at: '2026-07-07T00:00:00.000000Z',
    }

    const wrapper = mount(GenerationResultRenderer, {
      props: { generation },
    })

    expect(wrapper.text()).toContain('Generation failed. Please try again.')
    expect(wrapper.text()).not.toContain('SQLSTATE')
    expect(wrapper.text()).not.toContain('/var/www')
  })
})