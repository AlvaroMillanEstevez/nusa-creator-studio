import { apiGet, apiPost, ensureCsrfCookie } from '@/services/api'
import type {
  AuthResponse,
  AuthUser,
  LoginPayload,
  LogoutResponse,
  RegisterPayload,
} from '@/types/auth'

export async function registerUser(payload: RegisterPayload): Promise<AuthUser> {
  await ensureCsrfCookie()
  const response = await apiPost<AuthResponse, RegisterPayload>('/auth/register', payload)

  return response.user
}

export async function loginUser(payload: LoginPayload): Promise<AuthUser> {
  await ensureCsrfCookie()
  const response = await apiPost<AuthResponse, LoginPayload>('/auth/login', payload)

  return response.user
}

export async function logoutUser(): Promise<void> {
  await ensureCsrfCookie()
  await apiPost<LogoutResponse, Record<string, never>>('/auth/logout', {})
}

export async function fetchCurrentUser(): Promise<AuthUser> {
  const response = await apiGet<AuthResponse>('/auth/me')

  return response.user
}
