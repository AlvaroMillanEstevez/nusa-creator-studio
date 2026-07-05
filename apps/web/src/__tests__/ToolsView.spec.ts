import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import { availableTools, comingSoonTools } from '@/config/tools'
import ToolsView from '@/views/ToolsView.vue'

async function mountToolsView() {
  const placeholder = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: placeholder },
      { path: '/tools', component: ToolsView },
      { path: '/dashboard', component: placeholder },
      { path: '/campaigns/new', component: placeholder },
    ],
  })

  await router.push('/tools')
  await router.isReady()

  return mount(ToolsView, {
    global: {
      plugins: [router],
    },
  })
}

describe('ToolsView', () => {
  it('renders available tools first and links the campaign generator', async () => {
    const wrapper = await mountToolsView()
    const text = wrapper.text()
    const affiliateCard = wrapper.get('[data-testid="tool-card-affiliate_campaign"]')

    expect(text).toContain('What do you want to create today?')
    expect(text.indexOf('Available tools')).toBeLessThan(text.indexOf('Coming soon'))
    expect(affiliateCard.element.tagName).toBe('A')
    expect(affiliateCard.attributes('href')).toBe('/campaigns/new')
    expect(affiliateCard.text()).toContain('Affiliate Campaign Generator')
    expect(availableTools.every((tool) => Boolean(tool.route))).toBe(true)
  })

  it('renders coming soon services without broken links', async () => {
    const wrapper = await mountToolsView()

    expect(comingSoonTools).toHaveLength(5)

    for (const tool of comingSoonTools) {
      const card = wrapper.get(`[data-testid="tool-card-${tool.id}"]`)

      expect(card.element.tagName).toBe('ARTICLE')
      expect(card.attributes('aria-disabled')).toBe('true')
      expect(card.find('a').exists()).toBe(false)
      expect(card.text()).toContain(tool.title)
      expect('route' in tool).toBe(false)
    }
  })
})
