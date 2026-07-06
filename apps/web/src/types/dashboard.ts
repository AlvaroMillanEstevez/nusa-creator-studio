import type { ProjectStatus, ProjectType } from '@/types/project'

export interface DashboardUser {
  id: number
  name: string
  email: string
  credits_balance: number
}

export interface DashboardStats {
  total_projects: number
  total_generations: number
  completed_generations: number
  failed_generations: number
  draft_projects: number
  total_credits_spent: number
  affiliate_campaign_projects: number
  video_enhancer_projects: number
  product_to_video_projects: number
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
  stats: DashboardStats
  recent_projects: DashboardRecentProject[]
  recent_generations: DashboardRecentGeneration[]
}
