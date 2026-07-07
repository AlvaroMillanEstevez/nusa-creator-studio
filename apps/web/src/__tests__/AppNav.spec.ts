import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'

const routes = [
  '/',
  '/tools',
  '/pricing',
  '/dashboard',
  '/media',
  '/campaigns/new',
  '/account',
  '/admin',
  '/login',
  '/register',
].map((path) => ({ path, component: { template: '<div />' } }))

const freeUser: AuthUser = {
  id: 1,
  name: 'Koke Creator',
  email: 'creator@example.com',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 13,
  created_at: null,
}

async function mountNav(user: AuthUser | null, initialized = true) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = createRouter({
    history: createMemoryHistory(),
    routes,
  })
  await router.push('/')
  await router.isReady()

  const auth = useAuthStore(pinia)
  auth.initialized = initialized
  auth.user = user

  const wrapper = mount(AppNav, {
    global: { plugins: [pinia, router] },
  })

  return { wrapper, auth }
}

describe('AppNav', () => {
  it('shows a neutral session state while authentication is being checked', async () => {
    const { wrapper } = await mountNav(null, false)

    expect(wrapper.get('[data-testid="auth-nav-loading"]').text()).toContain('Checking session')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(false)
    expect(wrapper.find('details').exists()).toBe(false)
  })
  it('shows guest links without the authenticated user menu', async () => {
    const { wrapper } = await mountNav(null)

    expect(wrapper.find('a[href="/login"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/pricing"]').text()).toBe('Pricing')
    expect(wrapper.find('a[href="/media"]').exists()).toBe(false)
    expect(wrapper.find('details').exists()).toBe(false)
  })

  it('shows the user name, free plan and complete account menu', async () => {
    const { wrapper } = await mountNav(freeUser)
    const text = wrapper.text()

    expect(wrapper.find('details').exists()).toBe(true)
    expect(wrapper.get('summary').text()).toContain('Hi, Koke')
    expect(wrapper.get('summary').text()).toContain('Free')
    expect(text).toContain('13 credits')
    expect(wrapper.find('a[href="/account"]').text()).toBe('Account')
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/media"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Media Library')
    expect(wrapper.find('a[href="/account#plan"]').text()).toBe('Manage plan')
    expect(wrapper.find('button').text()).toBe('Logout')
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin"]').exists()).toBe(false)
  })

  it('shows the Admin link only for an admin user', async () => {
    const { wrapper } = await mountNav({
      ...freeUser,
      role: 'admin',
      role_label: 'Admin',
    })

    expect(wrapper.find('a[href="/admin"]').text()).toBe('Admin')
  })

  it('shows unlimited access for an internal unlimited user', async () => {
    const { wrapper } = await mountNav({
      ...freeUser,
      role: 'internal_creator',
      role_label: 'Internal Creator',
      plan_key: 'internal_unlimited',
      plan_label: 'Internal Unlimited',
      is_unlimited: true,
      access_label: 'Unlimited internal access',
      credits_balance: 0,
    })

    expect(wrapper.get('summary').text()).toContain('Unlimited')
    expect(wrapper.text()).toContain('Unlimited internal access')
    expect(wrapper.text()).not.toContain('0 credits')
  })
})
