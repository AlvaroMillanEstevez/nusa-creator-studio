import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import App from '../App.vue'
import router from '../router'

describe('App', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('links the home campaign action to the creation flow', async () => {
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(
        new Response(JSON.stringify({ status: 'ok', version: 'v1' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    await router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()

    const campaignLink = wrapper.get('a[href="/campaigns/new"]')

    expect(campaignLink.text()).toContain('Create first product campaign')
    expect(wrapper.text()).toContain('API ok - v1')
  })
})
