import { apiGet, apiPatch, apiPost } from '@/services/api'
import type {
  AiVoiceoverFormPayload,
  AiVoiceoverGenerationResponse,
  AiVoiceoverProject,
  AutoVideoEditorGenerationResponse,
  AutoVideoAdGenerationResponse,
  CampaignFormPayload,
  CampaignGenerationResponse,
  CampaignProject,
  CampaignProjectSettings,
  CreateProjectPayload,
  CreatorProject,
  CreatorProjectSettings,
  CreatorGeneration,
  GenerateAiVoiceoverPayload,
  GenerateAutoVideoEditPayload,
  GenerateAutoVideoAdPayload,
  GenerateMotionAiVideoPayload,
  GenerateVideoToCampaignPayload,
  FashionTryOnFormPayload,
  FashionTryOnGenerationResponse,
  FashionTryOnProject,
  MotionVideoFormPayload,
  MotionVideoGenerationResponse,
  MotionVideoProject,
  ProjectActionsResponse,
  ProjectGenerationHistoryResponse,
  ProductToVideoForm,
  ProductToVideoGenerationResponse,
  ProductToVideoProject,
  VideoEnhancerForm,
  VideoEnhancerGenerationResponse,
  VideoToCampaignGenerationResponse,
  VideoEnhancerProject,
  WorkspaceFormPayload,
  WorkspaceProject,
} from '@/types/project'

interface ResourceResponse<T> {
  data: T
}

export async function createWorkspaceProject(
  brief: WorkspaceFormPayload,
  title: string,
): Promise<WorkspaceProject> {
  const payload: CreateProjectPayload<WorkspaceFormPayload, CreatorProjectSettings> = {
    title,
    type: 'workspace',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: brief.platform,
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<WorkspaceProject>,
    CreateProjectPayload<WorkspaceFormPayload, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
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

export async function createVideoEnhancerProject(
  brief: VideoEnhancerForm,
): Promise<VideoEnhancerProject> {
  const payload: CreateProjectPayload<VideoEnhancerForm, CreatorProjectSettings> = {
    title: brief.video_title,
    type: 'video_enhancer',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: 'tiktok',
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<VideoEnhancerProject>,
    CreateProjectPayload<VideoEnhancerForm, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
}
export async function createProductToVideoProject(
  brief: ProductToVideoForm,
): Promise<ProductToVideoProject> {
  const payload: CreateProjectPayload<ProductToVideoForm, CreatorProjectSettings> = {
    title: brief.product_name,
    type: 'product_to_video',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: 'tiktok',
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<ProductToVideoProject>,
    CreateProjectPayload<ProductToVideoForm, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
}

export async function createAiVoiceoverProject(
  brief: AiVoiceoverFormPayload,
): Promise<AiVoiceoverProject> {
  const payload: CreateProjectPayload<AiVoiceoverFormPayload, CreatorProjectSettings> = {
    title: brief.title,
    type: 'ai_voiceover',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: brief.platform,
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<AiVoiceoverProject>,
    CreateProjectPayload<AiVoiceoverFormPayload, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
}

export async function createFashionTryOnProject(
  brief: FashionTryOnFormPayload,
): Promise<FashionTryOnProject> {
  const payload: CreateProjectPayload<FashionTryOnFormPayload, CreatorProjectSettings> = {
    title: brief.title,
    type: 'fashion_try_on',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: brief.platform,
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<FashionTryOnProject>,
    CreateProjectPayload<FashionTryOnFormPayload, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
}

export async function createMotionVideoProject(
  brief: MotionVideoFormPayload,
): Promise<MotionVideoProject> {
  const payload: CreateProjectPayload<MotionVideoFormPayload, CreatorProjectSettings> = {
    title: brief.title,
    type: 'motion_ai_video',
    status: 'draft',
    input_payload: brief,
    settings: {
      platform: brief.platform,
      format: 'vertical_9_16',
    },
  }

  const response = await apiPost<
    ResourceResponse<MotionVideoProject>,
    CreateProjectPayload<MotionVideoFormPayload, CreatorProjectSettings>
  >('/projects', payload)

  return response.data
}
export async function getCampaignProject(projectId: number | string): Promise<CampaignProject> {
  const response = await apiGet<ResourceResponse<CampaignProject>>('/projects/' + projectId)

  return response.data
}

export async function getCreatorProject(projectId: number | string): Promise<CreatorProject> {
  const response = await apiGet<ResourceResponse<CreatorProject>>('/projects/' + projectId)

  return response.data
}

export async function updateProject(
  projectId: number | string,
  payload: Partial<CreateProjectPayload<unknown, unknown>>,
): Promise<CreatorProject> {
  const response = await apiPatch<
    ResourceResponse<CreatorProject>,
    Partial<CreateProjectPayload<unknown, unknown>>
  >('/projects/' + projectId, payload)

  return response.data
}

export function getGeneration(generationId: number | string): Promise<CreatorGeneration> {
  return apiGet<ResourceResponse<CreatorGeneration>>('/generations/' + generationId).then(
    (response) => response.data,
  )
}

export function generateCampaignProject(
  projectId: number | string,
): Promise<CampaignGenerationResponse> {
  return apiPost<CampaignGenerationResponse, Record<string, never>>(
    '/projects/' + projectId + '/generate-campaign',
    {},
  )
}

export function generateVideoEnhancerProject(
  projectId: number | string,
): Promise<VideoEnhancerGenerationResponse> {
  return apiPost<VideoEnhancerGenerationResponse, Record<string, never>>(
    '/projects/' + projectId + '/generate-video-enhancer',
    {},
  )
}

export function generateVideoToCampaign(
  projectId: number | string,
  payload: GenerateVideoToCampaignPayload = {},
): Promise<VideoToCampaignGenerationResponse> {
  return apiPost<VideoToCampaignGenerationResponse, GenerateVideoToCampaignPayload>(
    '/projects/' + projectId + '/generate-video-campaign',
    payload,
  )
}

export function generateAutoVideoEdit(
  projectId: number | string,
  payload: GenerateAutoVideoEditPayload = {},
): Promise<AutoVideoEditorGenerationResponse> {
  return apiPost<AutoVideoEditorGenerationResponse, GenerateAutoVideoEditPayload>(
    '/projects/' + projectId + '/generate-auto-video-edit',
    payload,
  )
}
export function generateAutoVideoAd(
  projectId: number | string,
  payload: GenerateAutoVideoAdPayload,
): Promise<AutoVideoAdGenerationResponse> {
  return apiPost<AutoVideoAdGenerationResponse, GenerateAutoVideoAdPayload>(
    '/projects/' + projectId + '/generate-auto-video-ad',
    payload,
  )
}
export function generateProductVideoScript(
  projectId: number | string,
): Promise<ProductToVideoGenerationResponse> {
  return apiPost<ProductToVideoGenerationResponse, Record<string, never>>(
    '/projects/' + projectId + '/generate-product-video-script',
    {},
  )
}

export function generateAiVoiceover(
  projectId: number | string,
  payload: GenerateAiVoiceoverPayload = {},
): Promise<AiVoiceoverGenerationResponse> {
  return apiPost<AiVoiceoverGenerationResponse, GenerateAiVoiceoverPayload>(
    '/projects/' + projectId + '/generate-voiceover',
    payload,
  )
}

export function generateFashionTryOn(
  projectId: number | string,
): Promise<FashionTryOnGenerationResponse> {
  return apiPost<FashionTryOnGenerationResponse, Record<string, never>>(
    '/projects/' + projectId + '/generate-fashion-try-on',
    {},
  )
}

export function generateMotionVideo(
  projectId: number | string,
  payload: GenerateMotionAiVideoPayload,
): Promise<MotionVideoGenerationResponse> {
  return apiPost<MotionVideoGenerationResponse, GenerateMotionAiVideoPayload>(
    '/projects/' + projectId + '/generate-motion-ai-video',
    payload,
  )
}
export function getProjectGenerations(
  projectId: number | string,
): Promise<ProjectGenerationHistoryResponse> {
  return apiGet<ProjectGenerationHistoryResponse>('/projects/' + projectId + '/generations')
}

export function getProjectActions(projectId: number | string): Promise<ProjectActionsResponse> {
  return apiGet<ProjectActionsResponse>('/projects/' + projectId + '/actions')
}
