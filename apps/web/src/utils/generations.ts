import { currentLocale } from '@/i18n'
import {
  generationOutput,
  isAiVoiceoverResult,
  isAutoVideoEditorResult,
  isVideoToCampaignResult,
  type CreatorGeneration,
  type CreatorProject,
  type GenerationResult,
} from '@/types/project'

const jobTypeLabels: Record<string, string> = {
  generate_affiliate_campaign: 'Affiliate campaign',
  generate_video_to_campaign: 'Video campaign',
  generate_ai_voiceover: 'Voiceover',
  generate_auto_video_edit: 'Auto edit',
  generate_product_video_script: 'Product video script',
}

export function generationDisplayName(
  generation: CreatorGeneration,
  project: CreatorProject | null = null,
): string {
  const type = jobTypeLabels[generation.job_type] ?? 'Generation'
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

  return null
}

function projectProductName(project: CreatorProject | null): string | null {
  const payload = project?.input_payload

  if (!isRecord(payload)) {
    return null
  }

  return cleanString(payload.product_name)
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
