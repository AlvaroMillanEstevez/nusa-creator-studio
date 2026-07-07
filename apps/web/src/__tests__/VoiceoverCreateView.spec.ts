import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import VoiceoverCreateView from '@/views/VoiceoverCreateView.vue'

async function mountVoiceoverCreate(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/login', component: placeholder },
      { path: '/register', component: placeholder },
      { path: '/dashboard', component: placeholder },
      { path: '/account', component: placeholder },
      { path: '/voiceover/new', component: VoiceoverCreateView },
      { path: '/projects/:id', name: 'project-detail', component: placeholder },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push('/voiceover/new')
  await router.isReady()

  const wrapper = mount(VoiceoverCreateView, {
    global: {
      plugins: [createPinia(), router],
    },
  })

  await flushPromises()

  return { wrapper, router }
}

describe('VoiceoverCreateView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('validates the basic voiceover brief before submit', async () => {
    const fetchMock = vi.fn<typeof fetch>()
    const { wrapper } = await mountVoiceoverCreate(fetchMock)

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Add a project title.')
    expect(wrapper.text()).toContain('Add source text or at least a product name and description.')
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('submits an ai_voiceover project payload and redirects to project detail', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: {
            id: 99,
            user_id: 1,
            title: 'Spanish creator voiceover',
            type: 'ai_voiceover',
            status: 'draft',
            input_payload: {},
            settings: { platform: 'instagram_reels', format: 'vertical_9_16' },
            created_at: '2026-07-07T00:00:00.000000Z',
            updated_at: '2026-07-07T00:00:00.000000Z',
          },
        }),
        { status: 201, headers: { 'Content-Type': 'application/json' } },
      ),
    )
    const { wrapper, router } = await mountVoiceoverCreate(fetchMock)

    await wrapper.get('input[name="title"]').setValue('Spanish creator voiceover')
    await wrapper.get('textarea[name="source_text"]').setValue('¿Quieres crear contenido rápido?')
    await wrapper.get('input[name="product_name"]').setValue('Cámara Pequeña Ñandú')
    await wrapper
      .get('textarea[name="product_description"]')
      .setValue('Diseñada para creadores que necesitan automatización')
    await wrapper.get('textarea[name="target_audience"]').setValue('Pequeños creadores en España')
    await wrapper.get('select[name="language"]').setValue('spanish')
    await wrapper.get('select[name="voice_style"]').setValue('premium_brand')
    await wrapper.get('select[name="tone"]').setValue('professional')
    await wrapper.get('select[name="pace"]').setValue('fast')
    await wrapper.get('select[name="duration_seconds"]').setValue('45')
    await wrapper.get('select[name="platform"]').setValue('instagram_reels')
    await wrapper.get('textarea[name="notes"]').setValue('Keep pronunciation clear.')

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: 'Spanish creator voiceover',
          type: 'ai_voiceover',
          status: 'draft',
          input_payload: {
            title: 'Spanish creator voiceover',
            source_text: '¿Quieres crear contenido rápido?',
            product_name: 'Cámara Pequeña Ñandú',
            product_description: 'Diseñada para creadores que necesitan automatización',
            target_audience: 'Pequeños creadores en España',
            language: 'spanish',
            voice_style: 'premium_brand',
            tone: 'professional',
            pace: 'fast',
            duration_seconds: 45,
            platform: 'instagram_reels',
            notes: 'Keep pronunciation clear.',
          },
          settings: {
            platform: 'instagram_reels',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
    expect(router.currentRoute.value.fullPath).toBe('/projects/99')
  })
})