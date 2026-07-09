import type { AuthUser } from '@/types/auth'
import type { ProjectStatus, ProjectType } from '@/types/project'

export type DashboardUser = Omit<AuthUser, 'created_at'>

export interface DashboardAccess {
  label: string
  is_unlimited: boolean
  monthly_credit_allowance: number
  credits_balance: number
  can_generate_without_credits: boolean
}

export interface DashboardStats {
  total_projects: number
  total_generations: number
  completed_generations: number
  failed_generations: number
  draft_projects: number
  workspace_projects: number
  total_credits_spent: number
  affiliate_campaign_projects: number
  video_enhancer_projects: number
  product_to_video_projects: number
  fashion_try_on_projects: number
  motion_ai_video_projects: number
}

export interface DashboardRecentProject {
  id: number
  title: string
  type: ProjectType
  status: ProjectStatus
  created_at: string | null
  updated_at: string | null
  generations_count: number
  latest_generation_status: ProjectStatus | null
  latest_generation_created_at: string | null
}

export interface DashboardRecentGeneration {
  id: number
  project_id: number | null
  project_title: string | null
  project_type: ProjectType | null
  job_type: string
  provider: string | null
  status: ProjectStatus
  credits_charged: number
  cost_usd: string
  created_at: string | null
  finished_at: string | null
}

export interface DashboardSummary {
  user: DashboardUser
  access: DashboardAccess
  stats: DashboardStats
  recent_projects: DashboardRecentProject[]
  recent_generations: DashboardRecentGeneration[]
}
