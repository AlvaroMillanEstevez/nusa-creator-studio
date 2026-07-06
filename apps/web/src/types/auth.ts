import type { CurrentSubscriptionPlan } from '@/types/plan'

export type UserRole = 'admin' | 'internal_creator' | 'subscriber' | 'free_user'

export type PlanKey = 'free' | 'starter' | 'creator' | 'pro' | 'internal_unlimited'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  plan_key: PlanKey
  plan_label: string
  is_unlimited: boolean
  access_label: string
  monthly_credit_allowance: number
  credits_balance: number
  current_plan?: CurrentSubscriptionPlan | null
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

export interface ProfileUpdatePayload {
  name: string
  email: string
}

export interface PasswordUpdatePayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  user: AuthUser
}

export interface PasswordUpdateResponse {
  message: string
}

export interface LogoutResponse {
  message: string
}
