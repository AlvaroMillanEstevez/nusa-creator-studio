import { apiGet, apiPatch } from '@/services/api'
import type {
  AdminSummary,
  AdminUserAccessUpdate,
  AdminUserDetail,
  AdminUserDetailResponse,
  AdminUserFilters,
  AdminUserListResponse,
} from '@/types/admin'
import type {
  AdminCreatorTool,
  AdminCreatorToolListResponse,
  AdminCreatorToolResponse,
  AdminCreatorToolUpdate,
} from '@/types/tool'

export function getAdminSummary(): Promise<AdminSummary> {
  return apiGet<AdminSummary>('/admin/summary')
}

export function listAdminUsers(filters: AdminUserFilters = {}): Promise<AdminUserListResponse> {
  const search = new URLSearchParams()

  if (filters.search) {
    search.set('search', filters.search)
  }

  if (filters.role) {
    search.set('role', filters.role)
  }

  if (filters.plan_key) {
    search.set('plan_key', filters.plan_key)
  }

  if (filters.is_unlimited !== undefined && filters.is_unlimited !== '') {
    search.set('is_unlimited', filters.is_unlimited)
  }

  if (filters.page && filters.page > 1) {
    search.set('page', String(filters.page))
  }

  const query = search.toString()

  return apiGet<AdminUserListResponse>('/admin/users' + (query ? '?' + query : ''))
}

export async function getAdminUser(userId: number | string): Promise<AdminUserDetail> {
  const response = await apiGet<AdminUserDetailResponse>('/admin/users/' + userId)

  return response.data
}

export async function updateAdminUserAccess(
  userId: number | string,
  payload: AdminUserAccessUpdate,
): Promise<AdminUserDetail> {
  const response = await apiPatch<AdminUserDetailResponse, AdminUserAccessUpdate>(
    '/admin/users/' + userId + '/access',
    payload,
  )

  return response.data
}

export async function getAdminTools(): Promise<AdminCreatorTool[]> {
  const response = await apiGet<AdminCreatorToolListResponse>('/admin/tools')

  return response.data
}

export async function getAdminTool(key: string): Promise<AdminCreatorTool> {
  const response = await apiGet<AdminCreatorToolResponse>(
    '/admin/tools/' + encodeURIComponent(key),
  )

  return response.data
}

export async function updateAdminTool(
  key: string,
  payload: AdminCreatorToolUpdate,
): Promise<AdminCreatorTool> {
  const response = await apiPatch<AdminCreatorToolResponse, AdminCreatorToolUpdate>(
    '/admin/tools/' + encodeURIComponent(key),
    payload,
  )

  return response.data
}