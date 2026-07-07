import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import type { CreatorGeneration } from '@/types/project'

describe('AI Voiceover generation result rendering', () => {
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