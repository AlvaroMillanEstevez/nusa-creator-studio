import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import ProjectAssets from '@/components/ProjectAssets.vue'
import { API_BASE_URL } from '@/services/api'
import type { MediaAsset } from '@/types/media'
import { SUPPORTED_MEDIA_MESSAGE } from '@/utils/mediaValidation'

const asset: MediaAsset = {
  id: 12,
  user_id: 1,
  project_id: 42,
  type: 'image',
  disk: 'local',
  storage_identifier: 'media-12',
  original_name: 'product.png',
  mime_type: 'image/png',
  size_bytes: 68,
  size_label: '68 B',
  metadata: {
    detected_extension: 'png',
    uploaded_extension: 'png',
    validation_source: 'server',
  },
  created_at: '2026-07-06T00:00:00.000000Z',
  updated_at: '2026-07-06T00:00:00.000000Z',
}

async function selectFile(wrapper: ReturnType<typeof mount>, file: File): Promise<void> {
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', {
    value: [file],
    configurable: true,
  })
  await input.trigger('change')
}

describe('ProjectAssets', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('uploads an asset and adds it to the project list', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (_input, options) => {
      if (options?.method === 'POST') {
        return new Response(JSON.stringify({ data: asset }), {
          status: 201,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, { props: { projectId: 42 } })
    await flushPromises()

    expect(wrapper.text()).toContain('Project assets')
    expect(wrapper.text()).toContain('No assets saved to this project yet.')

    await selectFile(wrapper, new File(['safe'], 'product.png', { type: 'image/png' }))
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="media-asset-12"]').text()).toContain('product.png')
    expect(wrapper.text()).toContain('Asset uploaded and saved to this project.')

    const postCall = fetchMock.mock.calls.find(([, options]) => options?.method === 'POST')
    expect(postCall?.[0]).toBe(API_BASE_URL + '/media-assets')
  })

  it('deletes a project asset through the API', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (_input, options) => {
      if (options?.method === 'DELETE') {
        return new Response(JSON.stringify({ message: 'Media asset deleted successfully.' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [asset] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, { props: { projectId: 42 } })
    await flushPromises()

    await wrapper.get('[data-testid="media-asset-12"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="media-asset-12"]').exists()).toBe(false)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/media-assets/12',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })

  it('shows the friendly supported-format message for backend rejection', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (_input, options) => {
      if (options?.method === 'POST') {
        return new Response(JSON.stringify({ message: 'Unsafe internal upload detail.' }), {
          status: 422,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, { props: { projectId: 42 } })
    await flushPromises()

    await selectFile(wrapper, new File(['safe'], 'product.png', { type: 'image/png' }))
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe(SUPPORTED_MEDIA_MESSAGE)
    expect(wrapper.text()).not.toContain('Unsafe internal upload detail.')
  })
})