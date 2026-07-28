<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import ProjectAssetInput from '@/components/ProjectAssetInput.vue'
import { useTranslations } from '@/i18n'
import { ApiError } from '@/services/api'
import { generateVideoToCampaign, getCreatorProject, getProjectActions } from '@/services/projects'
import {
  campaignLanguageOptions,
  campaignToneOptions,
  type CreatorProject,
  type GenerateVideoToCampaignPayload,
  type ProjectAction,
} from '@/types/project'
import { positiveInt } from '@/utils/workflowLineage'

const { t } = useTranslations()
const route = useRoute()
const router = useRouter()

const project = ref<CreatorProject | null>(null)
const actions = ref<ProjectAction[]>([])
const selectedVideoAssetId = ref<number | null>(null)
const productName = ref('')
const productDescription = ref('')
const targetAudience = ref('')
const campaignContext = ref('')
const language = ref('english')
const tone = ref('natural')
const notes = ref('')
const isLoading = ref(true)
const isSubmitting = ref(false)
const loadError = ref('')
const formError = ref('')

const projectId = computed(() => {
  const value = Array.isArray(route.params.projectId) ? route.params.projectId[0] : route.params.projectId

  return value || ''
})
const action = computed(() => actions.value.find((item) => item.key === 'generate_video_to_campaign') ?? null)
const costLabel = computed(() => {
  const cost = action.value?.credit_cost

  if (cost === null || cost === undefined) {
    return t('tools.creditCostComingSoon')
  }

  if (cost === 0) {
    return t('projectActions.noCreditCharge')
  }

  return cost === 1 ? t('projectActions.oneCredit') : t('projectActions.manyCredits', { credits: cost })
})
const isUpgradeRequired = computed(() => action.value?.upgrade_required === true || action.value?.action_mode === 'upgrade')
const preselectedSourceVideoAssetId = computed(() =>
  positiveInt(Array.isArray(route.query.sourceVideoAssetId) ? route.query.sourceVideoAssetId[0] : route.query.sourceVideoAssetId),
)
const fromGenerationId = computed(() =>
  positiveInt(Array.isArray(route.query.fromGenerationId) ? route.query.fromGenerationId[0] : route.query.fromGenerationId),
)
const hasMotionVideoPreselection = computed(() =>
  preselectedSourceVideoAssetId.value !== null && fromGenerationId.value !== null,
)

function initializeForm(loadedProject: CreatorProject): void {
  const input = (loadedProject.input_payload ?? {}) as Record<string, unknown>
  productName.value = typeof input.product_name === 'string' ? input.product_name : loadedProject.title
  productDescription.value = typeof input.product_description === 'string' ? input.product_description : ''
  targetAudience.value = typeof input.target_audience === 'string' ? input.target_audience : ''
  language.value = typeof input.language === 'string' ? input.language : 'english'
  tone.value = typeof input.tone === 'string' ? input.tone : 'natural'
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  loadError.value = ''

  try {
    const [loadedProject, actionResponse] = await Promise.all([
      getCreatorProject(projectId.value),
      getProjectActions(projectId.value),
    ])

    project.value = loadedProject
    actions.value = Array.isArray(actionResponse.data) ? actionResponse.data : []
    initializeForm(loadedProject)

    if (preselectedSourceVideoAssetId.value !== null) {
      selectedVideoAssetId.value = preselectedSourceVideoAssetId.value
    }
  } catch (error: unknown) {
    loadError.value = error instanceof ApiError ? error.message : t('videoWorkflows.loadFailed')
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  formError.value = ''

  if (isUpgradeRequired.value) {
    await router.push(action.value?.primary_cta?.route ?? '/pricing')
    return
  }

  if (selectedVideoAssetId.value === null) {
    formError.value = t('videoWorkflows.selectSourceVideo')
    return
  }

  isSubmitting.value = true

  const payload: GenerateVideoToCampaignPayload = {
    source_video_asset_id: selectedVideoAssetId.value,
    product_name: productName.value.trim() || null,
    product_description: productDescription.value.trim() || null,
    target_audience: targetAudience.value.trim() || null,
    campaign_context: campaignContext.value.trim() || null,
    language: language.value,
    tone: tone.value,
    platform: 'tiktok',
    notes: notes.value.trim() || null,
  }

  try {
    const response = await generateVideoToCampaign(projectId.value, payload)
    await router.push(`/projects/${projectId.value}/generations/${response.data.id}`)
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
            ← Back to workspace
          </RouterLink>
          <p class="mt-7 text-sm font-medium text-emerald-400">{{ project?.title }}</p>
          <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ t('videoWorkflows.videoToCampaignTitle') }}</h1>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-neutral-400">{{ t('videoWorkflows.videoToCampaignBody') }}</p>
        </header>

        <section class="mt-8 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
          <div class="nusa-card-premium rounded-[2rem] p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.sourceVideo') }}</p>
            <h2 class="mt-2 text-xl font-semibold">{{ t('videoWorkflows.chooseSourceVideo') }}</h2>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
              {{ t('videoWorkflows.productVideoHelper') }}
            </p>

            <p
              v-if="hasMotionVideoPreselection"
              class="mt-5 rounded-2xl border border-sky-900/60 bg-sky-950/20 p-3 text-sm leading-6 text-sky-100/80"
              data-testid="video-analysis-motion-preselect-note"
            >
              Generated by Motion AI Video. Review the selected MP4 below, then explicitly click Analyze footage with AI when you are ready.
            </p>

            <ProjectAssetInput
              v-model="selectedVideoAssetId"
              :project-id="projectId"
              accepted-types="video"
              usage-role="campaign_source_video"
              :title="t('videoWorkflows.uploadOrChooseProductVideo')"
              :helper-text="t('videoWorkflows.productVideoHelper')"
              required
              auto-select-first
              class="mt-5"
            />
          </div>

          <form class="nusa-card-premium rounded-[2rem] p-5" @submit.prevent="submit">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">{{ t('videoWorkflows.campaignContext') }}</p>
            <h2 class="mt-2 text-xl font-semibold">{{ t('videoWorkflows.refineCampaignInputs') }}</h2>
            <p class="mt-3 rounded-2xl border border-neutral-800 bg-neutral-950/60 p-3 text-sm text-neutral-300">{{ costLabel }}</p>

            <div class="mt-5 space-y-4">
              <label class="block">
                <span class="nusa-label">{{ t('campaignForm.productName') }}</span>
                <input v-model="productName" class="nusa-input mt-2" type="text">
              </label>
              <label class="block">
                <span class="nusa-label">{{ t('campaignForm.productDescription') }}</span>
                <textarea v-model="productDescription" class="nusa-textarea mt-2 min-h-24" />
              </label>
              <label class="block">
                <span class="nusa-label">{{ t('campaignForm.targetAudience') }}</span>
                <input v-model="targetAudience" class="nusa-input mt-2" type="text">
              </label>
              <label class="block">
                <span class="nusa-label">{{ t('videoWorkflows.campaignContext') }}</span>
                <textarea v-model="campaignContext" class="nusa-textarea mt-2 min-h-24" :placeholder="t('videoWorkflows.contextPlaceholder')" />
              </label>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="nusa-label">{{ t('campaignForm.language') }}</span>
                  <select v-model="language" class="nusa-select mt-2">
                    <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="nusa-label">{{ t('campaignForm.tone') }}</span>
                  <select v-model="tone" class="nusa-select mt-2">
                    <option v-for="option in campaignToneOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
              </div>
              <label class="block">
                <span class="nusa-label">{{ t('common.notes') }}</span>
                <textarea v-model="notes" class="nusa-textarea mt-2 min-h-20" />
              </label>
            </div>

            <p v-if="formError" role="alert" class="mt-4 rounded-xl border border-rose-900/60 bg-rose-950/30 p-3 text-sm leading-6 text-rose-200">{{ formError }}</p>
            <button type="submit" :disabled="isSubmitting" class="mt-5 min-h-12 w-full rounded-2xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400">
              {{ isSubmitting ? t('videoWorkflows.generating') : t('videoWorkflows.generateVideoCampaign') }}
            </button>
          </form>
        </section>
      </template>
    </div>
  </main>
</template>
