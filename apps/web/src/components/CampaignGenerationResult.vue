<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import { useTranslations } from '@/i18n'
import type {
  CampaignGenerationResult,
  CampaignHook,
  CampaignMainScript,
  CampaignScriptScene,
  ProjectAction,
} from '@/types/project'

const props = defineProps<{
  result: CampaignGenerationResult
  projectId?: number | string | null
  generationId?: number | null
  voiceoverAction?: ProjectAction | null
}>()


const { t } = useTranslations()
const copyStatus = ref<'idle' | 'copied' | 'unavailable'>('idle')
const narrationText = ref('')

const campaignTitle = computed(
  () => props.result.campaign_title ?? props.result.main_script?.title ?? props.result.script?.title ?? 'Campaign result',
)
const hooks = computed<CampaignHook[]>(() =>
  props.result.hooks.map((hook, index) => {
    if (typeof hook === 'string') {
      return {
        label: `Hook ${index + 1}`,
        text: hook,
        intent: '',
      }
    }

    return hook
  }),
)
const script = computed<CampaignMainScript>(
  () =>
    props.result.main_script ??
    props.result.script ?? {
      duration_seconds: 30,
      voiceover_script: '',
      on_screen_text: [],
      scenes: [] as CampaignScriptScene[],
    },
)
const scenes = computed(() => script.value.scenes ?? [])
const captions = computed(() => props.result.captions ?? (props.result.caption ? [props.result.caption] : []))
const ctaOptions = computed(() => props.result.cta_options ?? (props.result.cta ? [props.result.cta] : []))
const usageNotes = computed(() => props.result.usage_notes ?? props.result.notes ?? [])
const complianceNotes = computed(() => props.result.compliance_notes ?? [])
const voiceoverScript = computed(() => {
  if (props.result.voiceover_ready_script) {
    return props.result.voiceover_ready_script
  }

  if (script.value.voiceover_script) {
    return script.value.voiceover_script
  }

  return scenes.value.map((scene) => scene.voiceover).filter(Boolean).join('\n')
})
const providerMetadata = computed(() => props.result.provider_metadata ?? null)
const campaignInputMode = computed(() => props.result.campaign_input_mode ?? providerMetadata.value?.campaign_input_mode ?? 'brief_only')
const autoVideoEditorEligible = computed(() => props.result.auto_video_editor_eligible === true || providerMetadata.value?.auto_video_editor_eligible === true)
const workflowNotice = computed(() => {
  if (campaignInputMode.value === 'video_analysis' && autoVideoEditorEligible.value) {
    return 'This campaign includes video analysis and can be used in Auto Video Editor.'
  }

  return 'Use this kit for scripts, captions, voiceover and publishing. For raw product footage, use Product Content Kit from video / Analyze footage so the result can feed Auto Video Editor.'
})
const hasProviderMetadata = computed(
  () => providerMetadata.value !== null && Object.keys(providerMetadata.value).length > 0,
)
const narrationTrimmed = computed(() => narrationText.value.trim())
const projectRoute = computed(() => {
  return props.projectId === undefined || props.projectId === null ? '/dashboard' : `/projects/${String(props.projectId)}`
})
const motionAiVideoRoute = computed(() => {
  if (props.projectId === undefined || props.projectId === null || props.generationId === undefined || props.generationId === null) {
    return null
  }

  return {
    name: 'project-motion-ai-video-action',
    params: { projectId: String(props.projectId) },
    query: {
      sourceMode: 'campaign_assisted',
      campaignGenerationId: String(props.generationId),
    },
  }
})

function sceneTime(scene: CampaignScriptScene): string {
  return scene.time_range ?? scene.timestamp ?? ''
}


async function copyVoiceoverScript(): Promise<void> {
  if (!narrationTrimmed.value) {
    return
  }

  if (!navigator.clipboard?.writeText) {
    copyStatus.value = 'unavailable'
    return
  }

  try {
    await navigator.clipboard.writeText(narrationTrimmed.value)
    copyStatus.value = 'copied'
  } catch {
    copyStatus.value = 'unavailable'
  }
}

watch(voiceoverScript, (next) => {
  narrationText.value = next
}, { immediate: true })


</script>

<template>
  <div class="space-y-5">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.campaignPackage') }}
      </p>
      <h2 class="mt-2 text-2xl font-bold leading-tight">{{ campaignTitle }}</h2>
      <p class="mt-5 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-sm leading-6 text-sky-100/80" data-testid="campaign-workflow-notice">{{ workflowNotice }}</p>
      <div class="mt-5 space-y-4 text-sm leading-6 text-neutral-300">
        <p v-if="result.product_angle">
          <span class="font-semibold text-neutral-100">{{ t('campaignResult.productAngle') }}:</span>
          {{ result.product_angle }}
        </p>
        <p v-if="result.target_audience_summary">
          <span class="font-semibold text-neutral-100">{{ t('campaignResult.audience') }}:</span>
          {{ result.target_audience_summary }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5" data-testid="campaign-next-actions">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">Next workflow</p>
      <h3 class="mt-2 text-xl font-semibold">Turn this kit into video</h3>
      <p class="mt-2 text-sm leading-6 text-neutral-400">
        Reuse this campaign context in Motion AI Video. Nusa will preselect campaign-assisted mode, but generation only starts after you review and press Generate.
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <RouterLink
          v-if="motionAiVideoRoute"
          :to="motionAiVideoRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          data-testid="campaign-create-motion-video"
        >
          Create Motion AI Video
        </RouterLink>
        <RouterLink
          :to="projectRoute"
          class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30"
          data-testid="campaign-return-project"
        >
          Return to project
        </RouterLink>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <h2 class="text-2xl font-bold">{{ t('campaignResult.hooks') }}</h2>
      <ol class="mt-5 space-y-3">
        <li
          v-for="(hook, index) in hooks"
          :key="`${hook.text}-${index}`"
          class="rounded-2xl bg-neutral-950/70 p-4 text-sm leading-6 text-neutral-200"
        >
          <div class="flex gap-3">
            <span class="font-bold text-emerald-400">{{ index + 1 }}</span>
            <div class="min-w-0 flex-1">
              <p class="break-words">{{ hook.text }}</p>
              <p v-if="hook.intent" class="mt-2 text-xs leading-5 text-neutral-500">
                {{ hook.intent }}
              </p>
            </div>
          </div>
        </li>
      </ol>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            {{ t('campaignResult.tiktokScript') }}
          </p>
          <h2 class="mt-2 text-xl font-bold">{{ campaignTitle }}</h2>
        </div>
        <span class="shrink-0 text-sm text-neutral-400"> {{ script.duration_seconds }}s </span>
      </div>

      <details v-if="voiceoverScript" class="mt-5 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-neutral-100">
          {{ t('campaignResult.narrationVoice') }}
        </summary>
        <div class="mt-4 space-y-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <p class="text-xs leading-5 text-neutral-500">
              {{ t('campaignResult.narrationHelper') }}
            </p>
            <button
              type="button"
              class="shrink-0 rounded-full border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-xs font-semibold text-emerald-200 transition hover:border-emerald-500 hover:bg-emerald-900/60"
              @click="copyVoiceoverScript"
            >
              {{ copyStatus === 'copied' ? t('campaignResult.copied') : t('campaignResult.copyScript') }}
            </button>
          </div>

          <div class="rounded-2xl border border-neutral-800 bg-neutral-950/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('projectActions.narrationScript') }}
            </p>
            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-200">
              {{ narrationText }}
            </p>
          </div>

          <p v-if="copyStatus === 'unavailable'" class="text-xs text-amber-300">
            {{ t('campaignResult.copyUnavailable') }}
          </p>
        </div>
      </details>

      <div class="mt-6 space-y-4">
        <article
          v-for="(scene, index) in scenes"
          :key="`${sceneTime(scene)}-${index}`"
          class="nusa-card-soft rounded-2xl p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-white">
              {{ t('campaignResult.scene', { number: index + 1 }) }}
            </p>
            <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
              {{ sceneTime(scene) }}
            </span>
          </div>
          <dl class="mt-4 space-y-4 text-sm">
            <div>
              <dt class="font-semibold text-neutral-500">{{ t('campaignResult.voiceover') }}</dt>
              <dd class="mt-1 break-words leading-6 text-neutral-200">{{ scene.voiceover }}</dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">
                {{ t('campaignResult.visualDirection') }}
              </dt>
              <dd class="mt-1 break-words leading-6 text-neutral-300">
                {{ scene.visual_direction }}
              </dd>
            </div>
            <div>
              <dt class="font-semibold text-neutral-500">{{ t('campaignResult.onScreenText') }}</dt>
              <dd class="mt-1 break-words font-medium text-emerald-300">
                {{ scene.on_screen_text }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.captions') }}
      </p>
      <div class="mt-3 space-y-3">
        <p
          v-for="caption in captions"
          :key="caption"
          class="whitespace-pre-line rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200"
        >
          {{ caption }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.hashtags') }}
      </p>
      <div class="mt-4 flex flex-wrap gap-2">
        <span
          v-for="hashtag in result.hashtags"
          :key="hashtag"
          class="break-all rounded-full border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs font-medium text-neutral-300"
        >
          {{ hashtag }}
        </span>
      </div>
    </section>

    <section class="rounded-3xl border border-emerald-900/60 bg-emerald-950/30 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.ctaOptions') }}
      </p>
      <ul class="mt-3 space-y-3">
        <li
          v-for="cta in ctaOptions"
          :key="cta"
          class="break-words text-base font-semibold leading-7 text-white"
        >
          {{ cta }}
        </li>
      </ul>
    </section>

    <section v-if="complianceNotes.length" class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-300">
        {{ t('campaignResult.complianceNotes') }}
      </p>
      <ul class="mt-4 space-y-3">
        <li
          v-for="note in complianceNotes"
          :key="note"
          class="flex gap-3 text-sm leading-6 text-neutral-300"
        >
          <span class="text-amber-300" aria-hidden="true">!</span>
          <span class="break-words">{{ note }}</span>
        </li>
      </ul>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        {{ t('campaignResult.practicalNotes') }}
      </p>
      <ul class="mt-4 space-y-3">
        <li
          v-for="note in usageNotes"
          :key="note"
          class="flex gap-3 text-sm leading-6 text-neutral-300"
        >
          <span class="text-emerald-400" aria-hidden="true">&#10003;</span>
          <span class="break-words">{{ note }}</span>
        </li>
      </ul>
    </section>

    <section v-if="hasProviderMetadata" class="nusa-card-soft rounded-3xl p-4 text-xs text-neutral-500">
      <p class="font-semibold uppercase tracking-[0.16em] text-neutral-400">
        {{ t('campaignResult.providerDetails') }}
      </p>
      <p class="mt-2">
        {{ providerMetadata?.provider ?? 'mock' }}<span v-if="providerMetadata?.model"> · {{ providerMetadata.model }}</span>
      </p>
      <p
        v-if="
          providerMetadata?.estimated_cost_usd !== undefined &&
          providerMetadata.estimated_cost_usd !== null
        "
        class="mt-1"
      >
        {{ t('campaignResult.estimatedProviderCost') }}: ${{ providerMetadata.estimated_cost_usd }}
      </p>
    </section>
  </div>
</template>