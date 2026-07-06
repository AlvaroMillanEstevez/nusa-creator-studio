import { apiGet } from '@/services/api'
import type { SubscriptionPlan, SubscriptionPlanListResponse } from '@/types/plan'

export async function getSubscriptionPlans(): Promise<SubscriptionPlan[]> {
  const response = await apiGet<SubscriptionPlanListResponse>('/plans')

  return response.data
}