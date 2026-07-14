import type { ProjectActionKey } from '@/types/project'

export type BetaWorkflowKey =
  | 'product_content_kit'
  | 'auto_video_editor'
  | 'auto_video_ads'
  | 'motion_ai_video'

export type BetaWorkflowStatus = 'available' | 'beta_next'

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
    categoryKey: 'content',
    recommendedInputKeys: ['brief', 'image', 'video'],
    outputKeys: ['hooks', 'script', 'captions', 'hashtags', 'cta', 'strategy'],
    iconKey: 'kit',
  },
  {
    key: 'auto_video_editor',
    status: 'available',
    sourceToolKey: 'auto_video_editor',
    dashboardToolKey: 'auto_video_editor',
    projectActionKey: 'generate_auto_video_edit',
    categoryKey: 'organic_video',
    recommendedInputKeys: ['raw_footage', 'video_analysis', 'voiceover'],
    outputKeys: ['organic_video', 'captions', 'overlays', 'end_card'],
    iconKey: 'editor',
  },
  {
    key: 'auto_video_ads',
    status: 'beta_next',
    sourceToolKey: null,
    dashboardToolKey: 'auto_video_ads',
    projectActionKey: 'create_video_ad',
    categoryKey: 'paid_ads',
    recommendedInputKeys: ['product_kit', 'raw_footage', 'cta'],
    outputKeys: ['paid_ad_creative', 'promo_overlays', 'cta', 'end_card'],
    iconKey: 'ads',
  },
  {
    key: 'motion_ai_video',
    status: 'beta_next',
    sourceToolKey: 'motion_ai_video',
    dashboardToolKey: 'motion_ai_video',
    projectActionKey: 'start_motion_video',
    categoryKey: 'ai_motion',
    recommendedInputKeys: ['product_image', 'scenario', 'script'],
    outputKeys: ['ai_motion_video', 'visual_plan', 'caption', 'cta'],
    iconKey: 'motion',
  },
]

export const laterWorkflowKeys = [
  'fashion_studio',
  'product_photo_studio',
  'brand_kit',
  'content_calendar',
] as const

export type LaterWorkflowKey = typeof laterWorkflowKeys[number]
