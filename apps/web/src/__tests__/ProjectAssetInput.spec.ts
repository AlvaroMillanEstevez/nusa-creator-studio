import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'

import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { API_BASE_URL } from '@/services/api'
import type { MediaAsset } from '@/types/media'

const imageAsset: MediaAsset = {
  id: 11,
  user_id: 1,
  project_id: 42,
  type: 'image',
  usage_role: 'product_image',
  disk: 'local',
  storage_identifier: 'asset-11',
  original_name: 'product-photo.jpg',
  mime_type: 'image/jpeg',
  size_bytes: 2048,
  size_label: '2 KB',
  metadata: null,
  created_at: '2026-07-10T01:00:00.000000Z',
  updated_at: '2026-07-10T01:00:00.000000Z',
}

const videoAsset: MediaAsset = {
  ...imageAsset,
  id: 12,
  type: 'video',
  usage_role: 'campaign_source_video',
  storage_identifier: 'asset-12',
  original_name: 'raw-product-demo.mp4',
  mime_type: 'video/mp4',
}

function mountInput(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>, props = {}) {
  vi.stubGlobal('fetch', fetchMock)

  const wrapper = mount(ProjectAssetInput, {
    props: {
      projectId: 42,
      modelValue: null,
      acceptedTypes: 'image',
      usageRole: 'product_image',
      title: 'Upload or choose product image',
      helperText: 'Use a clear product image.',
      ...props,
    },
  })

  return wrapper
}

function createFetchMock(assets: MediaAsset[] = [], uploadedAsset: MediaAsset = imageAsset) {
  return vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/media-assets') && method === 'GET') {
      return Response.json({ data: assets })
    }

    if (url.endsWith('/media-assets') && method === 'POST') {
      return Response.json({ data: uploadedAsset }, { status: 201 })
    }

    return Response.json({}, { status: 404 })
  })
}

async function attachFile(wrapper: VueWrapper, file: File): Promise<void> {
  const input = wrapper.get<HTMLInputElement>('[data-testid="project-asset-file"]')

  Object.defineProperty(input.element, 'files', {
    value: [file],
    configurable: true,
  })

  await input.trigger('change')
}

describe('ProjectAssetInput', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders an empty state without linking users away to /media', async () => {
    const fetchMock = createFetchMock([])
    const wrapper = mountInput(fetchMock)
    await flushPromises()

    expect(wrapper.text()).toContain('No project assets yet')
    expect(wrapper.find('[data-testid="project-asset-empty"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/media"]').exists()).toBe(false)
    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects/42/media-assets',
      expect.objectContaining({ headers: expect.any(Headers) }),
    )
  })

  it('selects an existing project image', async () => {
    const wrapper = mountInput(createFetchMock([imageAsset, videoAsset]))
    await flushPromises()

    await wrapper.get('[data-testid="project-asset-option-11"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([11])
    expect(wrapper.text()).toContain('product-photo.jpg')
    expect(wrapper.text()).not.toContain('raw-product-demo.mp4')
  })

  it('selects an existing project video', async () => {
    const wrapper = mountInput(createFetchMock([imageAsset, videoAsset]), {
      acceptedTypes: 'video',
      usageRole: 'campaign_source_video',
      title: 'Upload or choose product video',
    })
    await flushPromises()

    await wrapper.get('[data-testid="project-asset-option-12"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([12])
    expect(wrapper.text()).toContain('raw-product-demo.mp4')
    expect(wrapper.text()).not.toContain('product-photo.jpg')
  })

  it('uploads and selects a new image inside the current project', async () => {
    const uploadedAsset = { ...imageAsset, id: 90, original_name: 'new-product.jpg' }
    const fetchMock = createFetchMock([], uploadedAsset)
    const wrapper = mountInput(fetchMock)
    await flushPromises()

    const file = new File(['image'], 'new-product.jpg', { type: 'image/jpeg' })
    await attachFile(wrapper, file)
    await wrapper.get('[data-testid="project-asset-upload"]').trigger('click')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find(([input, init]) => String(input).endsWith('/media-assets') && init?.method === 'POST')
    const formData = uploadCall?.[1]?.body as FormData

    expect(formData.get('project_id')).toBe('42')
    expect(formData.get('usage_role')).toBe('product_image')
    expect(formData.get('type')).toBe('image')
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([90])
    expect(wrapper.text()).toContain('new-product.jpg')
  })

  it('uploads and selects a new video inside the current project', async () => {
    const uploadedAsset = { ...videoAsset, id: 91, original_name: 'new-demo.mp4' }
    const fetchMock = createFetchMock([], uploadedAsset)
    const wrapper = mountInput(fetchMock, {
      acceptedTypes: 'video',
      usageRole: 'campaign_source_video',
      title: 'Upload or choose product video',
    })
    await flushPromises()

    const file = new File(['video'], 'new-demo.mp4', { type: 'video/mp4' })
    await attachFile(wrapper, file)
    await wrapper.get('[data-testid="project-asset-upload"]').trigger('click')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find(([input, init]) => String(input).endsWith('/media-assets') && init?.method === 'POST')
    const formData = uploadCall?.[1]?.body as FormData

    expect(formData.get('project_id')).toBe('42')
    expect(formData.get('usage_role')).toBe('campaign_source_video')
    expect(formData.get('type')).toBe('video')
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([91])
    expect(wrapper.text()).toContain('new-demo.mp4')
  })
})
