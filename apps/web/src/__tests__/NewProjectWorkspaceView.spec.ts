import { describe, expect, it, vi, afterEach } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import NewProjectWorkspaceView from '@/views/NewProjectWorkspaceView.vue'

async function mountView(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>) {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/dashboard', component: placeholder },
      { path: '/media', component: placeholder },
      { path: '/account', component: placeholder },
      { path: '/projects/new', component: NewProjectWorkspaceView },
      { path: '/projects/:id', component: placeholder, name: 'project-detail' },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  await router.push('/projects/new')
  await router.isReady()

  const wrapper = mount(NewProjectWorkspaceView, {
    global: { plugins: [createPinia(), router] },
  })

  await flushPromises()

  return { wrapper, router }
}

describe('NewProjectWorkspaceView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('creates a generic workspace project and redirects to project detail', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      Response.json({
        data: {
          id: 77,
          user_id: 1,
          title: 'Creator desk launch',
          type: 'workspace',
          status: 'draft',
          input_payload: null,
          settings: null,
          created_at: '2026-07-09T01:00:00.000000Z',
          updated_at: '2026-07-09T01:00:00.000000Z',
        },
      }),
    )
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('#project-title').setValue('Creator desk launch')
    await wrapper.get('#content-idea').setValue('Compact creator desk lighting kit')
    await wrapper.get('#product-name').setValue('Creator desk light')
    await wrapper.get('#project-context').setValue('A compact desk light for mobile-first creators.')
    await wrapper.get('#target-audience').setValue('TikTok affiliate creators')
    await wrapper.get('#project-notes').setValue('Keep claims practical.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          title: 'Creator desk launch',
          type: 'workspace',
          status: 'draft',
          input_payload: {
            product_name: 'Creator desk light',
            content_idea: 'Compact creator desk lighting kit',
            product_description: 'A compact desk light for mobile-first creators.',
            target_audience: 'TikTok affiliate creators',
            language: 'english',
            tone: 'natural',
            platform: 'tiktok',
            notes: 'Keep claims practical.',
          },
          settings: {
            platform: 'tiktok',
            format: 'vertical_9_16',
          },
        }),
      }),
    )
    expect(router.currentRoute.value.fullPath).toBe('/projects/77')
  })

  it('validates required workspace fields before submit', async () => {
    const fetchMock = vi.fn<typeof fetch>()
    const { wrapper } = await mountView(fetchMock)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Add a project name.')
    expect(wrapper.text()).toContain('Add a product or content idea.')
    expect(wrapper.text()).toContain('Add project context so actions have enough brief detail.')
    expect(wrapper.text()).toContain('Describe the target audience.')
    expect(fetchMock).not.toHaveBeenCalled()
  })
})