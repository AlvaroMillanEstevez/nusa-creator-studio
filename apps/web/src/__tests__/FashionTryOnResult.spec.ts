import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import FashionTryOnGenerationResult from '@/components/FashionTryOnGenerationResult.vue'
import type { FashionTryOnResult } from '@/types/project'

const fetchMediaBlobMock = vi.hoisted(() => vi.fn<(streamOrDownloadUrl: string) => Promise<Blob>>())

vi.mock('@/services/media', () => ({
  fetchMediaBlob: fetchMediaBlobMock,
}))

function mountResult(result: FashionTryOnResult, props: Partial<InstanceType<typeof FashionTryOnGenerationResult>['$props']> = {}) {
  return mount(FashionTryOnGenerationResult, {
    props: { result, projectId: 42, generationId: 77, ...props },
    global: {
      stubs: {
        RouterLink: {
          props: ['to'],
          template: `<a :href="typeof to === 'string' ? to : JSON.stringify(to)"><slot /></a>`,
        },
      },
    },
  })
}

describe('FashionTryOnGenerationResult', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    fetchMediaBlobMock.mockReset()
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn<(blob: Blob | MediaSource) => string>(() => 'blob://fashion-result'),
      revokeObjectURL: vi.fn<(url: string) => void>(),
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders an honest mock payload without fake image actions', () => {
    const result: FashionTryOnResult = {
      try_on_title: 'Streetwear jacket try-on',
      concept_summary: 'Create a realistic try-on readiness plan.',
      generated_image: {
        status: 'not_generated',
        message: 'Preview plan only — no try-on image was generated.',
        provider: 'mock',
      },
      composition_plan: ['Vertical composition for TikTok.'],
      quality_checklist: ['References are image assets.'],
      compliance_notes: ['No real image generated yet.'],
    }

    const wrapper = mountResult(result)
    const text = wrapper.text()

    expect(text).toContain('Try-on preparation complete')
    expect(text).toContain('Preview plan only — no try-on image was generated.')
    expect(text).toContain('No fashion image was generated in mock mode')
    expect(text).toContain('Quality checklist')
    expect(text).not.toContain('Download image')
    expect(text).not.toContain('Animate with Motion AI Video')
    expect(fetchMediaBlobMock).not.toHaveBeenCalled()
  })

  it('prioritizes real image preview and hides diagnostics from normal users', async () => {
    fetchMediaBlobMock.mockResolvedValue(new Blob(['image'], { type: 'image/png' }))

    const result: FashionTryOnResult = {
      try_on_title: 'Standard try-on result',
      concept_summary: 'A generated fashion try-on image saved privately in Nusa.',
      tier: 'standard',
      category: 'tops',
      generated_image: {
        status: 'rendered',
        message: 'Image rendered successfully.',
        provider: 'fashn',
      },
      final_image_asset_id: 501,
      final_image_stream_url: '/api/v1/media-assets/501/stream',
      final_image_download_url: '/api/v1/media-assets/501/download',
      rendered_image_asset: {
        id: 501,
        stream_url: '/api/v1/media-assets/501/stream',
        download_url: '/api/v1/media-assets/501/download',
        mime_type: 'image/png',
        size_bytes: 1024,
      },
      provider_metadata: {
        provider: 'fashn',
        provider_model: 'tryon-v1.6',
        provider_prediction_id: 'pred_123',
        provider_credits_used: 7,
      },
      generation_summary: {
        quality: 'Standard',
        category: 'tops',
        output_format: 'png',
      },
      compliance_notes: [],
    }

    const wrapper = mountResult(result)
    await flushPromises()

    const text = wrapper.text()
    expect(wrapper.find('[data-testid="fashion-real-result"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-result-image"]').attributes('src')).toBe('blob://fashion-result')
    expect(text).toContain('Download image')
    expect(text).toContain('Generate another version')
    expect(text).toContain('Animate with Motion AI Video')
    expect(wrapper.find('[data-testid="fashion-internal-diagnostics"]').exists()).toBe(false)
    expect(text).not.toContain('pred_123')
  })
})
