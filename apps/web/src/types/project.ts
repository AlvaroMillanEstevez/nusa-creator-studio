export const campaignLanguageOptions = [
  { value: 'english', label: 'English' },
  { value: 'indonesian', label: 'Indonesian' },
  { value: 'spanish', label: 'Spanish' },
] as const

export const campaignToneOptions = [
  { value: 'natural', label: 'Natural' },
  { value: 'funny', label: 'Funny' },
  { value: 'premium', label: 'Premium' },
  { value: 'problem_solution', label: 'Problem / solution' },
  { value: 'review', label: 'Review' },
] as const

export const videoEnhancerStyleOptions = [
  { value: 'natural', label: 'Natural' },
  { value: 'energetic', label: 'Energetic' },
  { value: 'premium', label: 'Premium' },
  { value: 'educational', label: 'Educational' },
  { value: 'review', label: 'Review' },
] as const

export type CampaignLanguage = (typeof campaignLanguageOptions)[number]['value']
export type CampaignTone = (typeof campaignToneOptions)[number]['value']
export type VideoEnhancerStyle = (typeof videoEnhancerStyleOptions)[number]['value']

export type ProjectType = 'affiliate_campaign' | 'video_enhancer' | 'product_to_video'
export type ProjectStatus = 'draft' | 'pending' | 'processing' | 'completed' | 'failed'

export interface CampaignFormPayload {
  product_name: string
  product_description: string
  target_audience: string
  language: CampaignLanguage
  tone: CampaignTone
}

export interface VideoEnhancerForm {
  video_title: string
  video_context: string
  spoken_summary: string
  product_name: string
  target_audience: string
  language: CampaignLanguage
  style: VideoEnhancerStyle
}

export interface CreatorProjectSettings {
  platform: 'tiktok'
  format: 'vertical_9_16'
}

export type CampaignProjectSettings = CreatorProjectSettings

export interface Project<
  TInputPayload = Record<string, unknown>,
  TSettings = Record<string, unknown>,
> {
  id: number
  user_id: number
  title: string
  type: ProjectType
  status: ProjectStatus
  input_payload: TInputPayload | null
  settings: TSettings | null
  created_at: string | null
  updated_at: string | null
}

export interface CreateProjectPayload<TInputPayload, TSettings> {
  title: string
  type: ProjectType
  status: ProjectStatus
  input_payload: TInputPayload
  settings: TSettings
}

type TypedProject<TType extends ProjectType, TInputPayload> = Omit<
  Project<TInputPayload, CreatorProjectSettings>,
  'type'
> & {
  type: TType
}

export type CampaignProject = TypedProject<'affiliate_campaign', CampaignFormPayload>
export type VideoEnhancerProject = TypedProject<'video_enhancer', VideoEnhancerForm>
export type CreatorProject = CampaignProject | VideoEnhancerProject

export interface CampaignScriptScene {
  timestamp: string
  voiceover: string
  visual_direction: string
  on_screen_text: string
}

export interface CampaignGenerationResult {
  hooks: string[]
  script: {
    title: string
    duration_seconds: number
    scenes: CampaignScriptScene[]
  }
  caption: string
  hashtags: string[]
  cta: string
  notes: string[]
}

export interface VideoEnhancerSubtitleCue {
  timestamp: string
  text: string
}

export interface VideoEnhancerCut {
  timestamp: string
  edit_direction: string
  reason: string
}

export interface VideoEnhancerResult {
  improved_caption: string
  subtitle_plan: VideoEnhancerSubtitleCue[]
  cut_plan: VideoEnhancerCut[]
  on_screen_text: string[]
  posting_text: string
  hashtags: string[]
  cta: string
  notes: string[]
}

export type GenerationResult = CampaignGenerationResult | VideoEnhancerResult

export interface Generation<TResult = GenerationResult> {
  id: number
  user_id: number
  project_id: number | null
  job_type: string
  provider: string | null
  status: ProjectStatus
  cost_usd: string
  credits_charged: number
  request_payload: Record<string, unknown> | null
  result_payload: TResult | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
}

export interface GenerationResponse<TResult extends GenerationResult> {
  data: Generation<TResult>
  meta: {
    credits_balance: number
  }
}

export type CampaignGenerationResponse = GenerationResponse<CampaignGenerationResult>
export type VideoEnhancerGenerationResponse = GenerationResponse<VideoEnhancerResult>

export interface PaginatedResponse<T> {
  data: T[]
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

export type ProjectGenerationHistoryResponse = PaginatedResponse<Generation<GenerationResult>>

export function isCampaignGenerationResult(
  result: GenerationResult | null,
): result is CampaignGenerationResult {
  return result !== null && 'hooks' in result
}

export function isVideoEnhancerResult(
  result: GenerationResult | null,
): result is VideoEnhancerResult {
  return result !== null && 'improved_caption' in result
}
