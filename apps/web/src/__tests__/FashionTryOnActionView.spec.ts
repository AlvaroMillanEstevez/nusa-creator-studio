import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { API_BASE_URL } from '@/services/api'
import type { CreatorProject, FashionTryOnPremiumPresetCapability, FashionTryOnProviderCapabilities, ProjectActionsResponse } from '@/types/project'
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

const knownPremiumPresets: FashionTryOnPremiumPresetCapability[] = [
  {
    key: 'fast_1k',
    label: 'Premium Fast',
    resolution: '1k',
    resolution_label: '1K',
    generation_mode: 'fast',
    quality_label: 'Fast',
    expected_provider_credits: 1,
    num_images: 1,
    recommended: false,
    nusa_credit_cost: null,
    configured_nusa_credit_cost: null,
    per_output_credit_cost: null,
    pricing_configured: false,
    enabled: false,
    disabled_reason: 'premium_disabled',
    validation_status: 'premium_disabled',
    beta: true,
    output_count: 1,
    current_max_outputs: 1,
    variations_pricing_ready: true,
    variations_enabled: false,
  },
  {
    key: 'balanced_2k',
    label: 'Premium Balanced',
    resolution: '2k',
    resolution_label: '2K',
    generation_mode: 'balanced',
    quality_label: 'Balanced',
    expected_provider_credits: 3,
    num_images: 1,
    recommended: true,
    nusa_credit_cost: null,
    configured_nusa_credit_cost: null,
    per_output_credit_cost: null,
    pricing_configured: false,
    enabled: false,
    disabled_reason: 'premium_disabled',
    validation_status: 'premium_disabled',
    beta: true,
    output_count: 1,
    current_max_outputs: 1,
    variations_pricing_ready: true,
    variations_enabled: false,
  },
  {
    key: 'quality_4k',
    label: 'Premium Quality',
    resolution: '4k',
    resolution_label: '4K',
    generation_mode: 'quality',
    quality_label: 'Quality',
    expected_provider_credits: 5,
    num_images: 1,
    recommended: false,
    nusa_credit_cost: null,
    configured_nusa_credit_cost: null,
    per_output_credit_cost: null,
    pricing_configured: false,
    enabled: false,
    disabled_reason: 'premium_disabled',
    validation_status: 'premium_disabled',
    beta: true,
    output_count: 1,
    current_max_outputs: 1,
    variations_pricing_ready: true,
    variations_enabled: false,
  },
]

const enabledPremiumPresets: FashionTryOnPremiumPresetCapability[] = knownPremiumPresets.map((preset) => ({
  ...preset,
  nusa_credit_cost: preset.key === 'fast_1k' ? 3 : preset.key === 'quality_4k' ? 11 : 7,
  configured_nusa_credit_cost: preset.key === 'fast_1k' ? 3 : preset.key === 'quality_4k' ? 11 : 7,
  per_output_credit_cost: preset.key === 'fast_1k' ? 3 : preset.key === 'quality_4k' ? 11 : 7,
  pricing_configured: true,
  enabled: preset.key !== 'quality_4k',
  disabled_reason: preset.key === 'quality_4k' ? 'premium_quality_validation_pending' : null,
  validation_status: preset.key === 'quality_4k' ? 'final_provider_validation_pending' : 'beta_validated',
}))

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
  premium_real_available_for_user: false,
  premium_pricing_configured: true,
  premium_default_preset: 'balanced_2k',
  premium_quality_enabled: false,
  premium_output_count: 1,
  standard_credit_cost: 2,
  standard_per_output_credit_cost: 2,
  standard_output_count: 1,
  current_max_outputs: 1,
  variations_pricing_ready: true,
  variations_enabled: false,
  standard_categories: ['auto', 'tops', 'bottoms', 'one-pieces'],
  standard_garment_photo_types: ['auto', 'flat_lay', 'model'],
  standard_modes: ['balanced', 'performance', 'quality'],
  premium_presets: [],
  available_premium_presets: [],
  known_premium_presets: knownPremiumPresets,
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

function projectActions(providerCapabilities: FashionTryOnProviderCapabilities = capabilities): ProjectActionsResponse {
  return {
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
        provider_capabilities: providerCapabilities,
      },
    ],
  }
}


const premiumCapabilities: FashionTryOnProviderCapabilities = {
  ...capabilities,
  provider: 'fashn',
  mock_mode: false,
  real_rendering_enabled: true,
  standard_enabled: true,
  premium_enabled: true,
  premium_real_available_for_user: true,
  premium_pricing_configured: true,
  premium_quality_enabled: false,
  premium_presets: ['fast_1k', 'balanced_2k'],
  available_premium_presets: enabledPremiumPresets.filter((preset) => preset.enabled),
  known_premium_presets: enabledPremiumPresets,
}

const variationPremiumPresets: FashionTryOnPremiumPresetCapability[] = enabledPremiumPresets.map((preset) => ({
  ...preset,
  enabled: true,
  disabled_reason: null,
  validation_status: 'beta_validated',
  allowed_output_counts: preset.key === 'quality_4k' ? [2] : [2, 3, 4],
  default_output_count: 2,
  max_output_count: preset.key === 'quality_4k' ? 2 : 4,
  limited_for_beta_response_safety: preset.key === 'quality_4k',
  total_credit_costs: preset.key === 'fast_1k' ? { 2: 6, 3: 9, 4: 12 } : preset.key === 'quality_4k' ? { 2: 22 } : { 2: 14, 3: 21, 4: 28 },
  expected_provider_credits_total_by_count: preset.key === 'fast_1k' ? { 2: 2, 3: 3, 4: 4 } : preset.key === 'quality_4k' ? { 2: 10 } : { 2: 6, 3: 9, 4: 12 },
  variations_enabled: true,
}))

const variationsCapabilities: FashionTryOnProviderCapabilities = {
  ...capabilities,
  maximum_outputs: 4,
  max_outputs_enabled: 4,
  current_max_outputs: 4,
  variations_enabled: true,
  multi_output_generation_enabled: true,
  packages: [
    {
      key: 'single_result',
      enabled: true,
      status: 'available',
      allowed_output_counts: [1],
      default_output_count: 1,
      selection_required_for_continuation: false,
      partial_refund_supported: false,
    },
    {
      key: 'variations',
      enabled: true,
      status: 'beta',
      allowed_output_counts: [2, 3, 4],
      default_output_count: 2,
      selection_required_for_continuation: true,
      partial_refund_supported: true,
    },
  ],
  active_packages: ['single_result', 'variations'],
  future_packages: ['catalog_pack', 'catalog_video_pack'],
  variations: {
    enabled: true,
    allowed_output_counts: [2, 3, 4],
    default_output_count: 2,
    max_output_count: 4,
    selection_required_for_continuation: true,
    partial_refund_supported: true,
    standard: {
      allowed_output_counts: [2, 3, 4],
      default_output_count: 2,
      max_output_count: 4,
    },
    premium_presets: {
      fast_1k: {
        allowed_output_counts: [2, 3, 4],
        default_output_count: 2,
        max_output_count: 4,
      },
      balanced_2k: {
        allowed_output_counts: [2, 3, 4],
        default_output_count: 2,
        max_output_count: 4,
      },
      quality_4k: {
        allowed_output_counts: [2],
        default_output_count: 2,
        max_output_count: 2,
        limited_for_beta_response_safety: true,
      },
    },
  },
}

const premiumVariationsCapabilities: FashionTryOnProviderCapabilities = {
  ...variationsCapabilities,
  provider: 'fashn',
  mock_mode: false,
  real_rendering_enabled: true,
  premium_enabled: true,
  premium_real_available_for_user: true,
  premium_quality_enabled: true,
  premium_presets: ['fast_1k', 'balanced_2k', 'quality_4k'],
  available_premium_presets: variationPremiumPresets,
  known_premium_presets: variationPremiumPresets,
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

function successfulFetch(
  responseDelay?: Promise<Response>,
  providerCapabilities: FashionTryOnProviderCapabilities = capabilities,
): ReturnType<typeof vi.fn<typeof fetch>> {
  const actionResponse = projectActions(providerCapabilities)

  return vi.fn<typeof fetch>().mockImplementation(async (input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
    const method = init?.method ?? 'GET'

    if (url.endsWith('/projects/42/actions')) return Response.json(actionResponse)
    if (url.endsWith('/fashion-try-on/capabilities')) return Response.json({ data: providerCapabilities })
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
    expect(wrapper.text()).toContain('Generate several distinct try-on results in one request')
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
      person_image_permission_confirmed: true,
    })
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/700')
  })

  it('submits a canonical Premium preset payload without Standard or raw provider fields', async () => {
    const fetchMock = successfulFetch(undefined, premiumCapabilities)
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('[data-testid="fashion-tier-premium"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="fashion-premium-settings"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-category-select"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="fashion-advanced-toggle"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Premium Balanced')
    expect(wrapper.text()).toContain('7 credits')
    expect(wrapper.text()).toContain('Final provider validation pending')
    expect(wrapper.get('[data-testid="fashion-premium-preset-quality_4k"]').attributes('disabled')).toBeDefined()

    await wrapper.get('[data-testid="fashion-premium-preset-fast_1k"]').trigger('click')
    await wrapper.get('[data-testid="fashion-premium-prompt"]').setValue('Keep the garment color accurate and use clean catalog lighting.')
    await wrapper.get('[data-testid="select-model_reference"]').trigger('click')
    await wrapper.get('[data-testid="select-garment_reference"]').trigger('click')
    await wrapper.get('[data-testid="fashion-consent-checkbox"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const submitCall = fetchMock.mock.calls.find(([input, init]) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      return url === API_BASE_URL + '/projects/42/generate-fashion-try-on' && init?.method === 'POST'
    })

    if (submitCall === undefined) {
      throw new Error('Fashion Try-On Premium submit call was not captured.')
    }

    const [, request] = submitCall
    expect(JSON.parse(String(request?.body))).toEqual({
      package_key: 'single_result',
      person_source: 'user_upload',
      tier: 'premium',
      person_image_asset_id: 101,
      product_image_asset_id: 202,
      premium_preset: 'fast_1k',
      premium_prompt: 'Keep the garment color accurate and use clean catalog lighting.',
      person_image_permission_confirmed: true,
    })
    expect(String(request?.body)).not.toContain('quality_preset')
    expect(String(request?.body)).not.toContain('standard_mode')
    expect(String(request?.body)).not.toContain('generation_mode')
    expect(String(request?.body)).not.toContain('seed')
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/700')
  })

  it('submits Variations with canonical output count and total-cost UX', async () => {
    const fetchMock = successfulFetch(undefined, variationsCapabilities)
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('[data-testid="fashion-package-variations"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="fashion-output-count-section"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Beta')
    expect(wrapper.text()).toContain('2 images')
    expect(wrapper.text()).toContain('4 credits')

    await wrapper.get('[data-testid="fashion-output-count-3"]').trigger('click')
    await wrapper.get('[data-testid="select-model_reference"]').trigger('click')
    await wrapper.get('[data-testid="select-garment_reference"]').trigger('click')
    await wrapper.get('[data-testid="fashion-consent-checkbox"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const submitCall = fetchMock.mock.calls.find(([input, init]) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      return url === API_BASE_URL + '/projects/42/generate-fashion-try-on' && init?.method === 'POST'
    })

    if (submitCall === undefined) {
      throw new Error('Fashion Try-On Variations submit call was not captured.')
    }

    const [, request] = submitCall
    expect(JSON.parse(String(request?.body))).toEqual({
      package_key: 'variations',
      person_source: 'user_upload',
      tier: 'standard',
      person_image_asset_id: 101,
      product_image_asset_id: 202,
      person_image_permission_confirmed: true,
      requested_output_count: 3,
      category: 'auto',
      garment_photo_type: 'auto',
      standard_mode: 'balanced',
    })
    expect(String(request?.body)).not.toContain('num_samples')
    expect(String(request?.body)).not.toContain('num_images')
    expect(router.currentRoute.value.fullPath).toBe('/projects/42/generations/700')
  })

  it('limits Premium Quality variations to two outputs and resets the count without submitting', async () => {
    const fetchMock = successfulFetch(undefined, premiumVariationsCapabilities)
    const { wrapper, router } = await mountView(fetchMock)

    await wrapper.get('[data-testid="fashion-package-variations"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="fashion-output-count-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-output-count-3"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-output-count-4"]').exists()).toBe(true)

    await wrapper.get('[data-testid="fashion-output-count-4"]').trigger('click')
    expect(wrapper.text()).toContain('4 images')
    expect(wrapper.text()).toContain('8 credits')

    await wrapper.get('[data-testid="fashion-tier-premium"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="fashion-output-count-4"]').exists()).toBe(true)

    await wrapper.get('[data-testid="fashion-premium-preset-fast_1k"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="fashion-output-count-4"]').exists()).toBe(true)

    await wrapper.get('[data-testid="fashion-premium-preset-quality_4k"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="fashion-output-count-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fashion-output-count-3"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="fashion-output-count-4"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="fashion-output-count-2"]').attributes('aria-checked')).toBe('true')
    expect(wrapper.get('[data-testid="fashion-quality-variation-limit-copy"]').text()).toContain('two 4K images')
    expect(wrapper.text()).toContain('2 images')
    expect(wrapper.text()).toContain('22 credits')

    const submitCallsBeforeSubmit = fetchMock.mock.calls.filter(([input, init]) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      return url === API_BASE_URL + '/projects/42/generate-fashion-try-on' && init?.method === 'POST'
    })
    expect(submitCallsBeforeSubmit).toHaveLength(0)

    await wrapper.get('[data-testid="select-model_reference"]').trigger('click')
    await wrapper.get('[data-testid="select-garment_reference"]').trigger('click')
    await wrapper.get('[data-testid="fashion-consent-checkbox"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const submitCall = fetchMock.mock.calls.find(([input, init]) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      return url === API_BASE_URL + '/projects/42/generate-fashion-try-on' && init?.method === 'POST'
    })

    if (submitCall === undefined) {
      throw new Error('Fashion Try-On Quality variations submit call was not captured.')
    }

    const [, request] = submitCall
    expect(JSON.parse(String(request?.body))).toMatchObject({
      package_key: 'variations',
      tier: 'premium',
      premium_preset: 'quality_4k',
      requested_output_count: 2,
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
