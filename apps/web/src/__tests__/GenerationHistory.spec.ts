import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import GenerationHistory from '@/components/GenerationHistory.vue'
import type { CreatorGeneration, GenerationStatus } from '@/types/project'

function makeGeneration(
  id: number,
  createdAt: string,
  status: GenerationStatus = 'completed',
): CreatorGeneration {
  return {
    id,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_affiliate_campaign',
    provider: 'mock',
    status,
    cost_usd: '0.0000',
    credits_charged: 1,
    input_payload: null,
    output_payload: null,
    error_message: null,
    request_payload: null,
    result_payload: null,
    started_at: createdAt,
    finished_at: createdAt,
    created_at: createdAt,
  }
}

describe('GenerationHistory', () => {
  it('keeps newest-first order, marks the selection, and emits previous selections', async () => {
    const newest = makeGeneration(2, '2026-07-06T02:00:00.000000Z')
    const previous = makeGeneration(1, '2026-07-06T01:00:00.000000Z')
    const wrapper = mount(GenerationHistory, {
      props: {
        generations: [newest, previous],
        selectedGeneration: newest,
        isLoading: false,
        error: '',
        emptyCopy: 'No saved generations yet.',
      },
    })

    const historyButtons = wrapper.findAll('button')

    expect(historyButtons).toHaveLength(2)
    expect(historyButtons[0]?.text()).toContain('Latest generation')
    expect(historyButtons[0]?.classes()).toContain('border-emerald-500')
    expect(historyButtons[1]?.text()).toContain('Generation #1')

    await historyButtons[1]?.trigger('click')

    expect(wrapper.emitted('select')?.[0]?.[0]).toEqual(previous)
  })
  it('renders queued, processing, completed, and failed history statuses', () => {
    const statuses: GenerationStatus[] = ['queued', 'processing', 'completed', 'failed']
    const generations = statuses.map((status, index) =>
      makeGeneration(index + 1, `2026-07-06T0${index + 1}:00:00.000000Z`, status),
    )
    const wrapper = mount(GenerationHistory, {
      props: {
        generations,
        selectedGeneration: generations[0] ?? null,
        isLoading: false,
        error: '',
        emptyCopy: 'No saved generations yet.',
      },
    })

    for (const status of statuses) {
      expect(wrapper.text()).toContain(status)
    }
  })

  it('uses friendly workflow labels for campaign variants and video analysis', () => {
    const imageAssisted = {
      ...makeGeneration(3, '2026-07-06T03:00:00.000000Z'),
      input_payload: { campaign_input_mode: 'image_assisted' },
    }
    const videoAnalysis = {
      ...makeGeneration(4, '2026-07-06T04:00:00.000000Z'),
      job_type: 'generate_video_to_campaign',
      input_payload: { campaign_input_mode: 'video_analysis' },
    }
    const voiceover = {
      ...makeGeneration(5, '2026-07-06T05:00:00.000000Z'),
      job_type: 'generate_ai_voiceover',
    }
    const autoEditor = {
      ...makeGeneration(6, '2026-07-06T06:00:00.000000Z'),
      job_type: 'generate_auto_video_edit',
    }

    const wrapper = mount(GenerationHistory, {
      props: {
        generations: [imageAssisted, videoAnalysis, voiceover, autoEditor],
        selectedGeneration: imageAssisted,
        isLoading: false,
        error: '',
        emptyCopy: 'No saved generations yet.',
      },
    })

    expect(wrapper.text()).toContain('Product Content Kit · Image assisted')
    expect(wrapper.text()).toContain('Product Content Kit · Video analysis')
    expect(wrapper.text()).toContain('AI Voiceover')
    expect(wrapper.text()).toContain('Auto Video Editor')
    expect(wrapper.text()).not.toContain('generate_video_to_campaign')
    expect(wrapper.text()).not.toContain('affiliate_campaign')
  })

})
