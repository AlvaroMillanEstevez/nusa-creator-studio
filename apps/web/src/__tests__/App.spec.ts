import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'

import App from '../App.vue'
import router from '../router'

describe('App', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('gives the home page a clear path to the creator studio', async () => {
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

    const studioLinks = wrapper.findAll('a[href="/tools"]')

    expect(studioLinks.some((link) => link.text().includes('Open Creator Studio'))).toBe(true)
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Affiliate Campaign Generator')
    expect(wrapper.text()).not.toContain('Video Enhancer / Caption Pack')
    expect(wrapper.find('[data-testid^="tool-card-"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('API ok - v1')
  })
})
