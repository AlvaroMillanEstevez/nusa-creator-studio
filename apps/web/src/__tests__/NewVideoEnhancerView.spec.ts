import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { VideoEnhancerForm, VideoEnhancerProject } from '@/types/project'
import NewVideoEnhancerView from '@/views/NewVideoEnhancerView.vue'

describe('NewVideoEnhancerView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the brief form and creates a video enhancer project', async () => {
    const brief: VideoEnhancerForm = {
      video_title: 'Morning desk setup',
      product_name: 'Portable key light',
      video_context: 'A creator turns on the light and shows the desk before and after.',
      spoken_summary: 'The creator explains the setup and how the light fits on a small desk.',
      target_audience: 'Creators filming in small rooms',
      language: 'spanish',
      style: 'energetic',
    }
    const project: VideoEnhancerProject = {
      id: 77,
      user_id: 1,
      title: brief.video_title,
      type: 'video_enhancer',
      status: 'draft',
      input_payload: brief,
      settings: {
        platform: 'tiktok',
        format: 'vertical_9_16',
      },
      created_at: '2026-07-06T02:00:00.000000Z',
      updated_at: '2026-07-06T02:00:00.000000Z',
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
        {
          path: '/video-enhancer/new',
          name: 'video-enhancer-create',
          component: NewVideoEnhancerView,
        },
        {
          path: '/projects/:id',
          name: 'project-detail',
          component: placeholder,
        },
      ],
    })

    await router.push('/video-enhancer/new')
    await router.isReady()

    const wrapper = mount(NewVideoEnhancerView, {
      global: {
        plugins: [createPinia(), router],
      },
    })

    expect(wrapper.text()).toContain('Video Enhancer / Caption Pack')
    expect(wrapper.find('[name="video_context"]').exists()).toBe(true)
    expect(wrapper.find('[name="spoken_summary"]').exists()).toBe(true)

    await wrapper.get('[name="video_title"]').setValue(brief.video_title)
    await wrapper.get('[name="product_name"]').setValue(brief.product_name)
    await wrapper.get('[name="video_context"]').setValue(brief.video_context)
    await wrapper.get('[name="spoken_summary"]').setValue(brief.spoken_summary)
    await wrapper.get('[name="target_audience"]').setValue(brief.target_audience)
    await wrapper.get('[name="language"]').setValue(brief.language)
    await wrapper.get('input[name="style"][value="energetic"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: brief.video_title,
          type: 'video_enhancer',
          status: 'draft',
          input_payload: brief,
          settings: {
            platform: 'tiktok',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
    expect(router.currentRoute.value.fullPath).toBe('/projects/77')
  })
})
