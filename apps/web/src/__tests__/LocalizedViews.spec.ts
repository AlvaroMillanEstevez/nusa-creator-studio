import { afterEach, describe, expect, it, vi } from 'vitest'

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { setLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/auth'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'
import MediaLibraryView from '@/views/MediaLibraryView.vue'
import RegisterView from '@/views/RegisterView.vue'

const user: AuthUser = {
  id: 1,
  name: 'Nusa Creator',
  email: 'creator@example.com',
  role: 'free_user',
  role_label: 'Free User',
  plan_key: 'free',
  plan_label: 'Free',
  is_unlimited: false,
  access_label: 'Current plan credits',
  monthly_credit_allowance: 20,
  credits_balance: 12,
  preferred_locale: 'es',
  created_at: null,
}

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function testRouter() {
  const placeholder = { template: '<div />' }

  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: HomeView },
      { path: '/tools', component: placeholder },
      { path: '/dashboard', component: placeholder },
      { path: '/pricing', component: placeholder },
      { path: '/media', component: MediaLibraryView },
      { path: '/account', component: placeholder },
      { path: '/login', component: LoginView },
      { path: '/register', component: RegisterView },
      { path: '/campaigns/new', component: placeholder },
    ],
  })
}

async function mountWithAuth(component: object, path: string, authenticated = false) {
  const pinia = createPinia()
  const auth = useAuthStore(pinia)
  auth.user = authenticated ? user : null
  auth.initialized = true

  const router = testRouter()
  await router.push(path)
  await router.isReady()

  return mount(component, {
    global: { plugins: [pinia, router] },
  })
}

describe('localized core views', () => {
  afterEach(() => {
    setLocale('en', false)
    vi.unstubAllGlobals()
  })

  it('renders the home entry page in Indonesian', async () => {
    setLocale('id', false)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockResolvedValue(jsonResponse({ status: 'ok', version: 'v1' })),
    )

    const wrapper = await mountWithAuth(HomeView, '/')
    await flushPromises()

    expect(wrapper.text()).toContain('Studio konten produk Anda, dalam satu tempat.')
    expect(wrapper.text()).toContain('Buka Creator Studio')
    expect(wrapper.text()).toContain('Lihat dashboard')
  })

  it('renders login and register auth copy in Spanish', async () => {
    setLocale('es', false)

    const login = await mountWithAuth(LoginView, '/login')
    expect(login.text()).toContain('Iniciar sesión')
    expect(login.text()).toContain('Contraseña')
    expect(login.text()).toContain('¿Nuevo en Nusa?')

    login.unmount()

    const register = await mountWithAuth(RegisterView, '/register')
    expect(register.text()).toContain('Crea tu cuenta de estudio.')
    expect(register.text()).toContain('Confirmar contraseña')
    expect(register.text()).toContain('¿Ya tienes una cuenta?')
  })

  it('renders the media library empty state in Spanish', async () => {
    setLocale('es', false)
    vi.stubGlobal('scrollTo', vi.fn<() => void>())
    vi.stubGlobal(
      'fetch',
      vi.fn<typeof fetch>().mockImplementation(async (input) => {
        const url = String(input)

        if (url.endsWith('/media-assets/stats')) {
          return jsonResponse({
            data: {
              total_assets: 0,
              total_size_bytes: 0,
              total_size_label: '0 B',
              images_count: 0,
              videos_count: 0,
              audio_count: 0,
              documents_count: 0,
              other_count: 0,
              by_usage_role: {},
              recent_uploads_count: 0,
            },
          })
        }

        return jsonResponse({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
      }),
    )

    const wrapper = await mountWithAuth(MediaLibraryView, '/media', true)
    await flushPromises()

    expect(wrapper.text()).toContain('Biblioteca de media')
    expect(wrapper.text()).toContain('Aún no hay activos de media.')
    expect(wrapper.text()).toContain('Explorar herramientas')
  })
})
