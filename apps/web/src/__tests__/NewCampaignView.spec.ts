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

describe('NewCampaignView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows Product Content Kit brief-only and image-assisted input modes with video-analysis guidance', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(91, 'Pocket Light'))
    const { wrapper } = await mountView(fetchMock)

    expect(wrapper.text()).toContain('Brief only')
    expect(wrapper.text()).toContain('Best when you already know the product, audience and angle.')
    expect(wrapper.text()).toContain('Product image')
    expect(wrapper.text()).toContain('Use a product photo to improve context and positioning.')
    expect(wrapper.text()).toContain('Product video path: analyze product footage')
    expect(wrapper.text()).toContain('Video analysis results still save into this workspace and can feed Auto Video Editor.')
  })

  it('creates a brief-only Product Content Kit by default', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(92, 'Pocket Light'))
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('#product-name').setValue('Pocket Light')
    await wrapper.get('#product-description').setValue('A compact light for creator desk videos.')
    await wrapper.get('#target-audience').setValue('TikTok affiliate creators')
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

  it('sends image-assisted mode without implying direct Auto Video Editor eligibility', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(campaignResponse(93, 'Pocket Light'))
    const { wrapper } = await mountView(fetchMock)

    await wrapper.get('input[value="image_assisted"]').setValue(true)
    await wrapper.get('#product-name').setValue('Pocket Light')
    await wrapper.get('#product-description').setValue('A compact light for creator desk videos.')
    await wrapper.get('#target-audience').setValue('TikTok affiliate creators')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const call = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/projects'))
    const body = JSON.parse(String(call?.[1]?.body)) as { input_payload?: { campaign_input_mode?: string } }

    expect(body.input_payload?.campaign_input_mode).toBe('image_assisted')
    expect(wrapper.text()).not.toContain('Use image campaigns directly in Auto Video Editor')
  })
})