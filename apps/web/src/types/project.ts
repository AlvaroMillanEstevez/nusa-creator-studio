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
