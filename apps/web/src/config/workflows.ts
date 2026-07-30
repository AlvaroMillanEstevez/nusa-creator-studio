import type { RouteLocationRaw } from 'vue-router'

import type { ProjectActionKey } from '@/types/project'

export type BetaWorkflowKey =
  | 'product_content_kit'
  | 'analyze_footage'
  | 'ai_fashion_try_on'
  | 'motion_ai_video'
  | 'auto_video_editor'
  | 'auto_video_ads'

export type BetaWorkflowStatus = 'available' | 'coming_soon'
export type WorkflowFamilyKey = 'creator_content' | 'commerce_studio' | 'coming_soon'

export interface WorkflowFamilyDefinition {
  key: WorkflowFamilyKey
  titleKey: string
  descriptionKey: string
}

export interface BetaWorkflowDefinition {
  key: BetaWorkflowKey
  status: BetaWorkflowStatus
  familyKey: WorkflowFamilyKey
  sourceToolKey: string | null
  dashboardToolKey: string
  aliases: string[]
  projectActionKey: ProjectActionKey | null
  categoryKey: string
  recommendedInputKeys: string[]
  outputKeys: string[]
  iconKey: string
}

export const workflowFamilyDefinitions: WorkflowFamilyDefinition[] = [
  {
    key: 'creator_content',
    titleKey: 'workflows.families.creator_content.title',
    descriptionKey: 'workflows.families.creator_content.description',
  },
  {
    key: 'commerce_studio',
    titleKey: 'workflows.families.commerce_studio.title',
    descriptionKey: 'workflows.families.commerce_studio.description',
  },
  {
    key: 'coming_soon',
    titleKey: 'workflows.families.coming_soon.title',
    descriptionKey: 'workflows.families.coming_soon.description',
  },
]

export const betaWorkflows: BetaWorkflowDefinition[] = [
  {
    key: 'product_content_kit',
    status: 'available',
    familyKey: 'creator_content',
    sourceToolKey: 'affiliate_campaign',
    dashboardToolKey: 'product_content_kit',
    aliases: ['affiliate_campaign', 'campaign', 'campaign_generator'],
    projectActionKey: 'generate_campaign',
    categoryKey: 'content_kit',
    recommendedInputKeys: ['project_brief', 'optional_product_image'],
    outputKeys: ['hooks', 'script', 'captions', 'hashtags', 'cta', 'publishing_guidance'],
    iconKey: 'kit',
  },
  {
    key: 'analyze_footage',
    status: 'available',
    familyKey: 'creator_content',
    sourceToolKey: 'video_to_campaign',
    dashboardToolKey: 'video_to_campaign',
    aliases: ['analyze_footage_with_ai', 'video_analysis', 'video_enhancer', 'analyze_video'],
    projectActionKey: 'generate_video_to_campaign',
    categoryKey: 'video_analysis',
    recommendedInputKeys: ['product_video'],
    outputKeys: ['video_summary', 'product_moments', 'recommended_cuts', 'campaign_angle', 'captions', 'narration_script', 'edit_plan', 'optional_voiceover_action'],
    iconKey: 'analysis',
  },
  {
    key: 'motion_ai_video',
    status: 'available',
    familyKey: 'creator_content',
    sourceToolKey: 'motion_ai_video',
    dashboardToolKey: 'motion_ai_video',
    aliases: ['motion_video', 'veo_motion_video'],
    projectActionKey: 'start_motion_video',
    categoryKey: 'ai_video_generation',
    recommendedInputKeys: ['project_brief', 'creative_description', 'compatible_saved_campaign', 'product_image_when_enabled'],
    outputKeys: ['generated_mp4', 'private_video_asset', 'visual_generation_summary', 'post_production_suggestions'],
    iconKey: 'motion',
  },
  {
    key: 'auto_video_editor',
    status: 'available',
    familyKey: 'creator_content',
    sourceToolKey: 'auto_video_editor',
    dashboardToolKey: 'auto_video_editor',
    aliases: ['auto_video_edit', 'video_editor'],
    projectActionKey: 'generate_auto_video_edit',
    categoryKey: 'auto_video_editor',
    recommendedInputKeys: ['raw_or_generated_footage', 'optional_video_analysis', 'optional_voiceover'],
    outputKeys: ['edited_mp4', 'captions_overlays_when_composed', 'cta_end_card'],
    iconKey: 'editor',
  },
  {
    key: 'ai_fashion_try_on',
    status: 'available',
    familyKey: 'commerce_studio',
    sourceToolKey: 'fashion_try_on',
    dashboardToolKey: 'ai_fashion_try_on',
    aliases: ['fashion_try_on', 'try_on', 'fashion_studio'],
    projectActionKey: 'start_fashion_try_on',
    categoryKey: 'fashion_try_on',
    recommendedInputKeys: ['person_photo', 'clothing_product_photo', 'consent_confirmation'],
    outputKeys: ['try_on_image', 'private_image_asset', 'fashion_result_summary', 'motion_video_followup'],
    iconKey: 'fashion',
  },
  {
    key: 'auto_video_ads',
    status: 'coming_soon',
    familyKey: 'coming_soon',
    sourceToolKey: 'auto_video_ads',
    dashboardToolKey: 'auto_video_ads',
    aliases: ['video_ads', 'ad_plan'],
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

export const activeWorkflowFamilyDefinitions = workflowFamilyDefinitions.filter(
  (family) => family.key !== 'coming_soon',
)

export function workflowFamilyDefinition(key: WorkflowFamilyKey): WorkflowFamilyDefinition {
  return workflowFamilyDefinitions.find((family) => family.key === key) ?? workflowFamilyDefinitions[0]!
}

export function workflowDefinitionsForFamily(
  familyKey: WorkflowFamilyKey,
  statuses: BetaWorkflowStatus[] = ['available', 'coming_soon'],
): BetaWorkflowDefinition[] {
  return betaWorkflows.filter(
    (workflow) => workflow.familyKey === familyKey && statuses.includes(workflow.status),
  )
}

export function workflowDashboardPath(workflow: BetaWorkflowDefinition): string {
  return '/dashboard?tool=' + encodeURIComponent(workflow.dashboardToolKey)
}

export function workflowCreateProjectRoute(workflow: BetaWorkflowDefinition): RouteLocationRaw {
  return { name: 'project-create', query: { workflow: workflow.dashboardToolKey } }
}

export function workflowRouteForProject(
  workflow: BetaWorkflowDefinition,
  projectId: number | string,
): RouteLocationRaw {
  const params = { projectId: String(projectId) }

  if (workflow.key === 'analyze_footage') {
    return { name: 'project-video-to-campaign-action', params }
  }

  if (workflow.key === 'ai_fashion_try_on') {
    return { name: 'project-fashion-try-on-action', params }
  }

  if (workflow.key === 'motion_ai_video') {
    return { name: 'project-motion-ai-video-action', params }
  }

  if (workflow.key === 'auto_video_editor') {
    return { name: 'project-auto-video-editor-action', params }
  }

  if (workflow.key === 'auto_video_ads') {
    return { name: 'project-auto-video-ads-action', params }
  }

  return { name: 'project-detail', params: { id: String(projectId) } }
}

export function resolveWorkflowDefinition(value: unknown): BetaWorkflowDefinition | null {
  if (Array.isArray(value)) {
    return resolveWorkflowDefinition(value[0])
  }

  if (typeof value !== 'string') {
    return null
  }

  const key = value.trim().toLowerCase()

  if (key === '') {
    return null
  }

  return betaWorkflows.find((workflow) =>
    workflow.key === key ||
    workflow.dashboardToolKey === key ||
    workflow.sourceToolKey === key ||
    workflow.aliases.includes(key),
  ) ?? null
}
