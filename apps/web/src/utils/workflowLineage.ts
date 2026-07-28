import type { MediaAsset } from '@/types/media'
import {
  generationOutput,
  isMotionVideoResult,
  isVideoToCampaignResult,
  type CreatorGeneration,
  type CreatorProject,
  type GenerationResult,
} from '@/types/project'
import { formatDate } from '@/utils/formatters'
import { generationDisplayName } from '@/utils/generations'

export interface WorkflowOriginInfo {
  workflow: 'product_content_kit' | 'analyze_footage' | 'motion_ai_video' | 'auto_video_editor' | 'voiceover' | 'unknown'
  mode: string | null
  label: string
}

export function workflowOriginForGeneration(generation: CreatorGeneration): WorkflowOriginInfo {
  if (generation.job_type === 'generate_affiliate_campaign') {
    const mode = campaignInputMode(generation)

    if (mode === 'image_assisted') {
      return { workflow: 'product_content_kit', mode, label: 'Product Content Kit · Product image' }
    }

    if (mode === 'video_analysis') {
      return { workflow: 'analyze_footage', mode, label: 'Analyze Footage with AI · Video analysis' }
    }

    return { workflow: 'product_content_kit', mode: mode ?? 'brief_only', label: 'Product Content Kit · Project brief' }
  }

  if (generation.job_type === 'generate_video_to_campaign') {
    return { workflow: 'analyze_footage', mode: 'video_analysis', label: 'Analyze Footage with AI · Video analysis' }
  }

  if (generation.job_type === 'generate_motion_ai_video') {
    return { workflow: 'motion_ai_video', mode: motionSourceMode(generation), label: 'Motion AI Video · Generated footage' }
  }

  if (generation.job_type === 'generate_auto_video_edit') {
    return { workflow: 'auto_video_editor', mode: null, label: 'Auto Video Editor · Edited video' }
  }

  if (generation.job_type === 'generate_ai_voiceover') {
    return { workflow: 'voiceover', mode: null, label: 'Voiceover' }
  }

  return { workflow: 'unknown', mode: null, label: 'Saved generation' }
}

export function generationSelectorLabel(generation: CreatorGeneration, project: CreatorProject | null = null): string {
  const origin = workflowOriginForGeneration(generation)
  const name = generationDisplayName(generation, project)
  const date = formatDate(generation.created_at)

  return [name, origin.label, date].filter(Boolean).join(' · ')
}

export function isMotionCampaignCompatible(generation: CreatorGeneration): boolean {
  if (generation.status !== 'completed') {
    return false
  }

  if (generation.job_type === 'generate_affiliate_campaign') {
    return hasCampaignLikeOutput(generationOutput(generation))
  }

  if (generation.job_type === 'generate_video_to_campaign') {
    return hasCampaignLikeOutput(generationOutput(generation))
  }

  return false
}

export function isFootageAnalysisGeneration(generation: CreatorGeneration): boolean {
  if (generation.status !== 'completed' || generation.job_type !== 'generate_video_to_campaign') {
    return false
  }

  const output = generationOutput(generation)

  if (!isVideoToCampaignResult(output)) {
    return false
  }

  const sourceVideoAssetId = output.source_video_asset_id ?? output.provider_metadata?.source_video_asset_id ?? null
  const hasSourceVideo = typeof sourceVideoAssetId === 'number' && Number.isFinite(sourceVideoAssetId)
  const hasEditPlan = (Array.isArray(output.recommended_cuts) && output.recommended_cuts.length > 0)
    || (Array.isArray(output.edit_plan?.timeline) && output.edit_plan.timeline.length > 0)
  const mode = output.campaign_input_mode ?? output.provider_metadata?.campaign_input_mode ?? null
  const explicitlyEligible = output.auto_video_editor_eligible === true
    || output.provider_metadata?.auto_video_editor_eligible === true

  return hasSourceVideo && hasEditPlan && (mode === 'video_analysis' || explicitlyEligible)
}

export function sourceVideoAssetIdFromAnalysis(generation: CreatorGeneration | null): number | null {
  if (generation === null) {
    return null
  }

  const output = generationOutput(generation)

  if (!isVideoToCampaignResult(output)) {
    return null
  }

  const value = output.source_video_asset_id ?? output.provider_metadata?.source_video_asset_id ?? null

  return typeof value === 'number' && Number.isFinite(value) ? value : null
}

export function motionFinalVideoAssetId(generation: CreatorGeneration | null): number | null {
  if (generation === null) {
    return null
  }

  const output = generationOutput(generation)

  if (!isMotionVideoResult(output)) {
    return null
  }

  const value = output.final_video_asset_id
    ?? output.rendered_video_asset?.id
    ?? output.metadata.final_video_asset_id
    ?? output.provider_metadata?.final_video_asset_id
    ?? null

  return typeof value === 'number' && Number.isFinite(value) ? value : null
}

export function voiceoverSourceGenerationId(generation: CreatorGeneration): number | null {
  const output = generationOutput(generation)
  const candidates: unknown[] = [
    generation.input_payload?.source_generation_id,
    generation.request_payload?.source_generation_id,
  ]

  if (isRecord(output)) {
    candidates.push(output.source_generation_id)
  }

  for (const value of candidates) {
    const id = positiveInt(value)

    if (id !== null) {
      return id
    }
  }

  return null
}

export function mediaAssetOriginLabel(asset: MediaAsset): string {
  const metadata: Record<string, unknown> = isRecord(asset.metadata) ? asset.metadata as Record<string, unknown> : {}
  const workflow = typeof metadata.workflow === 'string' ? metadata.workflow : typeof metadata.workflow_key === 'string' ? metadata.workflow_key : null

  if (workflow === 'motion-ai-video' || workflow === 'motion_ai_video') {
    return 'Generated by Motion AI Video'
  }

  if (workflow === 'auto-video-editor' || workflow === 'auto_video_editor') {
    return 'Generated by Auto Video Editor'
  }

  if (asset.origin === 'generated' || metadata.generated === true) {
    return asset.type === 'video' ? 'Generated video' : 'Generated asset'
  }

  return asset.origin_label ?? 'Uploaded video'
}

export function positiveInt(value: unknown): number | null {
  if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
    return value
  }

  if (typeof value === 'string' && /^\d+$/.test(value)) {
    const parsed = Number(value)

    return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : null
  }

  return null
}

function campaignInputMode(generation: CreatorGeneration): string | null {
  for (const payload of [generation.input_payload, generation.request_payload, generation.result_payload, generation.output_payload]) {
    if (!isRecord(payload)) {
      continue
    }

    const providerMetadata = isRecord(payload.provider_metadata) ? payload.provider_metadata : {}
    const value = payload.campaign_input_mode ?? providerMetadata.campaign_input_mode

    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
    }
  }

  return null
}

function motionSourceMode(generation: CreatorGeneration): string | null {
  const output = generationOutput(generation)

  if (!isMotionVideoResult(output)) {
    return null
  }

  return output.metadata.source_mode
    ?? output.motion_summary.source_mode
    ?? output.motion_summary.source_type
    ?? output.provider_metadata?.source_mode
    ?? null
}

function hasCampaignLikeOutput(result: GenerationResult | null): boolean {
  if (!isRecord(result)) {
    return false
  }

  const stringKeys = [
    'campaign_title',
    'campaign_angle',
    'product_angle',
    'caption',
    'cta',
    'narration_voice_script',
    'voiceover_ready_script',
  ]

  if (stringKeys.some((key) => typeof result[key] === 'string' && String(result[key]).trim() !== '')) {
    return true
  }

  return ['hooks', 'captions', 'cta_options', 'hashtags'].some((key) => Array.isArray(result[key]) && result[key].length > 0)
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}
