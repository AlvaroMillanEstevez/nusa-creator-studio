import { apiGet, apiPost } from '@/services/api'
import type {
  CampaignFormPayload,
  CampaignGenerationResponse,
  CampaignProject,
  CampaignProjectSettings,
  CreateProjectPayload,
  ProjectGenerationHistoryResponse,
} from '@/types/project'

interface ResourceResponse<T> {
  data: T
}

export async function createCampaignProject(
  campaign: CampaignFormPayload,
): Promise<CampaignProject> {
  const payload: CreateProjectPayload<CampaignFormPayload, CampaignProjectSettings> = {
    title: campaign.product_name,
    type: 'affiliate_campaign',
    status: 'draft',
    input_payload: campaign,
    settings: {
      platform: 'tiktok',
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<CampaignProject>,
    CreateProjectPayload<CampaignFormPayload, CampaignProjectSettings>
  >('/projects', payload)

  return response.data
}

export async function getCampaignProject(projectId: number | string): Promise<CampaignProject> {
  const response = await apiGet<ResourceResponse<CampaignProject>>('/projects/' + projectId)

  return response.data
}

export function generateCampaignProject(
  projectId: number | string,
): Promise<CampaignGenerationResponse> {
  return apiPost<CampaignGenerationResponse, Record<string, never>>(
    '/projects/' + projectId + '/generate-campaign',
    {},
  )
}

export function getProjectGenerations(
  projectId: number | string,
): Promise<ProjectGenerationHistoryResponse> {
  return apiGet<ProjectGenerationHistoryResponse>('/projects/' + projectId + '/generations')
}
