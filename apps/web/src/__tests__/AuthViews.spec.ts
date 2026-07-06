import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'

import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'

const authenticatedUser: AuthUser = {
  id: 1,
  name: 'Nusa Creator',
  email: 'creator@example.test',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 20,
  created_at: null,
}

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

function mockAuthFailure(message: string, status = 500) {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
    const url =
      typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

    if (url.endsWith('/sanctum/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }

    return new Response(JSON.stringify({ message }), {
      status,
      headers: { 'Content-Type': 'application/json' },
    })
  })
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

function mockAuthSuccess() {
  const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
    const url =
      typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

    if (url.endsWith('/sanctum/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }

    return new Response(JSON.stringify({ user: authenticatedUser }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })
  })
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

describe('authentication views', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders login inside the minimal auth layout without AppNav', async () => {
    const router = testRouter()
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.get('h1').text()).toBe('Welcome back.')
    expect(wrapper.find('[aria-label="Primary navigation"]').exists()).toBe(false)
    expect(wrapper.get('[aria-label="Authentication navigation"]').attributes('aria-label')).toBe(
      'Authentication navigation',
    )
    expect(wrapper.findAll('a[href="/"]').some((link) => link.text() === 'Back to Home')).toBe(true)
    expect(wrapper.findAll('a[href="/register"]').some((link) => link.text() === 'Register')).toBe(
      true,
    )
    expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email')
    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.get('button[type="submit"]').text()).toBe('Log in')
    expect(
      wrapper.findAll('a[href="/register"]').some((link) => link.text() === 'Create an account'),
    ).toBe(true)
  })

  it('renders registration inside the minimal auth layout without AppNav', async () => {
    const router = testRouter()
    await router.push('/register')
    await router.isReady()

    const wrapper = mount(RegisterView, {
      global: { plugins: [createPinia(), router] },
    })

    expect(wrapper.get('h1').text()).toBe('Create your studio account.')
    expect(wrapper.find('[aria-label="Primary navigation"]').exists()).toBe(false)
    expect(wrapper.get('[aria-label="Authentication navigation"]').attributes('aria-label')).toBe(
      'Authentication navigation',
    )
    expect(wrapper.findAll('a[href="/"]').some((link) => link.text() === 'Back to Home')).toBe(true)
    expect(wrapper.findAll('a[href="/login"]').some((link) => link.text() === 'Log in')).toBe(true)
    expect(wrapper.get('input[name="name"]').attributes('type')).toBe('text')
    expect(wrapper.get('input[name="email"]').attributes('type')).toBe('email')
    expect(wrapper.get('input[name="password"]').attributes('type')).toBe('password')
    expect(wrapper.get('input[name="password_confirmation"]').attributes('type')).toBe('password')
  })

  it('clears a login error when navigating to registration', async () => {
    const router = testRouter()
    const pinia = createPinia()
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(
      { template: '<RouterView />' },
      {
        global: { plugins: [pinia, router] },
      },
    )
    const auth = useAuthStore(pinia)

    auth.error = 'The provided credentials are incorrect.'
    await nextTick()

    expect(wrapper.get('[role="alert"]').text()).toBe('The provided credentials are incorrect.')

    await router.push('/register')
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Create your studio account.')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(auth.error).toBe('')
  })

  it('clears a registration error when navigating to login', async () => {
    const router = testRouter()
    const pinia = createPinia()
    await router.push('/register')
    await router.isReady()

    const wrapper = mount(
      { template: '<RouterView />' },
      {
        global: { plugins: [pinia, router] },
      },
    )
    const auth = useAuthStore(pinia)

    auth.error = 'The email has already been taken.'
    await nextTick()

    expect(wrapper.get('[role="alert"]').text()).toBe('The email has already been taken.')

    await router.push('/login')
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Welcome back.')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(auth.error).toBe('')
  })

  it('redirects to a safe internal destination after login', async () => {
    mockAuthSuccess()
    const router = testRouter()
    await router.push('/login?redirect=/campaigns/new')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="email"]').setValue('creator@example.test')
    await wrapper.get('input[name="password"]').setValue('strong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/campaigns/new')
  })

  it('ignores an unsafe external redirect after login', async () => {
    mockAuthSuccess()
    const router = testRouter()
    await router.push('/login?redirect=//malicious.example/path')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="email"]').setValue('creator@example.test')
    await wrapper.get('input[name="password"]').setValue('strong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/dashboard')
  })

  it('shows a safe login error instead of a raw SQL exception', async () => {
    mockAuthFailure(
      'SQLSTATE[23505]: insert into "users" ("email") values (?) in /var/www/vendor/File.php:42',
    )
    const router = testRouter()
    await router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="email"]').setValue('creator@example.test')
    await wrapper.get('input[name="password"]').setValue('strong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Something went wrong. Please try again.')
    expect(wrapper.text()).not.toContain('SQLSTATE')
    expect(wrapper.text()).not.toContain('vendor')
  })

  it('shows a safe registration error instead of an internal class message', async () => {
    mockAuthFailure('App\\Models\\User failed in /var/www/app/Services/RegisterUser.php:18')
    const router = testRouter()
    await router.push('/register')
    await router.isReady()

    const wrapper = mount(RegisterView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="name"]').setValue('Nusa Creator')
    await wrapper.get('input[name="email"]').setValue('creator@example.test')
    await wrapper.get('input[name="password"]').setValue('strong-password')
    await wrapper.get('input[name="password_confirmation"]').setValue('strong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Something went wrong. Please try again.')
    expect(wrapper.text()).not.toContain('App\\Models')
    expect(wrapper.text()).not.toContain('RegisterUser.php')
  })

  it('keeps useful backend validation errors visible', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockImplementation(async (input) => {
      const url =
        typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url

      if (url.endsWith('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }

      return new Response(
        JSON.stringify({
          message: 'The given data was invalid.',
          errors: { email: ['The email has already been taken.'] },
        }),
        {
          status: 422,
          headers: { 'Content-Type': 'application/json' },
        },
      )
    })
    vi.stubGlobal('fetch', fetchMock)

    const router = testRouter()
    await router.push('/register')
    await router.isReady()

    const wrapper = mount(RegisterView, {
      global: { plugins: [createPinia(), router] },
    })

    await wrapper.get('input[name="name"]').setValue('Nusa Creator')
    await wrapper.get('input[name="email"]').setValue('taken@example.test')
    await wrapper.get('input[name="password"]').setValue('strong-password')
    await wrapper.get('input[name="password_confirmation"]').setValue('strong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('The email has already been taken.')
  })
})
