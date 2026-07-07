import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import MotionVideoCreateView from '@/views/MotionVideoCreateView.vue'

describe('MotionVideoCreateView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('submits a motion_ai_video project payload and redirects to project detail', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/motion-video/new', component: MotionVideoCreateView },
        { path: '/projects/:id', name: 'project-detail', component: { template: '<div />' } },
        { path: '/tools', component: { template: '<div />' } },
        { path: '/pricing', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
        { path: '/login', component: { template: '<div />' } },
        { path: '/register', component: { template: '<div />' } },
      ],
    })
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      Response.json(
        {
          data: {
            id: 123,
            user_id: 1,
            title: 'Portable blender motion video',
            type: 'motion_ai_video',
            status: 'draft',
            input_payload: {},
            settings: { platform: 'tiktok', format: 'vertical_9_16' },
            created_at: null,
            updated_at: null,
          },
        },
        { status: 201 },
      ),
    )

    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())

    await router.push('/motion-video/new')
    await router.isReady()

    const wrapper = mount(MotionVideoCreateView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="title"]').setValue('Portable blender motion video')
    await wrapper.get('input[name="product_name"]').setValue('Portable smoothie blender')
    await wrapper
      .get('textarea[name="product_description"]')
      .setValue('A compact blender for quick creator product demos.')
    await wrapper.get('select[name="video_goal"]').setValue('affiliate_ad')
    await wrapper.get('select[name="motion_style"]').setValue('dynamic_zoom')
    await wrapper.get('select[name="visual_style"]').setValue('premium_brand')
    await wrapper.get('select[name="duration_seconds"]').setValue('30')
    await wrapper.get('select[name="aspect_ratio"]').setValue('square_1_1')
    await wrapper.get('textarea[name="target_audience"]').setValue('TikTok affiliate creators')
    await wrapper.get('select[name="platform"]').setValue('instagram_reels')
    await wrapper.get('select[name="language"]').setValue('spanish')
    await wrapper.get('textarea[name="brand_notes"]').setValue('Clean premium brand mood.')
    await wrapper.get('textarea[name="scene_notes"]').setValue('Open with product close-up.')
    await wrapper.get('textarea[name="audio_notes"]').setValue('Sync to a light beat later.')
    await wrapper.get('textarea[name="notes"]').setValue('No unrealistic claims.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('"type":"motion_ai_video"'),
      }),
    )

    const body = JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))
    expect(body).toMatchObject({
      title: 'Portable blender motion video',
      type: 'motion_ai_video',
      status: 'draft',
      input_payload: {
        title: 'Portable blender motion video',
        product_name: 'Portable smoothie blender',
        product_description: 'A compact blender for quick creator product demos.',
        video_goal: 'affiliate_ad',
        motion_style: 'dynamic_zoom',
        visual_style: 'premium_brand',
        duration_seconds: 30,
        aspect_ratio: 'square_1_1',
        target_audience: 'TikTok affiliate creators',
        platform: 'instagram_reels',
        language: 'spanish',
        brand_notes: 'Clean premium brand mood.',
        scene_notes: 'Open with product close-up.',
        audio_notes: 'Sync to a light beat later.',
        notes: 'No unrealistic claims.',
      },
      settings: { platform: 'instagram_reels', format: 'vertical_9_16' },
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/123')
  })
})