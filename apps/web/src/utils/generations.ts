import { currentLocale } from '@/i18n'
import {
  generationOutput,
  isAiVoiceoverResult,
  isAutoVideoEditorResult,
  isAutoVideoAdResult,
  isVideoToCampaignResult,
  type CreatorGeneration,
  type CreatorProject,
  type GenerationResult,
} from '@/types/project'

const jobTypeLabels: Record<string, string> = {
  generate_affiliate_campaign: 'Product Content Kit',
  generate_video_to_campaign: 'Product Content Kit · Video analysis',
  generate_ai_voiceover: 'AI Voiceover',
  generate_auto_video_edit: 'Auto Video Editor',
  generate_product_video_script: 'Product Content Kit · Script pack',
  generate_auto_video_ad: 'Auto Video Ads',
  generate_motion_ai_video: 'Motion AI Video',
}

export function generationJobTypeLabel(jobType: string): string {
  return jobTypeLabels[jobType] ?? 'Generation'
}

export function generationFriendlyLabel(generation: CreatorGeneration): string {
  if (generation.job_type === 'generate_affiliate_campaign') {
    const mode = campaignInputMode(generation)

    if (mode === 'image_assisted') {
      return 'Product Content Kit · Image assisted'
    }

    if (mode === 'video_analysis') {
      return 'Product Content Kit · Video analysis'
    }
  }

  if (generation.job_type === 'generate_auto_video_ad') {
    const visualSourceType = autoVideoAdVisualSourceType(generation)

    if (visualSourceType === 'image') {
      return 'Auto Video Ads · Image ad'
    }

    if (visualSourceType === 'video') {
      return 'Auto Video Ads · Video ad'
    }

    if (visualSourceType === 'hybrid') {
      return 'Auto Video Ads · Hybrid ad'
    }
  }

  return generationJobTypeLabel(generation.job_type)
}

export function generationDisplayName(
  generation: CreatorGeneration,
  project: CreatorProject | null = null,
): string {
  const type = generationFriendlyLabel(generation)
  const product = projectProductName(project) ?? project?.title ?? resultTitle(generationOutput(generation))
  const date = shortDate(generation.created_at)
  const duration = generationDurationLabel(generation)

  return [type, product, duration, date].filter(Boolean).join(' · ')
}

export function generationDurationLabel(generation: CreatorGeneration): string | null {
  const output = generationOutput(generation)

  if (generation.job_type === 'generate_ai_voiceover' && isRecord(output)) {
    const audio = output.audio
    const duration = isRecord(audio) && typeof audio.duration_seconds === 'number'
      ? audio.duration_seconds
      : output.estimated_duration_seconds

    return secondsLabel(typeof duration === 'number' ? duration : null)
  }

  if (isAiVoiceoverResult(output)) {
    return secondsLabel(output.audio?.duration_seconds ?? output.estimated_duration_seconds)
  }

  if (isAutoVideoEditorResult(output)) {
    return secondsLabel(output.render_settings.actual_duration_seconds ?? output.render_settings.target_duration_seconds)
  }

  if (isAutoVideoAdResult(output)) {
    return secondsLabel(output.ad_summary.duration)
  }

  return null
}

function projectProductName(project: CreatorProject | null): string | null {
  const payload = project?.input_payload

  if (!isRecord(payload)) {
    return null
  }

  return cleanString(payload.product_name)
}

function campaignInputMode(generation: CreatorGeneration): string | null {
  for (const payload of [generation.input_payload, generation.request_payload, generation.result_payload]) {
    if (!isRecord(payload)) {
      continue
    }

    const mode = payload.campaign_input_mode ?? payload.input_mode

    if (typeof mode === 'string' && mode.trim() !== '') {
      return mode.trim()
    }

    const nestedInput = payload.input_payload

    if (isRecord(nestedInput)) {
      const nestedMode = nestedInput.campaign_input_mode ?? nestedInput.input_mode

      if (typeof nestedMode === 'string' && nestedMode.trim() !== '') {
        return nestedMode.trim()
      }
    }
  }

  return null
}


function autoVideoAdVisualSourceType(generation: CreatorGeneration): string | null {
  for (const payload of [generation.output_payload, generation.result_payload, generation.input_payload, generation.request_payload]) {
    if (!isRecord(payload)) {
      continue
    }

    const metadata = payload.metadata
    const value = isRecord(metadata) ? metadata.visual_source_type : payload.visual_source_type

    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
    }
  }

  return null
}
function resultTitle(result: GenerationResult | null): string | null {
  if (isAiVoiceoverResult(result)) {
    return cleanString(result.voiceover_title)
  }

  if (isVideoToCampaignResult(result)) {
    return cleanString(result.campaign_angle)
  }

  if (isAutoVideoEditorResult(result)) {
    return cleanString(result.source_summary)
  }

  if (isAutoVideoAdResult(result)) {
    return cleanString(result.ad_summary.creative_angle)
  }

  return null
}

function shortDate(value: string | null): string | null {
  if (!value) {
    return null
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return null
  }

  return new Intl.DateTimeFormat(currentLocale.value, {
    month: 'short',
    day: 'numeric',
  }).format(date)
}

function secondsLabel(value: number | null | undefined): string | null {
  if (typeof value !== 'number' || Number.isNaN(value) || value <= 0) {
    return null
  }

  return `${Number.isInteger(value) ? value : value.toFixed(1)}s`
}

function cleanString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}
