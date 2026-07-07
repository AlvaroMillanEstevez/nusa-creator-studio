import type { PlanKey, UserRole } from '@/types/auth'
import type { ProjectStatus, ProjectType } from '@/types/project'

export interface AdminSummary {
  total_users: number
  total_admins: number
  total_internal_creators: number
  total_subscribers: number
  total_free_users: number
  total_projects: number
  total_generations: number
  total_completed_generations: number
  total_failed_generations: number
  total_credits_spent: number
  total_unlimited_generations: number
  projects_by_type: Record<ProjectType, number>
  generations_by_job_type: {
    generate_affiliate_campaign: number
    generate_video_enhancer: number
    generate_product_video_script: number
    generate_ai_voiceover: number
    generate_fashion_try_on: number
    generate_motion_ai_video: number
  }
}

export interface AdminUserListItem {
  id: number
  name: string
  email: string
  role: UserRole
  plan_key: PlanKey
  is_unlimited: boolean
  monthly_credit_allowance: number
  credits_balance: number
  projects_count: number
  generations_count: number
  created_at: string | null
  updated_at: string | null
}

export interface AdminUserFilters {
  search?: string
  role?: UserRole | ''
  plan_key?: PlanKey | ''
  is_unlimited?: '' | '0' | '1'
  page?: number
}

export interface AdminUserListResponse {
  data: AdminUserListItem[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface AdminUserStats {
  projects_count: number
  generations_count: number
  completed_generations_count: number
  failed_generations_count: number
  credits_spent: number
}

export interface AdminRecentProject {
  id: number
  title: string
  type: ProjectType
  status: ProjectStatus
  created_at: string | null
}

export interface AdminRecentGeneration {
  id: number
  project_id: number | null
  project_title: string | null
  job_type: string
  provider: string | null
  status: ProjectStatus
  credits_charged: number
  created_at: string | null
}

export interface AdminUserDetail {
  id: number
  name: string
  email: string
  role: UserRole
  plan_key: PlanKey
  is_unlimited: boolean
  monthly_credit_allowance: number
  credits_balance: number
  created_at: string | null
  updated_at: string | null
  stats: AdminUserStats
  recent_projects: AdminRecentProject[]
  recent_generations: AdminRecentGeneration[]
}

export interface AdminUserDetailResponse {
  data: AdminUserDetail
}

export interface AdminUserAccessUpdate {
  role: UserRole
  plan_key: PlanKey
  is_unlimited: boolean
  monthly_credit_allowance: number
  credits_balance: number
}
