import { describe, expect, it, vi, afterEach } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import NewProjectWorkspaceView from '@/views/NewProjectWorkspaceView.vue'

function workspaceResponse(id: number, title: string): Response {
  return Response.json({
    data: {
      id,
      user_id: 1,
      title,
      type: 'workspace',
      status: 'draft',
      input_payload: null,
      settings: null,
      created_at: '2026-07-09T01:00:00.000000Z',
      updated_at: '2026-07-09T01:00:00.000000Z',
    },
  })
}

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

  it('creates a generic workspace project without a product category and redirects to project detail', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(workspaceResponse(77, 'Creator desk launch'))
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('#project-title').setValue('Creator desk launch')
    await wrapper.get('#content-idea').setValue('Compact creator desk lighting kit')
    await wrapper.get('#product-name').setValue('Creator desk light')
    expect(wrapper.get('#product-category').attributes('role')).toBe('combobox')
    expect(wrapper.get('#product-category').attributes('placeholder')).toBe('Search or type a category…')
    expect(wrapper.text()).toContain('Optional. Use any category that helps Nusa understand your product.')
    await wrapper.get('#key-selling-points').setValue('Compact, soft light, easy setup')
    await wrapper.get('#project-context').setValue('A compact desk light for mobile-first creators.')
    await wrapper.get('#target-audience').setValue('TikTok affiliate creators')
    await wrapper.get('#cta-preference').setValue('Check product details')
    await wrapper.get('#claims-to-avoid').setValue('Do not promise professional studio results.')
    await wrapper.get('#brand-notes').setValue('Premium but practical.')
    await wrapper.get('#competitor-notes').setValue('Avoid direct competitor callouts.')
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
            product_category: null,
            content_idea: 'Compact creator desk lighting kit',
            product_description: 'A compact desk light for mobile-first creators.',
            target_audience: 'TikTok affiliate creators',
            language: 'english',
            tone: 'natural',
            platform: 'tiktok',
            main_goal: 'affiliate_content',
            key_selling_points: 'Compact, soft light, easy setup',
            claims_to_avoid: 'Do not promise professional studio results.',
            brand_notes: 'Premium but practical.',
            cta_preference: 'Check product details',
            competitor_notes: 'Avoid direct competitor callouts.',
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

  it('submits a custom product category when typed freely', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(workspaceResponse(78, 'Handmade candle launch'))
    const { wrapper } = await mountView(fetchMock)

    await wrapper.get('#project-title').setValue('Handmade candle launch')
    await wrapper.get('#product-name').setValue('Ceramic ritual candle')
    await wrapper.get('#product-category').setValue('Hand-poured home fragrance')
    await wrapper.get('#project-context').setValue('A small-batch candle for cozy desk setups.')
    await wrapper.get('#target-audience').setValue('Creators decorating their home workspace')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        body: expect.stringContaining('"product_category":"Hand-poured home fragrance"'),
      }),
    )
  })

  it('filters product category suggestions and selects one', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(workspaceResponse(79, 'Desk tech launch'))
    const { wrapper } = await mountView(fetchMock)

    await wrapper.get('#product-category').trigger('focus')
    expect(wrapper.text()).toContain('Beauty & skincare')
    expect(wrapper.text()).toContain('Software / app')

    await wrapper.get('#product-category').setValue('tech')
    expect(wrapper.text()).toContain('Tech gadget')
    expect(wrapper.text()).not.toContain('Beauty & skincare')

    const techOption = wrapper
      .findAll('[role="option"]')
      .find((option) => option.text() === 'Tech gadget')

    expect(techOption).toBeDefined()
    await techOption?.trigger('mousedown')
    await flushPromises()

    expect(wrapper.get<HTMLInputElement>('#product-category').element.value).toBe('Tech gadget')

    await wrapper.get('#project-title').setValue('Desk tech launch')
    await wrapper.get('#product-name').setValue('Magnetic cable dock')
    await wrapper.get('#project-context').setValue('A cable dock for creators with compact desks.')
    await wrapper.get('#target-audience').setValue('Tech creators with desk setup content')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledWith(
      API_BASE_URL + '/projects',
      expect.objectContaining({
        body: expect.stringContaining('"product_category":"Tech gadget"'),
      }),
    )
  })

  it('validates required workspace fields before submit', async () => {
    const fetchMock = vi.fn<typeof fetch>()
    const { wrapper } = await mountView(fetchMock)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Add a project name.')
    expect(wrapper.text()).toContain('Add a product or service name.')
    expect(wrapper.text()).toContain('Add project context so actions have enough brief detail.')
    expect(wrapper.text()).toContain('Describe the target audience.')
    expect(fetchMock).not.toHaveBeenCalled()
  })
})