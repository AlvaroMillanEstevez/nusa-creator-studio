import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { ProductToVideoForm, ProductToVideoProject } from '@/types/project'
import NewProductToVideoView from '@/views/NewProductToVideoView.vue'

describe('NewProductToVideoView', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('renders the form and creates a product-to-video project', async () => {
    const brief: ProductToVideoForm = {
      product_name: 'Portable key light',
      product_description: 'A small light for creator desk setups.',
      key_benefits: 'Portable, adjustable and easy to set up.',
      target_audience: 'Creators filming in small rooms',
      video_goal: 'product_demo',
      video_style: 'storytelling',
      duration_seconds: 45,
      language: 'spanish',
    }
    const project: ProductToVideoProject = {
      id: 88,
      user_id: 1,
      title: brief.product_name,
      type: 'product_to_video',
      status: 'draft',
      input_payload: brief,
      settings: { platform: 'tiktok', format: 'vertical_9_16' },
      created_at: '2026-07-06T04:00:00.000000Z',
      updated_at: '2026-07-06T04:00:00.000000Z',
    }
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: project }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const placeholder = { template: '<div />' }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: placeholder },
        { path: '/tools', component: placeholder },
        { path: '/dashboard', component: placeholder },
        { path: '/campaigns/new', component: placeholder },
        { path: '/video-enhancer/new', component: placeholder },
        { path: '/product-to-video/new', component: NewProductToVideoView },
        { path: '/projects/:id', name: 'project-detail', component: placeholder },
      ],
    })
    await router.push('/product-to-video/new')
    await router.isReady()

    const wrapper = mount(NewProductToVideoView, { global: { plugins: [router] } })

    expect(wrapper.text()).toContain('Product-to-Video Script Pack')
    expect(wrapper.text()).toContain('not a rendered video')

    for (const [name, value] of Object.entries({
      product_name: brief.product_name,
      product_description: brief.product_description,
      key_benefits: brief.key_benefits,
      target_audience: brief.target_audience,
    })) {
      await wrapper.get(`[name="${name}"]`).setValue(value)
    }
    await wrapper.get('[name="video_goal"]').setValue(brief.video_goal)
    await wrapper.get('[name="video_style"]').setValue(brief.video_style)
    await wrapper.get('[name="duration_seconds"]').setValue(String(brief.duration_seconds))
    await wrapper.get('[name="language"]').setValue(brief.language)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: brief.product_name,
          type: 'product_to_video',
          status: 'draft',
          input_payload: brief,
          settings: { platform: 'tiktok', format: 'vertical_9_16' },
        }),
      }),
    )
    expect(router.currentRoute.value.fullPath).toBe('/projects/88')
  })
})
