import { apiGet } from '@/services/api'
import type { CreatorTool, CreatorToolListResponse } from '@/types/tool'

export async function getCreatorTools(): Promise<CreatorTool[]> {
  const response = await apiGet<CreatorToolListResponse>('/tools')

  return response.data
}