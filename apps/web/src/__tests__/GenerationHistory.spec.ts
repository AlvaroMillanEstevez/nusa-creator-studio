import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import GenerationHistory from '@/components/GenerationHistory.vue'
import type { CreatorGeneration } from '@/types/project'

function makeGeneration(id: number, createdAt: string): CreatorGeneration {
  return {
    id,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_affiliate_campaign',
    provider: 'mock',
    status: 'completed',
    cost_usd: '0.0000',
    credits_charged: 1,
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
})
