<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { listProjectMediaAssets, uploadMediaAsset } from '@/services/media'
import { generateAutoVideoEdit, getCreatorProject, getProjectActions, getProjectGenerations } from '@/services/projects'
import type { MediaAsset } from '@/types/media'
import {
  autoVideoCaptionStyleOptions,
  autoVideoDurationOptions,
  autoVideoEditingStyleOptions,
  autoVideoPacingOptions,
  type CreatorGeneration,
  type CreatorProject,
  type GenerateAutoVideoEditPayload,
  type ProjectAction,
} from '@/types/project'
import { MEDIA_ACCEPT, MEDIA_MAX_SIZE_BYTES, validateMediaFile } from '@/utils/mediaValidation'

const { t } = useTranslations()
const route = useRoute()
const router = useRouter()

type SourceMode = 'analysis' | 'raw_video'
type VoiceoverMode = 'none' | 'generation' | 'audio_asset' | 'new'

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const generations = ref<CreatorGeneration[]>([])
const mediaAssets = ref<MediaAsset[]>([])
const sourceMode = ref<SourceMode>('analysis')
const selectedAnalysisId = ref<number | null>(null)
const selectedVideoAssetId = ref<number | null>(null)
const voiceoverMode = ref<VoiceoverMode>('none')
const selectedVoiceoverGenerationId = ref<number | null>(null)
const selectedVoiceoverAudioAssetId = ref<number | null>(null)
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const generated = ref<CreatorGeneration | null>(null)
const editingStyle = ref('ugc_natural')
const pacing = ref('medium')
const captionStyle = ref('clean_subtitles')
const durationSeconds = ref(30)
const cta = ref('')
const musicDirection = ref('')
const notes = ref('')
const isLoading = ref(true)
const isUploading = ref(false)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')
const uploadError = ref('')

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const autoEditorAction = computed(() => actions.value.find((item) => item.key === 'generate_auto_video_edit') ?? null)
const videoToCampaignAction = computed(() => actions.value.find((item) => item.key === 'generate_video_to_campaign') ?? null)
const voiceoverAction = computed(() => actions.value.find((item) => item.key === 'generate_voiceover_from_script') ?? null)
const videoCampaignGenerations = computed(() => generations.value.filter((item) => item.job_type === 'generate_video_to_campaign' && item.status === 'completed'))
const voiceoverGenerations = computed(() => generations.value.filter((item) => item.job_type === 'generate_ai_voiceover' && item.status === 'completed'))
const sourceVideos = computed(() => mediaAssets.value.filter((asset) => asset.type === 'video'))
const voiceoverAudioAssets = computed(() => mediaAssets.value.filter((asset) => asset.type === 'audio' && ['voiceover_audio', 'audio_track'].includes(asset.usage_role ?? 'audio_track')))
const isUpgradeRequired = computed(() => autoEditorAction.value?.upgrade_required === true || autoEditorAction.value?.action_mode === 'upgrade')
const includeVoiceover = computed(() => voiceoverMode.value !== 'none')
const costBreakdown = computed(() => {
  const base = autoEditorAction.value?.credit_cost ?? 0
  const videoAnalysis = sourceMode.value === 'analysis' && selectedAnalysisId.value !== null
    ? 0
    : videoToCampaignAction.value?.credit_cost ?? 0
  const voiceover = voiceoverMode.value === 'new' ? voiceoverAction.value?.credit_cost ?? 0 : 0

  return {
    base,
    videoAnalysis,
    voiceover,
    total: base + videoAnalysis + voiceover,
  }
})
const resultRoute = computed(() => generated.value === null ? null : `/projects/${projectId.value}/generations/${generated.value.id}`)

function initializeSelection(): void {
  selectedAnalysisId.value = videoCampaignGenerations.value[0]?.id ?? null
  selectedVideoAssetId.value = sourceVideos.value[0]?.id ?? null
  selectedVoiceoverGenerationId.value = voiceoverGenerations.value[0]?.id ?? null
  selectedVoiceoverAudioAssetId.value = voiceoverAudioAssets.value[0]?.id ?? null
  sourceMode.value = selectedAnalysisId.value === null ? 'raw_video' : 'analysis'
  voiceoverMode.value = selectedVoiceoverGenerationId.value !== null ? 'generation' : 'none'
}

function chooseFile(event: Event): void {
  uploadError.value = ''
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) {
    selectedFile.value = null
    return
  }

  const validationError = validateMediaFile(file)

  if (validationError) {
    uploadError.value = validationError
    selectedFile.value = null
    input.value = ''
    return
  }

  if (!file.type.startsWith('video/')) {
    uploadError.value = t('videoWorkflows.uploadVideoOnly')
    selectedFile.value = null
    input.value = ''
    return
  }

  selectedFile.value = file
}

async function uploadSelectedVideo(): Promise<void> {
  if (selectedFile.value === null) {
    uploadError.value = t('videoWorkflows.chooseVideoFirst')
    return
  }

  isUploading.value = true
  uploadError.value = ''

  try {
    const asset = await uploadMediaAsset(selectedFile.value, projectId.value, 'source_video')
    mediaAssets.value = [asset, ...mediaAssets.value.filter((item) => item.id !== asset.id)]
    selectedVideoAssetId.value = asset.id
    sourceMode.value = 'raw_video'
    selectedFile.value = null

    if (fileInput.value) {
      fileInput.value.value = ''
    }
  } catch (error: unknown) {
    uploadError.value = error instanceof ApiError ? error.message : t('videoWorkflows.uploadFailed')
  } finally {
    isUploading.value = false
  }
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, assetResponse, generationResponse, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      listProjectMediaAssets(projectId.value),
      getProjectGenerations(projectId.value),
      getProjectActions(projectId.value),
    ])

    project.value = loadedProject
    mediaAssets.value = Array.isArray(assetResponse.data) ? assetResponse.data : []
    generations.value = Array.isArray(generationResponse.data) ? generationResponse.data : []
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
    initializeSelection()
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : t('videoWorkflows.loadFailed')
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isUpgradeRequired.value) {
    await router.push(autoEditorAction.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (sourceMode.value === 'analysis' && selectedAnalysisId.value === null) {
    formError.value = t('videoWorkflows.selectAnalysisOrVideo')
    return
  }

  if (sourceMode.value === 'raw_video' && selectedVideoAssetId.value === null) {
    formError.value = t('videoWorkflows.selectSourceVideo')
    return
  }

  if (voiceoverMode.value === 'generation' && selectedVoiceoverGenerationId.value === null) {
    formError.value = t('videoWorkflows.selectVoiceover')
    return
  }

  if (voiceoverMode.value === 'audio_asset' && selectedVoiceoverAudioAssetId.value === null) {
    formError.value = t('videoWorkflows.selectVoiceover')
    return
  }

  isSubmitting.value = true

  const payload: GenerateAutoVideoEditPayload = {
    source_video_asset_id: sourceMode.value === 'raw_video' ? selectedVideoAssetId.value : null,
    source_video_to_campaign_generation_id: sourceMode.value === 'analysis' ? selectedAnalysisId.value : null,
    include_voiceover: includeVoiceover.value,
    voiceover_generation_id: voiceoverMode.value === 'generation' ? selectedVoiceoverGenerationId.value : null,
    voiceover_audio_asset_id: voiceoverMode.value === 'audio_asset' ? selectedVoiceoverAudioAssetId.value : null,
    platform: 'tiktok',
    format: 'vertical_9_16',
    editing_style: editingStyle.value,
    pacing: pacing.value,
    caption_style: captionStyle.value,
    target_duration_seconds: durationSeconds.value,
    cta: cta.value.trim() || null,
    music_direction: musicDirection.value.trim() || null,
    notes: notes.value.trim() || null,
  }

  try {
    const response = await generateAutoVideoEdit(projectId.value, payload)
    generated.value = response.data
  } catch (error: unknown) {
    if (error instanceof ApiError && (error.code === 'insufficient_credits' || error.status === 402)) {
      formError.value = t('projectActions.insufficientCredits')
    } else {
      formError.value = error instanceof ApiError ? error.message : t('videoWorkflows.generateFailed')
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadPage)
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-5xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <section v-if="isLoading" class="mt-12" aria-live="polite">
        <div class="h-4 w-32 animate-pulse rounded-full bg-neutral-800" />
        <div class="mt-5 h-10 w-4/5 animate-pulse rounded-xl bg-neutral-800" />
        <div class="mt-8 h-80 animate-pulse rounded-[2rem] bg-neutral-900" />
        <p class="mt-5 text-sm text-neutral-500">{{ t('videoWorkflows.loading') }}</p>
      </section>

      <section v-else-if="loadError" class="nusa-card-premium mt-12 rounded-[2rem] p-6">
        <p class="text-sm font-medium text-rose-400">{{ t('videoWorkflows.unavailable') }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ t('videoWorkflows.needsAnotherTry') }}</h1>
        <p role="alert" class="mt-4 leading-7 text-neutral-400">{{ loadError }}</p>
        <button type="button" class="mt-7 min-h-12 rounded-2xl bg-white px-5 font-semibold text-neutral-950" @click="loadPage">
          {{ t('common.tryAgain') }}
        </button>
      </section>

      <template v-else>
        <header class="mt-10">
          <RouterLink :to="`/projects/${projectId}`" class="inline-flex min-h-10 items-center gap-2 rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-emerald-500 hover:bg-emerald-950/30">
            ? {{ t('projectActions.backToWorkspace') }}
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ t('videoWorkflows.autoEditorTitle') }}</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">{{ t('videoWorkflows.autoEditorBody') }}</p>
        </header>

        <section v-if="generated" class="nusa-card-premium mt-8 rounded-[2rem] p-5" data-testid="auto-video-editor-success">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('generation.completed') }}</p>
          <h2 class="mt-3 text-2xl font-bold">{{ t('videoWorkflows.editPlanSaved') }}</h2>
          <p class="mt-3 text-sm leading-6 text-neutral-400">{{ t('videoWorkflows.editPlanSavedBody') }}</p>
          <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <RouterLink v-if="resultRoute" :to="resultRoute" class="flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300">
              {{ t('videoWorkflows.viewEditPlan') }}
            </RouterLink>
            <RouterLink :to="`/projects/${projectId}`" class="flex min-h-11 items-center justify-center rounded-2xl border border-neutral-700 px-4 text-sm font-semibold text-neutral-200 transition hover:border-neutral-500 hover:bg-neutral-900">
              {{ t('projectActions.backToWorkspace') }}
            </RouterLink>
          </div>
        </section>

        <form v-else class="mt-8 grid gap-5 lg:grid-cols-[1.05fr_0.95fr]" @submit.prevent="submit">
          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.videoInput') }}</p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('videoWorkflows.chooseAnalysisOrVideo') }}</h2>

              <div class="mt-5 space-y-3">
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="sourceMode" type="radio" value="analysis" class="mt-1 accent-emerald-400" :disabled="videoCampaignGenerations.length === 0">
                  <span>
                    <span class="block font-semibold text-neutral-100">{{ t('videoWorkflows.reuseVideoCampaignAnalysis') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('videoWorkflows.reuseAnalysisHelp') }}</span>
                  </span>
                </label>
                <select v-if="sourceMode === 'analysis' && videoCampaignGenerations.length" v-model.number="selectedAnalysisId" class="nusa-select">
                  <option v-for="item in videoCampaignGenerations" :key="item.id" :value="item.id">
                    #{{ item.id }} ? {{ item.created_at ?? 'Saved analysis' }}
                  </option>
                </select>

                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="sourceMode" type="radio" value="raw_video" class="mt-1 accent-emerald-400">
                  <span>
                    <span class="block font-semibold text-neutral-100">{{ t('videoWorkflows.useRawVideo') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('videoWorkflows.rawVideoIncludesAnalysis') }}</span>
                  </span>
                </label>
                <select v-if="sourceMode === 'raw_video' && sourceVideos.length" v-model.number="selectedVideoAssetId" class="nusa-select">
                  <option v-for="asset in sourceVideos" :key="asset.id" :value="asset.id">
                    {{ asset.original_name ?? 'Uploaded video' }} ? {{ asset.size_label ?? 'size unavailable' }}
                  </option>
                </select>
                <p v-else-if="sourceMode === 'raw_video'" class="rounded-2xl border border-dashed border-neutral-800 p-4 text-sm text-neutral-500">{{ t('videoWorkflows.noVideoAssets') }}</p>
              </div>

              <label class="nusa-card-soft mt-5 flex cursor-pointer flex-col items-center rounded-2xl border-dashed px-4 py-7 text-center transition hover:border-cyan-300/40">
                <span class="nusa-label">{{ t('videoWorkflows.uploadSourceVideo') }}</span>
                <span class="mt-2 text-xs leading-5 text-neutral-500">MP4, MOV or WEBM ? max {{ MEDIA_MAX_SIZE_BYTES / 1024 / 1024 }} MB</span>
                <input ref="fileInput" type="file" :accept="MEDIA_ACCEPT" class="sr-only" @change="chooseFile">
              </label>
              <p v-if="selectedFile" class="mt-3 break-words rounded-xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-200">{{ selectedFile.name }}</p>
              <p v-if="uploadError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">{{ uploadError }}</p>
              <button type="button" :disabled="isUploading || !selectedFile" class="mt-4 min-h-12 w-full rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400" @click="uploadSelectedVideo">
                {{ isUploading ? t('videoWorkflows.uploading') : t('videoWorkflows.uploadAndUseVideo') }}
              </button>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.voiceoverInput') }}</p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('videoWorkflows.chooseVoiceoverOption') }}</h2>
              <div class="mt-5 space-y-3">
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="voiceoverMode" type="radio" value="none" class="mt-1 accent-emerald-400">
                  <span>{{ t('videoWorkflows.noVoiceover') }}</span>
                </label>
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="voiceoverMode" type="radio" value="generation" class="mt-1 accent-emerald-400" :disabled="voiceoverGenerations.length === 0">
                  <span>{{ t('videoWorkflows.reuseVoiceoverGeneration') }}</span>
                </label>
                <select v-if="voiceoverMode === 'generation' && voiceoverGenerations.length" v-model.number="selectedVoiceoverGenerationId" class="nusa-select">
                  <option v-for="item in voiceoverGenerations" :key="item.id" :value="item.id">#{{ item.id }} ? {{ item.created_at ?? 'Voiceover' }}</option>
                </select>
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="voiceoverMode" type="radio" value="audio_asset" class="mt-1 accent-emerald-400" :disabled="voiceoverAudioAssets.length === 0">
                  <span>{{ t('videoWorkflows.reuseAudioAsset') }}</span>
                </label>
                <select v-if="voiceoverMode === 'audio_asset' && voiceoverAudioAssets.length" v-model.number="selectedVoiceoverAudioAssetId" class="nusa-select">
                  <option v-for="asset in voiceoverAudioAssets" :key="asset.id" :value="asset.id">{{ asset.original_name ?? 'Voiceover audio' }}</option>
                </select>
                <label class="flex items-start gap-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">
                  <input v-model="voiceoverMode" type="radio" value="new" class="mt-1 accent-emerald-400">
                  <span>
                    <span class="block font-semibold text-neutral-100">{{ t('videoWorkflows.includeNewVoiceover') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-neutral-500">{{ t('videoWorkflows.newVoiceoverBetaGuide') }}</span>
                  </span>
                </label>
              </div>
            </div>
          </section>

          <section class="space-y-5">
            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.editSettings') }}</p>
              <h2 class="mt-2 text-xl font-semibold">{{ t('videoWorkflows.configureEditPlan') }}</h2>
              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="nusa-label">{{ t('videoWorkflows.editingStyle') }}</span>
                  <select v-model="editingStyle" class="nusa-select mt-2">
                    <option v-for="option in autoVideoEditingStyleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">{{ t('videoWorkflows.pacing') }}</span>
                  <select v-model="pacing" class="nusa-select mt-2">
                    <option v-for="option in autoVideoPacingOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">{{ t('videoWorkflows.captionStyle') }}</span>
                  <select v-model="captionStyle" class="nusa-select mt-2">
                    <option v-for="option in autoVideoCaptionStyleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">{{ t('videoWorkflows.duration') }}</span>
                  <select v-model.number="durationSeconds" class="nusa-select mt-2">
                    <option v-for="duration in autoVideoDurationOptions" :key="duration" :value="duration">{{ duration }}s</option>
                  </select>
                </label>
              </div>
              <label class="mt-4 block">
                <span class="nusa-label">CTA</span>
                <input v-model="cta" class="nusa-input mt-2" type="text" :placeholder="t('videoWorkflows.ctaPlaceholder')">
              </label>
              <label class="mt-4 block">
                <span class="nusa-label">{{ t('videoWorkflows.musicDirection') }}</span>
                <textarea v-model="musicDirection" class="nusa-textarea mt-2 min-h-20" />
              </label>
              <label class="mt-4 block">
                <span class="nusa-label">{{ t('common.notes') }}</span>
                <textarea v-model="notes" class="nusa-textarea mt-2 min-h-20" />
              </label>
            </div>

            <div class="nusa-card-premium rounded-[2rem] p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.costBreakdown') }}</p>
              <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">{{ t('videoWorkflows.baseEditor') }}: {{ costBreakdown.base }}</p>
                <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">{{ t('videoWorkflows.videoAnalysis') }}: {{ costBreakdown.videoAnalysis }}</p>
                <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">{{ t('videoWorkflows.voiceover') }}: {{ costBreakdown.voiceover }}</p>
                <p class="rounded-2xl border border-emerald-800 bg-emerald-950/40 p-3 text-sm font-semibold text-emerald-100">{{ t('videoWorkflows.totalCredits') }}: {{ costBreakdown.total }}</p>
              </div>
              <p class="mt-3 text-xs leading-5 text-neutral-500">{{ t('videoWorkflows.costBreakdownHelp') }}</p>
              <p class="mt-3 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-xs leading-5 text-sky-100/80">{{ t('videoWorkflows.renderProcessingCopy') }}</p>
              <p v-if="formError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">{{ formError }}</p>
              <button type="submit" :disabled="isSubmitting" class="mt-5 min-h-12 w-full rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400">
                {{ isSubmitting ? t('videoWorkflows.generating') : t('videoWorkflows.generateAutoVideoEdit') }}
              </button>
            </div>
          </section>
        </form>
      </template>
    </div>
  </main>
</template>
