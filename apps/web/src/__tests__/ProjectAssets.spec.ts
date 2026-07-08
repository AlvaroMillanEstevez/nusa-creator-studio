import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import ProjectAssets from '@/components/ProjectAssets.vue'
import { API_BASE_URL } from '@/services/api'

describe('ProjectAssets', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows Fashion Try-On role guidance and uploads the selected usage_role', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const method = init?.method ?? 'GET'

      if (method === 'POST') {
        return Response.json(
          {
            data: {
              id: 15,
              user_id: 1,
              project_id: 99,
              type: 'image',
              usage_role: 'garment_reference',
              disk: 'local',
              storage_identifier: 'media-15',
              original_name: 'garment.png',
              mime_type: 'image/png',
              size_bytes: 12,
              size_label: '12 B',
              metadata: null,
              created_at: '2026-07-07T00:00:00.000000Z',
              updated_at: '2026-07-07T00:00:00.000000Z',
            },
          },
          { status: 201 },
        )
      }

      return Response.json({ data: [] })
    })

    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, {
      props: { projectId: 99, projectType: 'fashion_try_on' },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Upload a model/person reference image.')
    expect(wrapper.text()).toContain('Upload a garment/product reference image.')

    await wrapper.get('select[name="usage_role"]').setValue('garment_reference')

    const file = new File(['png'], 'garment.png', { type: 'image/png' })
    Object.defineProperty(wrapper.get('input[type="file"]').element, 'files', {
      value: [file],
      configurable: true,
    })
    await wrapper.get('input[type="file"]').trigger('change')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find((call) => call[1]?.method === 'POST')
    expect(uploadCall?.[0]).toBe(API_BASE_URL + '/media-assets')
    const body = uploadCall?.[1]?.body as FormData
    expect(body.get('file')).toBe(file)
    expect(body.get('project_id')).toBe('99')
    expect(body.get('usage_role')).toBe('garment_reference')
    expect(wrapper.text()).toContain('Garment reference')
  })

  it('shows Motion AI Video role guidance and uploads the selected usage_role', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const method = init?.method ?? 'GET'

      if (method === 'POST') {
        return Response.json(
          {
            data: {
              id: 22,
              user_id: 1,
              project_id: 123,
              type: 'video',
              usage_role: 'source_video',
              disk: 'local',
              storage_identifier: 'media-22',
              original_name: 'source.mp4',
              mime_type: 'video/mp4',
              size_bytes: 1200,
              size_label: '1.2 KB',
              metadata: null,
              created_at: '2026-07-07T00:00:00.000000Z',
              updated_at: '2026-07-07T00:00:00.000000Z',
            },
          },
          { status: 201 },
        )
      }

      return Response.json({ data: [] })
    })

    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, {
      props: { projectId: 123, projectType: 'motion_ai_video' },
    })
    await flushPromises()

    expect(wrapper.text()).toContain(
      'Upload a product image, source video, background reference or brand reference.',
    )
    expect(wrapper.text()).toContain('Optional: upload an audio track for future video sync.')

    await wrapper.get('select[name="usage_role"]').setValue('source_video')

    const file = new File(['mp4'], 'source.mp4', { type: 'video/mp4' })
    Object.defineProperty(wrapper.get('input[type="file"]').element, 'files', {
      value: [file],
      configurable: true,
    })
    await wrapper.get('input[type="file"]').trigger('change')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find((call) => call[1]?.method === 'POST')
    expect(uploadCall?.[0]).toBe(API_BASE_URL + '/media-assets')
    const body = uploadCall?.[1]?.body as FormData
    expect(body.get('file')).toBe(file)
    expect(body.get('project_id')).toBe('123')
    expect(body.get('usage_role')).toBe('source_video')
    expect(wrapper.text()).toContain('Source video')
  })
  it('renders an audio player for project audio assets without raw storage paths', async () => {
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn<() => string>(() => 'blob:project-audio'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn<() => void>(),
    })

    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url = String(input)

      if (url.endsWith('/media-assets/44/stream')) {
        return new Response(new Blob(['mp3'], { type: 'audio/mpeg' }), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return Response.json({
        data: [
          {
            id: 44,
            user_id: 1,
            project_id: 91,
            type: 'audio',
            usage_role: 'audio_track',
            disk: 'local',
            storage_identifier: 'media-44',
            stream_url: '/api/v1/media-assets/44/stream',
            download_url: '/api/v1/media-assets/44/download',
            original_name: 'voiceover-44.mp3',
            mime_type: 'audio/mpeg',
            size_bytes: 1200,
            size_label: '1.2 KB',
            metadata: null,
            created_at: '2026-07-07T00:00:00.000000Z',
            updated_at: '2026-07-07T00:00:00.000000Z',
          },
        ],
      })
    })

    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(ProjectAssets, {
      props: { projectId: 91, projectType: 'ai_voiceover' },
    })
    await flushPromises()
    await flushPromises()

    expect(wrapper.text()).toContain('Play audio')
    expect(wrapper.text()).toContain('voiceover-44.mp3')
    expect(wrapper.find('[data-testid="audio-controls"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('users/1/generated/voiceovers')
  })
})
