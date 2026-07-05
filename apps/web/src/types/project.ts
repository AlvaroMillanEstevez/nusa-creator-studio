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

export type CampaignLanguage = (typeof campaignLanguageOptions)[number]['value']
export type CampaignTone = (typeof campaignToneOptions)[number]['value']

export type ProjectType = 'affiliate_campaign' | 'video_enhancer' | 'product_to_video'
export type ProjectStatus = 'draft' | 'pending' | 'processing' | 'completed' | 'failed'

export interface CampaignFormPayload {
  product_name: string
  product_description: string
  target_audience: string
  language: CampaignLanguage
  tone: CampaignTone
}

export interface CampaignProjectSettings {
  platform: 'tiktok'
  format: 'vertical_9_16'
}

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

export type CampaignProject = Project<CampaignFormPayload, CampaignProjectSettings>

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

export interface Generation<TResult = Record<string, unknown>> {
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

export interface CampaignGenerationResponse {
  data: Generation<CampaignGenerationResult>
  meta: {
    credits_balance: number
  }
}
