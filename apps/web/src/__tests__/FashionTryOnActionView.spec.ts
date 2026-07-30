import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { CreatorProject, FashionTryOnProviderCapabilities, ProjectActionsResponse } from '@/types/project'
import FashionTryOnActionView from '@/views/FashionTryOnActionView.vue'

const project: CreatorProject = {
  id: 42,
  user_id: 1,
  title: 'Streetwear launch workspace',
  type: 'workspace',
  status: 'draft',
  input_payload: null,
  settings: { platform: 'tiktok', format: 'vertical_9_16' },
  created_at: '2026-07-07T00:00:00.000000Z',
  updated_at: '2026-07-07T00:00:00.000000Z',
}

const capabilities: FashionTryOnProviderCapabilities = {
  provider: 'mock',
  mock_mode: true,
  public_enabled: true,
  real_rendering_supported: true,
  real_rendering_enabled: false,
  standard_supported: true,
  standard_enabled: true,
  premium_supported: true,
  premium_enabled: false,
  standard_categories: ['auto', 'tops', 'bottoms', 'one-pieces'],
  standard_garment_photo_types: ['auto', 'flat_lay', 'model'],
  standard_modes: ['balanced', 'performance', 'quality'],
  premium_presets: ['premium_fast', 'premium_balanced', 'premium_quality'],
  active_packages: ['single_result'],
  default_package: 'single_result',
  future_packages: ['variations', 'catalog_pack', 'catalog_video_pack'],
  active_person_sources: ['user_upload'],
  default_person_source: 'user_upload',
  future_person_sources: ['nusa_ai_model', 'custom_ai_model'],
  maximum_outputs: 1,
  max_outputs_enabled: 1,
  multi_output_supported: true,
  multi_output_generation_enabled: false,
  consent_required: true,
  person_image_required: true,
  product_image_required: true,
  supported_mime_types: ['image/jpeg', 'image/png', 'image/webp'],
}

const actions: ProjectActionsResponse = {
  data: [
    {
      key: 'start_fashion_try_on',
      title: 'AI Fashion Try-On',
      description: 'Combine a person photo with a clothing product image.',
      service_tier: 'premium',
      output_type: 'image',
      status: 'available',
      credit_cost: 2,
      user_has_access: true,
      upgrade_required: false,
      action_mode: 'configure',
      requirements: [],
      recommended_sources: [],
      recommended_assets: [],
      missing_requirements_count: 0,
      required_source: 'media_asset',
      source_generation_id: null,
      reason: null,
      action_endpoint: '/api/v1/projects/42/generate-fashion-try-on',
      tool_key: 'fashion_try_on',
      tool_status: 'available',
      provider_capabilities: capabilities,
    },
  ],
}

const ProjectAssetInputStub = {
  props: ['modelValue', 'usageRole', 'title', 'helperText'],
  emits: ['update:modelValue'],
  template: `
    <div :data-testid="'asset-input-' + usageRole">
      <p>{{ title }}</p>
      <p>{{ helperText }}</p>
      <button type="button" :data-testid="'select-' + usageRole" @click="$emit('update:modelValue', usageRole === 'model_reference' ? 101 : 202)">
        Select {{ usageRole }}
      </button>
    </div>
  `,
}

async function mountView(fetchMock: ReturnType<typeof vi.fn<typeof fetch>>, path = '/projects/42/actions/fashion-try-on') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/projects/:projectId/actions/fashion-try-on', name: 'project-fashion-try-on-action', component: FashionTryOnActionView },
      { path: '/projects/:projectId/generations/:generationId', name: 'project-generation-detail', component: { template: '<div />' } },
      { path: '/projects/:id', component: { template: '<div />' } },
      { path: '/pricing', component: { template: '<div />' } },
    ],
  })

  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('scrollTo', vi.fn<() => void>())

  await router.push(path)
  await router.isReady()

  const wrapper = mount(FashionTryOnActionView, {
    global: {
      plugins: [createPinia(), router],
      stubs: {
        AppNav: { template: '<nav />' },
        ProjectAssetInput: ProjectAssetInputStub,
        RouterLink: { props: ['to'], template: '<a><slot /></a>' },
      },
    },
  })
  await flushPromises()

  return { wrapper, router }
}

function successfulFetch(responseDelay?: Promise<Response>): ReturnType<typeof vi.fn<typeof fetch>> {
  return vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/actions')) return Response.json(actions)
    if (url.endsWith('/fashion-try-on/capabilities')) return Response.json({ data: capabilities })
    if (url.endsWith('/projects/42/generate-fashion-try-on') && method === 'POST') {
      return responseDelay ?? Response.json({ data: { id: 700 } }, { status: 201 })
    }

    return Response.json({ data: project })
  })
}

const scrollIntoViewMock = vi.fn<() => void>()

describe('FashionTryOnActionView', () => {
  beforeEach(() => {
    scrollIntoViewMock.mockClear()
    Object.defineProperty(window.HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      value: scrollIntoViewMock,
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders packages, person sources, disabled Premium and local validation without locking the button', async () => {
    const { wrapper } = await mountView(successfulFetch())

    expect(wrapper.text()).toContain('AI Fashion Try-On')
    expect(wrapper.text()).toContain('Choose your Try-On package')
    expect(wrapper.text()).toContain('Single Try-On')
    expect(wrapper.get('[data-testid="fashion-package-variations"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="fashion-package-catalog_pack"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="fashion-person-source-nusa_ai_model"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="fashion-person-source-custom_ai_model"]').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Standard')
    expect(wrapper.text()).toContain('Premium')
    expect(wrapper.get('[data-testid="fashion-tier-premium"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="fashion-category-select"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-consent-checkbox"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="fashion-generate-button"]').attributes('disabled')).toBeUndefined()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Choose or upload one person photo first.')
    expect(wrapper.get('[data-testid="fashion-generate-button"]').attributes('disabled')).toBeUndefined()
    expect(scrollIntoViewMock).toHaveBeenCalled()
  })

  it('submits a canonical Standard payload once and redirects to generation detail', async () => {
    let resolveResponse!: (response: Response) => void
    const responsePromise = new Promise<Response>((resolve) => {
      resolveResponse = resolve
    })
    const fetchMock = successfulFetch(responsePromise)
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('[data-testid="select-model_reference"]').trigger('click')
    await wrapper.get('[data-testid="select-garment_reference"]').trigger('click')
    await wrapper.get('[data-testid="fashion-category-select"]').setValue('tops')
    await wrapper.get('[data-testid="fashion-advanced-toggle"]').trigger('click')
    await wrapper.get('[data-testid="fashion-garment-photo-type-select"]').setValue('flat_lay')
    await wrapper.get('[data-testid="fashion-standard-mode-select"]').setValue('quality')
    await wrapper.get('[data-testid="fashion-consent-checkbox"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')

    const submitCallsBeforeResolve = fetchMock.mock.calls.filter(([input, init]) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      return url === API_BASE_URL + '/projects/42/generate-fashion-try-on' && init?.method === 'POST'
    })
    expect(submitCallsBeforeResolve).toHaveLength(1)

    resolveResponse(Response.json({ data: { id: 700 } }, { status: 201 }))
    await flushPromises()

    const submitCall = submitCallsBeforeResolve[0]
    if (submitCall === undefined) {
      throw new Error('Fashion Try-On submit call was not captured.')
    }
    const [, request] = submitCall
    expect(JSON.parse(String(request?.body))).toEqual({
      package_key: 'single_result',
      person_source: 'user_upload',
      tier: 'standard',
      person_image_asset_id: 101,
      product_image_asset_id: 202,
      category: 'tops',
      garment_photo_type: 'flat_lay',
      standard_mode: 'quality',
      quality_preset: null,
      prompt: null,
      person_image_permission_confirmed: true,
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/700')
  })

  it('ignores invalid query preselection IDs safely', async () => {
    const { wrapper } = await mountView(
      successfulFetch(),
      '/projects/42/actions/fashion-try-on?personImageAssetId=abc&productImageAssetId=-9',
    )

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[data-testid="fashion-generate-button"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).toContain('Choose or upload one person photo first.')
  })
})
