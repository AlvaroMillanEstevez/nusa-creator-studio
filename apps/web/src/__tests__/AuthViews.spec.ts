import { describe, expect, it } from 'vitest'

import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'

function testRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/tools', component: { template: '<div />' } },
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/campaigns/new', component: { template: '<div />' } },
      { path: '/login', component: LoginView },
      { path: '/register', component: RegisterView },
    ],
  })
}

describe('authentication views', () => {
  it('renders the email and password login form', async () => {
    const router = testRouter()
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.get('h1').text()).toBe('Welcome back.')
    expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email')
    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.get('button[type="submit"]').text()).toBe('Log in')
    expect(
      wrapper.findAll('a[href="/register"]').some((link) => link.text() === 'Create an account'),
    ).toBe(true)
  })

  it('renders all registration fields', async () => {
    const router = testRouter()
    await router.push('/register')
    await router.isReady()

    const wrapper = mount(RegisterView, {
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.get('h1').text()).toBe('Create your studio account.')
    expect(wrapper.get('input[name="name"]').attributes('type')).toBe('text')
    expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email')
    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.get('input[name="password_confirmation"]').attributes('type')).toBe('password')
    expect(wrapper.findAll('a[href="/login"]').some((link) => link.text() === 'Log in')).toBe(
      true,
    )
  })
})
