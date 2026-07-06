import { apiGet, apiPatch } from '@/services/api'
import type {
  AdminSummary,
  AdminUserAccessUpdate,
  AdminUserDetail,
  AdminUserDetailResponse,
  AdminUserFilters,
  AdminUserListResponse,
} from '@/types/admin'

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
