import { apiDelete, apiGet, apiPostForm } from '@/services/api'
import type {
  DeleteMediaAssetResponse,
  MediaAsset,
  MediaAssetFilters,
  MediaAssetListResponse,
  MediaAssetResponse,
  MediaAssetType,
  MediaAssetUsageRole,
} from '@/types/media'

export async function uploadMediaAsset(
  file: File,
  projectId?: number | string,
  usageRole?: MediaAssetUsageRole,
  type?: MediaAssetType,
): Promise<MediaAsset> {
  const payload = new FormData()
  payload.append('file', file)

  if (projectId !== undefined) {
    payload.append('project_id', String(projectId))
  }

  if (usageRole !== undefined) {
    payload.append('usage_role', usageRole)
  }

  if (type !== undefined) {
    payload.append('type', type)
  }

  const response = await apiPostForm<MediaAssetResponse>('/media-assets', payload)

  return response.data
}

export function listMediaAssets(
  filters: MediaAssetFilters = {},
): Promise<MediaAssetListResponse> {
  const search = new URLSearchParams()

  if (filters.project_id !== undefined) {
    search.set('project_id', String(filters.project_id))
  }

  if (filters.type) {
    search.set('type', filters.type)
  }

  if (filters.usage_role) {
    search.set('usage_role', filters.usage_role)
  }

  const query = search.toString()

  return apiGet<MediaAssetListResponse>('/media-assets' + (query ? '?' + query : ''))
}

export function listProjectMediaAssets(
  projectId: number | string,
): Promise<MediaAssetListResponse> {
  return apiGet<MediaAssetListResponse>(
    '/projects/' + encodeURIComponent(String(projectId)) + '/media-assets',
  )
}

export function deleteMediaAsset(id: number | string): Promise<DeleteMediaAssetResponse> {
  return apiDelete<DeleteMediaAssetResponse>('/media-assets/' + encodeURIComponent(String(id)))
}