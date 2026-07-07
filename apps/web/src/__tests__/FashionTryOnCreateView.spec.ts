import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import FashionTryOnCreateView from '@/views/FashionTryOnCreateView.vue'

describe('FashionTryOnCreateView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('submits a fashion_try_on project payload and redirects to project detail', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/fashion-try-on/new', component: FashionTryOnCreateView },
        { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
        { path: '/tools', component: { template: '<div />' } },
        { path: '/pricing', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
        { path: '/login', component: { template: '<div />' } },
        { path: '/register', component: { template: '<div />' } },
      ],
    })
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      Response.json({
        data: {
          id: 99,
          user_id: 1,
          title: 'Streetwear jacket try-on',
          type: 'fashion_try_on',
          status: 'draft',
          input_payload: {},
          settings: { platform: 'tiktok', format: 'vertical_9_16' },
          created_at: null,
          updated_at: null,
        },
      }, { status: 201 }),
    )

    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())

    await router.push('/fashion-try-on/new')
    await router.isReady()

    const wrapper = mount(FashionTryOnCreateView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="title"]').setValue('Streetwear jacket try-on')
    await wrapper.get('input[name="product_name"]').setValue('Everyday creator jacket')
    await wrapper
      .get('textarea[name="product_description"]')
      .setValue('A lightweight jacket for content shoots.')
    await wrapper.get('textarea[name="target_audience"]').setValue('Fashion affiliate creators')
    await wrapper.get('textarea[name="background_preference"]').setValue('clean studio wall')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('"type":"fashion_try_on"'),
      }),
    )

    const body = JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))
    expect(body).toMatchObject({
      title: 'Streetwear jacket try-on',
      type: 'fashion_try_on',
      status: 'draft',
      input_payload: {
        title: 'Streetwear jacket try-on',
        product_name: 'Everyday creator jacket',
        product_description: 'A lightweight jacket for content shoots.',
        garment_type: 'jacket',
        style_goal: 'product_showcase',
        output_style: 'realistic',
        language: 'english',
        platform: 'tiktok',
      },
      settings: { platform: 'tiktok', format: 'vertical_9_16' },
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/99')
  })
})