import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import type { MediaAsset, MediaAssetStats } from '@/types/media'
import MediaLibraryView from '@/views/MediaLibraryView.vue'

const user: AuthUser = {
  id: 1,
  name: 'Nusa Creator',
  email: 'creator@example.com',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 12,
  created_at: null,
}

const asset: MediaAsset = {
  id: 12,
  user_id: 1,
  project_id: 42,
  type: 'image',
  usage_role: 'product_image',
  origin: 'uploaded',
  origin_label: 'Uploaded',
  disk: 'local',
  storage_identifier: 'media-12',
  stream_url: '/api/v1/media-assets/12/stream',
  download_url: '/api/v1/media-assets/12/download',
  original_name: 'product hero.png',
  mime_type: 'image/png',
  size_bytes: 2048,
  size_label: '2.0 KB',
  metadata: null,
  project_label: 'Summer launch campaign',
  project: {
    id: 42,
    title: 'Summer launch campaign',
    type: 'affiliate_campaign',
  },
  created_at: '2026-07-07T00:00:00.000000Z',
  updated_at: '2026-07-07T00:00:00.000000Z',
}

const stats: MediaAssetStats = {
  total_assets: 1,
  total_size_bytes: 2048,
  total_size_label: '2.0 KB',
  images_count: 1,
  videos_count: 0,
  audio_count: 0,
  documents_count: 0,
  other_count: 0,
  by_usage_role: {
    general: 0,
    product_image: 1,
    source_video: 0,
    raw_product_video: 0,
    campaign_source_video: 0,
    audio_track: 0,
    voiceover_audio: 0,
    edited_video_result: 0,
    video_reference: 0,
    model_reference: 0,
    garment_reference: 0,
    background_reference: 0,
    brand_reference: 0,
  },
  recent_uploads_count: 1,
}

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function blobResponse(type = 'image/png'): Response {
  return new Response(new Blob(['private media bytes'], { type }), {
    status: 200,
    headers: { 'Content-Type': type },
  })
}

function createFetchMock(
  options: {
    assets?: MediaAsset[]
    stats?: MediaAssetStats
    fail?: boolean
  } = {},
) {
  const responseAssets = options.assets ?? [asset]
  const responseStats = options.stats ?? stats

  return vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (options.fail) {
      return jsonResponse({ message: 'Server exploded.' }, 500)
    }

    if (url.endsWith('/media-assets/stats')) {
      return jsonResponse({ data: responseStats })
    }

    if (method === 'DELETE' && url.endsWith('/media-assets/12')) {
      return jsonResponse({ message: 'Media asset deleted successfully.' })
    }

    if (url.includes('/stream') || url.includes('/download')) {
      return blobResponse(url.includes('55') ? 'audio/mpeg' : 'image/png')
    }

    if (url.includes('/media-assets')) {
      return jsonResponse({
        data: responseAssets,
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 12,
          total: responseAssets.length,
        },
      })
    }

    return jsonResponse({ data: [] })
  })
}

async function mountMediaLibrary(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.user = user
  auth.initialized = true

  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/media', component: MediaLibraryView },
      { path: '/tools', component: placeholder },
      { path: '/dashboard', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/account', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
      { path: '/campaigns/new', component: placeholder },
    ],
  })

  await router.push('/media')
  await router.isReady()

  const wrapper = mount(MediaLibraryView, {
    global: { plugins: [pinia, router] },
  })

  await flushPromises()

  return wrapper
}

describe('MediaLibraryView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads assets, summary cards and project summaries', async () => {
    const fetchMock = createFetchMock()
    const wrapper = await mountMediaLibrary(fetchMock)

    expect(wrapper.text()).toContain('Media Library')
    expect(wrapper.text()).toContain('Manage uploaded and generated project assets across your creator workflows.')
    expect(wrapper.text()).toContain('Total assets')
    expect(wrapper.text()).toContain('2.0 KB')
    expect(wrapper.text()).toContain('product hero.png')
    expect(wrapper.text()).toContain('Product image')
    expect(wrapper.text()).toContain('Uploaded')
    expect(wrapper.text()).toContain('Summer launch campaign')
    expect(wrapper.text()).toContain('Affiliate Campaign')
  })

  it('keeps long asset filenames inside responsive compact card markup', async () => {
    const longName =
      'super-ultra-long-product-photo-file-name-without-spaces-and-extra-long-suffix-for-mobile-overflow-testing.png'
    const longProjectTitle =
      'A very long campaign project title for mobile overflow checks and creator workflow previews'
    const fetchMock = createFetchMock({
      assets: [
        {
          ...asset,
          original_name: longName,
          project_label: longProjectTitle,
          project: asset.project ? { ...asset.project, title: longProjectTitle } : null,
        },
      ],
    })
    const wrapper = await mountMediaLibrary(fetchMock)
    const card = wrapper.get('[data-testid="media-library-asset-12"]')

    expect(wrapper.text()).toContain(longName)
    expect(wrapper.text()).toContain(longProjectTitle)
    expect(card.classes()).toEqual(expect.arrayContaining(['min-w-0', 'max-w-full', 'overflow-hidden']))
    expect(card.get('h3').classes()).toContain('break-words')
    expect(card.get('dl').classes()).toEqual(expect.arrayContaining(['grid-cols-2', 'min-w-0']))
    expect(card.findAll('button').map((button) => button.text())).toEqual(
      expect.arrayContaining(['Preview', 'Download', 'Delete asset']),
    )
  })

  it('opens an image preview modal with a private blob URL', async () => {
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn<() => string>(() => 'blob:library-image'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn<() => void>(),
    })

    const fetchMock = createFetchMock()
    const wrapper = await mountMediaLibrary(fetchMock)

    await wrapper.get('[data-testid="media-library-asset-12"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="media-preview-modal"]').exists()).toBe(true)
    expect(wrapper.find('img[src="blob:library-image"]').exists()).toBe(true)
    expect(fetchMock.mock.calls.some((call) => call[0] === API_BASE_URL + '/media-assets/12/stream')).toBe(true)
  })

  it('renders audio playback inside the preview modal for audio assets', async () => {
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn<() => string>(() => 'blob:library-audio'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn<() => void>(),
    })

    const fetchMock = createFetchMock({
      assets: [
        {
          ...asset,
          id: 55,
          type: 'audio',
          usage_role: 'audio_track',
          storage_identifier: 'media-55',
          stream_url: '/api/v1/media-assets/55/stream',
          download_url: '/api/v1/media-assets/55/download',
          original_name: 'voiceover-55.mp3',
          mime_type: 'audio/mpeg',
          size_label: '1.2 KB',
        },
      ],
    })
    const wrapper = await mountMediaLibrary(fetchMock)

    expect(wrapper.text()).toContain('voiceover-55.mp3')
    expect(wrapper.text()).not.toContain('Play audio')

    await wrapper.get('[data-testid="media-library-asset-55"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Play audio')
    expect(wrapper.text()).toContain('Download audio')
    expect(wrapper.text()).toContain('Private media file')
    expect(wrapper.find('[data-testid="audio-controls"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('users/1/generated/voiceovers')
  })

  it('calls the API with search, type, usage role and origin filters', async () => {
    const fetchMock = createFetchMock()
    const wrapper = await mountMediaLibrary(fetchMock)

    await wrapper.get('input[name="search"]').setValue('hero')
    await wrapper.get('select[name="type"]').setValue('image')
    await wrapper.get('select[name="usage_role"]').setValue('product_image')
    await wrapper.get('select[name="origin"]').setValue('uploaded')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const filteredCall = fetchMock.mock.calls.find((call) => String(call[0]).includes('search=hero'))
    expect(filteredCall).toBeDefined()

    const url = new URL(String(filteredCall?.[0]))
    expect(url.pathname).toBe('/api/v1/media-assets')
    expect(url.searchParams.get('search')).toBe('hero')
    expect(url.searchParams.get('type')).toBe('image')
    expect(url.searchParams.get('usage_role')).toBe('product_image')
    expect(url.searchParams.get('origin')).toBe('uploaded')
    expect(url.searchParams.get('per_page')).toBe('12')
  })

  it('deletes an asset after confirmation and removes it from the list', async () => {
    const fetchMock = createFetchMock()
    vi.stubGlobal('confirm', vi.fn<() => boolean>(() => true))
    const wrapper = await mountMediaLibrary(fetchMock)
    const deleteButton = wrapper
      .get('[data-testid="media-library-asset-12"]')
      .findAll('button')
      .find((button) => button.text() === 'Delete asset')

    expect(deleteButton).toBeDefined()
    await deleteButton?.trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/media-assets/12',
      expect.objectContaining({ method: 'DELETE' }),
    )
    expect(wrapper.find('[data-testid="media-library-asset-12"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Media asset deleted.')
  })

  it('renders the empty state', async () => {
    const fetchMock = createFetchMock({ assets: [], stats: { ...stats, total_assets: 0 } })
    const wrapper = await mountMediaLibrary(fetchMock)

    expect(wrapper.text()).toContain('No media assets yet.')
    expect(wrapper.text()).toContain('Browse tools')
    expect(wrapper.text()).toContain('Back to dashboard')
  })

  it('renders a friendly error state', async () => {
    const fetchMock = createFetchMock({ fail: true })
    const wrapper = await mountMediaLibrary(fetchMock)

    expect(wrapper.get('[role="alert"]').text()).toContain('Something went wrong. Please try again.')
    expect(wrapper.text()).toContain('Media unavailable')
  })
})