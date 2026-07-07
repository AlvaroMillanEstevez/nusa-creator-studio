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

export const productVideoGoalOptions = [
  { value: 'awareness', label: 'Awareness' },
  { value: 'product_demo', label: 'Product demo' },
  { value: 'affiliate_sale', label: 'Affiliate sale' },
  { value: 'education', label: 'Education' },
  { value: 'review', label: 'Review' },
] as const

export const productVideoStyleOptions = [
  { value: 'natural', label: 'Natural' },
  { value: 'energetic', label: 'Energetic' },
  { value: 'premium', label: 'Premium' },
  { value: 'problem_solution', label: 'Problem-solution' },
  { value: 'storytelling', label: 'Storytelling' },
  { value: 'review', label: 'Review' },
] as const

export const productVideoDurationOptions = [15, 30, 45] as const

export const voiceoverStyleOptions = [
  { value: 'natural_creator', label: 'Natural creator' },
  { value: 'energetic_seller', label: 'Energetic seller' },
  { value: 'calm_explainer', label: 'Calm explainer' },
  { value: 'premium_brand', label: 'Premium brand' },
  { value: 'friendly_storyteller', label: 'Friendly storyteller' },
] as const

export const voiceoverToneOptions = [
  { value: 'natural', label: 'Natural' },
  { value: 'persuasive', label: 'Persuasive' },
  { value: 'playful', label: 'Playful' },
  { value: 'professional', label: 'Professional' },
  { value: 'emotional', label: 'Emotional' },
] as const

export const voiceoverPaceOptions = [
  { value: 'slow', label: 'Slow' },
  { value: 'medium', label: 'Medium' },
  { value: 'fast', label: 'Fast' },
] as const

export const voiceoverDurationOptions = [15, 30, 45, 60] as const

export const voiceoverPlatformOptions = [
  { value: 'tiktok', label: 'TikTok' },
  { value: 'instagram_reels', label: 'Instagram Reels' },
  { value: 'youtube_shorts', label: 'YouTube Shorts' },
  { value: 'generic_vertical_video', label: 'Generic vertical video' },
] as const

export type CampaignLanguage = (typeof campaignLanguageOptions)[number]['value']
export type CampaignTone = (typeof campaignToneOptions)[number]['value']
export type VideoEnhancerStyle = (typeof videoEnhancerStyleOptions)[number]['value']
export type ProductVideoGoal = (typeof productVideoGoalOptions)[number]['value']
export type ProductVideoStyle = (typeof productVideoStyleOptions)[number]['value']
export type ProductVideoDuration = (typeof productVideoDurationOptions)[number]
export type VoiceoverStyle = (typeof voiceoverStyleOptions)[number]['value']
export type VoiceoverTone = (typeof voiceoverToneOptions)[number]['value']
export type VoiceoverPace = (typeof voiceoverPaceOptions)[number]['value']
export type VoiceoverDuration = (typeof voiceoverDurationOptions)[number]
export type VoiceoverPlatform = (typeof voiceoverPlatformOptions)[number]['value']

export type ProjectType = 'affiliate_campaign' | 'video_enhancer' | 'product_to_video' | 'ai_voiceover'
export type ProjectStatus = 'draft' | 'pending' | 'processing' | 'completed' | 'failed'
export type GenerationStatus = 'queued' | 'processing' | 'completed' | 'failed' | 'pending'

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

export interface ProductToVideoForm {
  product_name: string
  product_description: string
  key_benefits: string
  target_audience: string
  video_goal: ProductVideoGoal
  video_style: ProductVideoStyle
  duration_seconds: ProductVideoDuration
  language: CampaignLanguage
}

export interface AiVoiceoverFormPayload {
  title: string
  source_text: string | null
  product_name: string | null
  product_description: string | null
  target_audience: string | null
  language: CampaignLanguage
  voice_style: VoiceoverStyle
  tone: VoiceoverTone
  pace: VoiceoverPace
  duration_seconds: VoiceoverDuration
  platform: VoiceoverPlatform
  notes: string | null
}

export interface CreatorProjectSettings {
  platform: VoiceoverPlatform
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
> & { type: TType }

export type CampaignProject = TypedProject<'affiliate_campaign', CampaignFormPayload>
export type VideoEnhancerProject = TypedProject<'video_enhancer', VideoEnhancerForm>
export type ProductToVideoProject = TypedProject<'product_to_video', ProductToVideoForm>
export type AiVoiceoverProject = TypedProject<'ai_voiceover', AiVoiceoverFormPayload>
export type CreatorProject =
  | CampaignProject
  | VideoEnhancerProject
  | ProductToVideoProject
  | AiVoiceoverProject

export interface CampaignScriptScene {
  timestamp: string
  voiceover: string
  visual_direction: string
  on_screen_text: string
}

export interface CampaignGenerationResult {
  hooks: string[]
  script: { title: string; duration_seconds: number; scenes: CampaignScriptScene[] }
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

export interface ProductVideoStoryboardScene {
  scene_number: number
  timestamp: string
  scene_goal: string
  visual_direction: string
  voiceover: string
  on_screen_text: string
}

export interface ProductToVideoResult {
  concept_title: string
  video_angle: string
  storyboard: ProductVideoStoryboardScene[]
  shot_list: string[]
  asset_checklist: string[]
  caption: string
  hashtags: string[]
  cta: string
  editing_notes: string[]
  compliance_notes: string[]
}

export interface AiVoiceoverSceneTiming {
  timestamp: string
  section: string
  narration_focus: string
  delivery_note: string
}

export interface AiVoiceoverMockAudio {
  status: 'placeholder'
  message: string
  provider: 'mock'
}

export interface AiVoiceoverResult {
  voiceover_title: string
  narration_script: string
  scene_timing: AiVoiceoverSceneTiming[]
  voice_direction: string
  pronunciation_notes: string[]
  pacing_notes: string[]
  platform_notes: string[]
  estimated_duration_seconds: number
  suggested_audio_format: string
  usage_notes: string[]
  mock_audio: AiVoiceoverMockAudio
  compliance_notes: string[]
}

export type GenerationResult =
  | CampaignGenerationResult
  | VideoEnhancerResult
  | ProductToVideoResult
  | AiVoiceoverResult

export interface Generation<TResult = GenerationResult> {
  id: number
  user_id: number
  project_id: number | null
  job_type: string
  provider: string | null
  status: GenerationStatus
  cost_usd: string
  credits_charged: number
  input_payload: Record<string, unknown> | null
  output_payload: TResult | null
  error_message: string | null
  request_payload?: Record<string, unknown> | null
  result_payload?: TResult | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
}

export type CreatorGeneration = Generation<GenerationResult>

export function generationOutput<TResult extends GenerationResult>(
  generation: Generation<TResult>,
): TResult | null {
  return generation.output_payload ?? generation.result_payload ?? null
}

export interface GenerationResponse<TResult extends GenerationResult> {
  data: Generation<TResult>
  meta: { credits_balance: number }
}

export type CampaignGenerationResponse = GenerationResponse<CampaignGenerationResult>
export type VideoEnhancerGenerationResponse = GenerationResponse<VideoEnhancerResult>
export type ProductToVideoGenerationResponse = GenerationResponse<ProductToVideoResult>
export type AiVoiceoverGenerationResponse = GenerationResponse<AiVoiceoverResult>

export interface PaginatedResponse<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
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

export function isProductToVideoResult(
  result: GenerationResult | null,
): result is ProductToVideoResult {
  return result !== null && 'concept_title' in result
}

export function isAiVoiceoverResult(
  result: GenerationResult | null,
): result is AiVoiceoverResult {
  return result !== null && 'voiceover_title' in result && 'mock_audio' in result
}