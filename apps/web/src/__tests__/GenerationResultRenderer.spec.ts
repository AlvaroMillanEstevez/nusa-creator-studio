import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'

import GenerationResultRenderer from '@/components/GenerationResultRenderer.vue'
import type { CreatorGeneration } from '@/types/project'

function failedFashionGeneration(outputPayload: CreatorGeneration['output_payload']): CreatorGeneration {
  return {
    id: 73,
    user_id: 1,
    project_id: 42,
    job_type: 'generate_fashion_try_on',
    provider: 'fashn',
    status: 'failed',
    cost_usd: '0.0000',
    credits_charged: 2,
    input_payload: null,
    output_payload: outputPayload,
    error_message: 'Generation failed due to an internal processing error.',
    request_payload: null,
    result_payload: null,
    started_at: '2026-07-08T01:00:00.000000Z',
    finished_at: '2026-07-08T01:01:00.000000Z',
    created_at: '2026-07-08T01:00:00.000000Z',
  }
}

function mountRenderer(generation: CreatorGeneration) {
  return mount(GenerationResultRenderer, {
    props: { generation, projectId: 42 },
    global: {
      plugins: [createPinia()],
      stubs: {
        RouterLink: {
          props: ['to'],
          template: `<a :href="typeof to === 'string' ? to : JSON.stringify(to)"><slot /></a>`,
        },
      },
    },
  })
}

describe('GenerationResultRenderer Fashion Try-On failures', () => {
  it('renders safe PoseError guidance and prioritizes the person photo action', () => {
    const wrapper = mountRenderer(failedFashionGeneration({
      provider_metadata: {
        safe_error_category: 'fashn_pose_not_detected',
        provider_error_name: 'PoseError',
        provider_error_message: 'Failed to detect body pose in model image.',
      },
    } as CreatorGeneration['output_payload']))

    const text = wrapper.text()

    expect(text).toContain('We could not detect a clear body pose')
    expect(text).toContain('Use a photo where the person’s torso and body are clearly visible.')
    expect(text).toContain('Choose another person photo')
    expect(text).toContain('Choose another product photo')
    expect(text).toContain('Return to project')
    expect(text).not.toContain('PoseError')
    expect(text).not.toContain('model_image')
    expect(wrapper.findAll('[data-testid="fashion-failure-actions"] a')[0]?.text()).toContain('Choose another person photo')
  })

  it('keeps historical generic Fashion Try-On failures readable', () => {
    const wrapper = mountRenderer(failedFashionGeneration(null))

    expect(wrapper.text()).toContain('Fashion Try-On failed')
    expect(wrapper.text()).toContain('Your images were not processed.')
    expect(wrapper.text()).toContain('Edit settings')
  })
})
