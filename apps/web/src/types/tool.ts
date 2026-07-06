export type ToolStatus = 'available' | 'coming_soon' | 'maintenance' | 'disabled'

export interface CreatorTool {
  key: string
  title: string
  short_description: string
  long_description: string | null
  category: string
  status: ToolStatus
  route: string | null
  credit_cost: number
  credit_cost_label: string | null
  badge_label: string | null
  is_recommended: boolean
  sort_order: number
}

export interface AdminCreatorTool extends CreatorTool {
  id: number
  is_visible: boolean
  metadata: Record<string, unknown> | null
  created_at: string | null
  updated_at: string | null
}

export interface CreatorToolListResponse {
  data: CreatorTool[]
}

export interface AdminCreatorToolListResponse {
  data: AdminCreatorTool[]
}

export interface AdminCreatorToolResponse {
  data: AdminCreatorTool
}

export interface AdminCreatorToolUpdate {
  title: string
  short_description: string
  long_description: string | null
  category: string
  status: ToolStatus
  route: string | null
  credit_cost: number
  credit_cost_label: string | null
  badge_label: string | null
  is_recommended: boolean
  is_visible: boolean
  sort_order: number
}