export type BillingInterval = 'month' | 'year' | 'one_time' | 'internal'

export interface SubscriptionPlan {
  key: string
  name: string
  short_description: string | null
  description: string | null
  price_amount: string | null
  currency: string
  billing_interval: BillingInterval
  monthly_credits: number
  is_featured: boolean
  cta_label: string | null
  features: string[]
  limits: Record<string, unknown> | null
  sort_order: number
}

export interface CurrentSubscriptionPlan {
  key: string
  name: string
  monthly_credits: number
  price_amount: string | null
  currency: string
  billing_interval: BillingInterval
  features: string[]
}

export interface AdminSubscriptionPlan extends SubscriptionPlan {
  id: number
  max_projects_per_month: number | null
  max_generations_per_month: number | null
  is_public: boolean
  is_active: boolean
  is_internal: boolean
  metadata: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
}

export interface SubscriptionPlanListResponse {
  data: SubscriptionPlan[]
}

export interface AdminSubscriptionPlanListResponse {
  data: AdminSubscriptionPlan[]
}

export interface AdminSubscriptionPlanResponse {
  data: AdminSubscriptionPlan
}

export interface AdminSubscriptionPlanUpdate {
  name: string
  short_description: string | null
  description: string | null
  price_amount: number | null
  currency: string
  billing_interval: BillingInterval
  monthly_credits: number
  max_projects_per_month: number | null
  max_generations_per_month: number | null
  is_public: boolean
  is_featured: boolean
  is_active: boolean
  is_internal: boolean
  sort_order: number
  cta_label: string | null
  features: string[]
  limits: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
}