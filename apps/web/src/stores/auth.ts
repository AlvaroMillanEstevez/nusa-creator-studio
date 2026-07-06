import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { ApiError } from '@/services/api'
import {
  fetchCurrentUser,
  loginUser,
  logoutUser,
  registerUser,
} from '@/services/auth'
import type { AuthUser, LoginPayload, RegisterPayload } from '@/types/auth'

function errorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError ? error.message : fallback
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const isLoading = ref(false)
  const error = ref('')
  const initialized = ref(false)
  let initialization: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchMe(): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      user.value = await fetchCurrentUser()
    } catch (requestError: unknown) {
      user.value = null

      if (!(requestError instanceof ApiError && requestError.status === 401)) {
        error.value = errorMessage(requestError, 'We could not check your session.')
      }
    } finally {
      initialized.value = true
      isLoading.value = false
    }
  }

  async function initialize(): Promise<void> {
    if (initialized.value) {
      return
    }

    initialization ??= fetchMe().finally(() => {
      initialization = null
    })

    await initialization
  }

  async function login(payload: LoginPayload): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      user.value = await loginUser(payload)
      initialized.value = true
    } catch (requestError: unknown) {
      error.value = errorMessage(requestError, 'We could not log you in. Please try again.')
      throw requestError
    } finally {
      isLoading.value = false
    }
  }

  async function register(payload: RegisterPayload): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      user.value = await registerUser(payload)
      initialized.value = true
    } catch (requestError: unknown) {
      error.value = errorMessage(requestError, 'We could not create your account. Please try again.')
      throw requestError
    } finally {
      isLoading.value = false
    }
  }

  async function logout(): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      await logoutUser()
    } catch (requestError: unknown) {
      error.value = errorMessage(requestError, 'We could not complete logout cleanly.')
      throw requestError
    } finally {
      user.value = null
      initialized.value = true
      isLoading.value = false
    }
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    error,
    initialized,
    initialize,
    fetchMe,
    login,
    register,
    logout,
  }
})
