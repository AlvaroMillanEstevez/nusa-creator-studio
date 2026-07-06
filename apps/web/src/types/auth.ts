export interface AuthUser {
  id: number
  name: string
  email: string
  credits_balance: number
  created_at: string | null
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  user: AuthUser
}

export interface LogoutResponse {
  message: string
}
