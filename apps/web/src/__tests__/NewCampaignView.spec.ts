import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import NewCampaignView from '@/views/NewCampaignView.vue'

function campaignResponse(id: number, title: string): Response {
  return Response.json({
    data: {
      id,
      user_id: 1,
      title,
      type: 'affiliate_campaign',
      status: 'draft',
      input_payload: null,
      settings: null,
      created_at: '2026-07-14T01:00:00.000000Z',
      updated_at: '2026-07-14T01:00:00.000000Z',
    },
  })
}

async function mountView(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: NewCampaignView },
      { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
      { path: '/projects/:projectId/actions/video-to-campaign', name: 'project-video-to-campaign-action', component: { template: '<div />' } },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  await router.push('/campaigns/new')
  await router.isReady()

  const wrapper = mount(NewCampaignView, {
    global: { plugins: [createPinia(), router] },
  })

  await flushPromises()

  return { wrapper, router }
}

async function fillRequiredFields(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper']): Promise<void> {
  await wrapper.get('#product-name').setValue('Pocket Light')
  await wrapper.get('#product-description').setValue('A compact light for creator desk videos.')
  await wrapper.get('#target-audience').setValue('TikTok affiliate creators')
}

describe('NewCampaignView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows Product Content Kit brief, image and video-analysis input modes', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(91, 'Pocket Light'))
    const { wrapper } = await mountView(fetchMock)

    expect(wrapper.text()).toContain('Brief only')
    expect(wrapper.text()).toContain('Best when you already know the product, audience and angle.')
    expect(wrapper.text()).toContain('Product image')
    expect(wrapper.text()).toContain('Image-assisted campaign context')
    expect(wrapper.text()).toContain('Product video analysis')
    expect(wrapper.text()).toContain('Analyze product footage for campaign ideas and edit-plan data.')
    expect(wrapper.text()).toContain('Brief and image-assisted kits create campaign content.')
  })

  it('creates a brief-only Product Content Kit by default', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(92, 'Pocket Light'))
    const { wrapper, router } = await mountView(fetchMock)

    await fillRequiredFields(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: 'Pocket Light',
          type: 'affiliate_campaign',
          status: 'draft',
          input_payload: {
            campaign_input_mode: 'brief_only',
            product_name: 'Pocket Light',
            product_description: 'A compact light for creator desk videos.',
            target_audience: 'TikTok affiliate creators',
            language: 'english',
            tone: 'natural',
          },
          settings: {
            platform: 'tiktok',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
    expect(router.currentRoute.value.fullPath).toBe('/projects/92')
  })

  it('uploads a product image and stores image-assisted asset context on the project', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
      const url = String(input)
      const method = init?.method ?? 'GET'

      if (url.endsWith('/projects') && method === 'POST') {
        return campaignResponse(93, 'Pocket Light')
      }

      if (url.endsWith('/media-assets') && method === 'POST') {
        return Response.json({
          data: {
            id: 901,
            user_id: 1,
            project_id: 93,
            type: 'image',
            usage_role: 'product_image',
            disk: 'local',
            storage_identifier: 'asset-901',
            original_name: 'pocket-light.jpg',
            mime_type: 'image/jpeg',
            size_bytes: 2048,
            size_label: '2 KB',
            metadata: null,
            created_at: '2026-07-14T01:00:00.000000Z',
            updated_at: '2026-07-14T01:00:00.000000Z',
          },
        }, { status: 201 })
      }

      if (url.endsWith('/projects/93') && method === 'PATCH') {
        return campaignResponse(93, 'Pocket Light')
      }

      return Response.json({}, { status: 404 })
    })
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('input[value="image_assisted"]').setValue(true)
    await fillRequiredFields(wrapper)

    const input = wrapper.get<HTMLInputElement>('[data-testid="campaign-product-image-file"]')
    Object.defineProperty(input.element, 'files', {
      value: [new File(['image'], 'pocket-light.jpg', { type: 'image/jpeg' })],
      configurable: true,
    })
    await input.trigger('change')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const uploadCall = fetchMock.mock.calls.find(([input, init]) => String(input).endsWith('/media-assets') && init?.method === 'POST')
    const formData = uploadCall?.[1]?.body as FormData
    const patchCall = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects/93'))
    const patchBody = JSON.parse(String(patchCall?.[1]?.body)) as { input_payload?: Record<string, unknown> }

    expect(formData.get('project_id')).toBe('93')
    expect(formData.get('usage_role')).toBe('product_image')
    expect(formData.get('type')).toBe('image')
    expect(patchBody.input_payload).toMatchObject({
      campaign_input_mode: 'image_assisted',
      source_image_asset_id: 901,
      product_image_asset_id: 901,
      product_image_asset_name: 'pocket-light.jpg',
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/93')
    expect(wrapper.text()).not.toContain('Use image campaigns directly in Auto Video Editor')
  })

  it('creates a video-analysis Product Content Kit workspace and routes to the video analysis flow', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(94, 'Pocket Light'))
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('input[value="video_analysis"]').setValue(true)
    await fillRequiredFields(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects'))
    const body = JSON.parse(String(call?.[1]?.body)) as { input_payload?: { campaign_input_mode?: string } }

    expect(body.input_payload?.campaign_input_mode).toBe('video_analysis')
    expect(router.currentRoute.value.name).toBe('project-video-to-campaign-action')
    expect(router.currentRoute.value.params.projectId).toBe('94')
  })
})
