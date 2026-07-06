import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'

import AppNav from '@/components/AppNav.vue'
import { useAuthStore } from '@/stores/auth'

const routes = ['/', '/tools', '/dashboard', '/campaigns/new', '/login', '/register'].map(
  (path) => ({ path, component: { template: '<div />' } }),
)

describe('AppNav', () => {
  it('switches between guest and authenticated navigation', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    await router.push('/')
    await router.isReady()

    const auth = useAuthStore(pinia)
    auth.initialized = true

    const wrapper = mount(AppNav, {
      global: { plugins: [pinia, router] },
    })

    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Logout')

    auth.user = {
      id: 1,
      name: 'Nusa Creator',
      email: 'creator@example.com',
      role: 'free_user',
      plan_key: 'free',
      is_unlimited: false,
      monthly_credit_allowance: 20,
      credits_balance: 20,
      created_at: null,
    }
    await nextTick()

    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/campaigns/new"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Logout')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(false)
  })
})
