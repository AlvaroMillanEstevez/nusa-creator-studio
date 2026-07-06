export type UserRole = 'admin' | 'internal_creator' | 'subscriber' | 'free_user'

export type PlanKey = 'free' | 'starter' | 'creator' | 'pro' | 'internal_unlimited'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  plan_key: PlanKey
  is_unlimited: boolean
  monthly_credit_allowance: number
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
