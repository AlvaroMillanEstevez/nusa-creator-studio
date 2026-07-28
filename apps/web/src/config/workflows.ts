import type { ProjectActionKey } from '@/types/project'

export type BetaWorkflowKey =
  | 'product_content_kit'
  | 'analyze_footage'
  | 'ai_fashion_try_on'
  | 'motion_ai_video'
  | 'auto_video_editor'
  | 'auto_video_ads'

export type BetaWorkflowStatus = 'available' | 'coming_soon'

export interface BetaWorkflowDefinition {
  key: BetaWorkflowKey
  status: BetaWorkflowStatus
  sourceToolKey: string | null
  dashboardToolKey: string
  projectActionKey: ProjectActionKey | null
  categoryKey: string
  recommendedInputKeys: string[]
  outputKeys: string[]
  iconKey: string
}

export const betaWorkflows: BetaWorkflowDefinition[] = [
  {
    key: 'product_content_kit',
    status: 'available',
    sourceToolKey: 'affiliate_campaign',
    dashboardToolKey: 'product_content_kit',
    projectActionKey: 'generate_campaign',
    categoryKey: 'content_kit',
    recommendedInputKeys: ['project_brief', 'optional_product_image'],
    outputKeys: ['hooks', 'script', 'captions', 'hashtags', 'cta', 'publishing_guidance'],
    iconKey: 'kit',
  },
  {
    key: 'analyze_footage',
    status: 'available',
    sourceToolKey: 'video_to_campaign',
    dashboardToolKey: 'video_to_campaign',
    projectActionKey: 'generate_video_to_campaign',
    categoryKey: 'video_analysis',
    recommendedInputKeys: ['product_video'],
    outputKeys: ['video_summary', 'product_moments', 'recommended_cuts', 'campaign_angle', 'captions', 'narration_script', 'edit_plan', 'optional_voiceover_action'],
    iconKey: 'analysis',
  },
  {
    key: 'ai_fashion_try_on',
    status: 'available',
    sourceToolKey: 'fashion_try_on',
    dashboardToolKey: 'ai_fashion_try_on',
    projectActionKey: 'start_fashion_try_on',
    categoryKey: 'fashion_try_on',
    recommendedInputKeys: ['person_photo', 'clothing_product_photo', 'consent_confirmation'],
    outputKeys: ['try_on_image', 'private_image_asset', 'fashion_result_summary', 'motion_video_followup'],
    iconKey: 'fashion',
  },
  {
    key: 'motion_ai_video',
    status: 'available',
    sourceToolKey: 'motion_ai_video',
    dashboardToolKey: 'motion_ai_video',
    projectActionKey: 'start_motion_video',
    categoryKey: 'ai_video_generation',
    recommendedInputKeys: ['project_brief', 'creative_description', 'compatible_saved_campaign', 'product_image_when_enabled'],
    outputKeys: ['generated_mp4', 'private_video_asset', 'visual_generation_summary', 'post_production_suggestions'],
    iconKey: 'motion',
  },
  {
    key: 'auto_video_editor',
    status: 'available',
    sourceToolKey: 'auto_video_editor',
    dashboardToolKey: 'auto_video_editor',
    projectActionKey: 'generate_auto_video_edit',
    categoryKey: 'auto_video_editor',
    recommendedInputKeys: ['raw_or_generated_footage', 'optional_video_analysis', 'optional_voiceover'],
    outputKeys: ['edited_mp4', 'captions_overlays_when_composed', 'cta_end_card'],
    iconKey: 'editor',
  },
  {
    key: 'auto_video_ads',
    status: 'coming_soon',
    sourceToolKey: 'auto_video_ads',
    dashboardToolKey: 'auto_video_ads',
    projectActionKey: 'create_video_ad',
    categoryKey: 'paid_ads',
    recommendedInputKeys: ['campaign_content', 'product_footage', 'overlays', 'cta'],
    outputKeys: ['paid_ad_variations', 'storyboard', 'promo_overlays', 'cta', 'end_card'],
    iconKey: 'ads',
  },
]

export const availableWorkflowDefinitions = betaWorkflows.filter(
  (workflow) => workflow.status === 'available',
)

export const comingSoonWorkflowDefinitions = betaWorkflows.filter(
  (workflow) => workflow.status === 'coming_soon',
)
