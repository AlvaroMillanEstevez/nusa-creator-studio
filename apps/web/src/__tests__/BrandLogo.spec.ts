import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'

import BrandLogo from '@/components/BrandLogo.vue'
import BrandMark from '@/components/BrandMark.vue'
import BrandWordmark from '@/components/BrandWordmark.vue'

describe('brand components', () => {
  it('renders the horizontal Nusa Creator Studio logo', () => {
    const wrapper = mount(BrandLogo)

    expect(wrapper.find('[data-testid="brand-logo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="brand-mark"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="brand-wordmark"]').text()).toContain('Nusa Creator Studio')
  })

  it('renders compact brand pieces for tight headers', () => {
    const mark = mount(BrandMark, { props: { size: 'sm' } })
    const wordmark = mount(BrandWordmark, { props: { compact: true } })

    expect(mark.get('svg').classes()).toContain('size-8')
    expect(wordmark.text()).toBe('Nusa')
  })
})
