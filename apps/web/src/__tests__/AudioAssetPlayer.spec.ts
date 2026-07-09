import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import AudioAssetPlayer from '@/components/AudioAssetPlayer.vue'
import { API_ORIGIN } from '@/services/api'

function stubObjectUrls(url = 'blob:nusa-audio') {
  const createObjectURL = vi.fn<() => string>(() => url)
  const revokeObjectURL = vi.fn<() => void>()

  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: createObjectURL,
  })
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: revokeObjectURL,
  })

  return { createObjectURL, revokeObjectURL }
}

describe('AudioAssetPlayer', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('fetches a private audio blob and renders audio controls', async () => {
    const objectUrls = stubObjectUrls()
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(new Blob(['mp3-bytes'], { type: 'audio/mpeg' }), {
        status: 200,
        headers: { 'Content-Type': 'audio/mpeg' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(AudioAssetPlayer, {
      props: {
        streamUrl: '/api/v1/media-assets/12/stream',
        downloadUrl: '/api/v1/media-assets/12/download',
        title: 'voiceover-demo.mp3',
      },
    })

    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_ORIGIN + '/api/v1/media-assets/12/stream',
      expect.objectContaining({
        credentials: 'include',
        headers: { Accept: '*/*' },
      }),
    )
    expect(objectUrls.createObjectURL).toHaveBeenCalledWith(expect.any(Blob))
    expect(wrapper.get('[data-testid="audio-controls"]').attributes('src')).toBe('blob:nusa-audio')
    expect(wrapper.text()).toContain('Download audio')

    wrapper.unmount()
    expect(objectUrls.revokeObjectURL).toHaveBeenCalledWith('blob:nusa-audio')
  })


  it('renders compact playback without exposing the secure stream URL', async () => {
    stubObjectUrls()
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(new Blob(['mp3-bytes'], { type: 'audio/mpeg' }), { status: 200 }),
      ),
    )

    const wrapper = mount(AudioAssetPlayer, {
      props: {
        compact: true,
        streamUrl: '/api/v1/media-assets/42/stream',
        title: 'voiceover-42.mp3',
      },
    })

    await flushPromises()

    expect(wrapper.get('[data-testid="audio-asset-player"]').attributes('data-compact')).toBe('true')
    expect(wrapper.text()).toContain('voiceover-42.mp3')
    expect(wrapper.find('[data-testid="compact-audio-controls"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('/api/v1/media-assets/42/stream')
  })
  it('shows an error state when the private audio request fails', async () => {
    stubObjectUrls()
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        Response.json({ message: 'Missing private file.' }, { status: 404 }),
      ),
    )

    const wrapper = mount(AudioAssetPlayer, {
      props: {
        streamUrl: '/api/v1/media-assets/99/stream',
        title: 'missing.mp3',
      },
    })

    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Could not load audio.')
    expect(wrapper.find('[data-testid="audio-controls"]').exists()).toBe(false)
  })
})
