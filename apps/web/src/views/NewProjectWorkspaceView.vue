<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import SearchableCombobox from '@/components/SearchableCombobox.vue'
import { resolveWorkflowDefinition, workflowRouteForProject } from '@/config/workflows'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { createWorkspaceProject } from '@/services/projects'
import {
  campaignLanguageOptions,
  campaignToneOptions,
  workspaceMainGoalOptions,
  workspacePlatformOptions,
  type WorkspaceFormPayload,
} from '@/types/project'
import { focusFirstInvalidField } from '@/utils/forms'

type FormField = keyof WorkspaceFormPayload | 'title'
type FormErrors = Partial<Record<FormField, string>>

const route = useRoute()
const router = useRouter()
const formElement = ref<HTMLFormElement | null>(null)
const isSubmitting = ref(false)
const submitError = ref('')
const fieldErrors = ref<FormErrors>({})
const productCategoryOptions = [
  'Beauty & skincare',
  'Fashion',
  'Accessories',
  'Jewelry',
  'Home & living',
  'Kitchen appliance',
  'Home appliance',
  'Gaming accessory',
  'Tech gadget',
  'Smartphone accessory',
  'Desk setup',
  'Fitness',
  'Health & wellness',
  'Food & beverage',
  'Coffee & drinks',
  'Baby & kids',
  'Pet product',
  'Travel accessory',
  'Outdoor gear',
  'Car accessory',
  'Digital product',
  'Online course',
  'Software / app',
  'Creator tool',
  'Handmade product',
  'Local brand',
  'Other',
]


const workflowIntent = computed(() => {
  const value = route.query.workflow ?? route.query.tool
  const workflow = resolveWorkflowDefinition(value)

  return workflow?.status === 'available' ? workflow : null
})
const workflowIntentLabel = computed(() =>
  workflowIntent.value === null ? '' : t('workflows.' + workflowIntent.value.key + '.title'),
)
const workflowIntentFamily = computed(() =>
  workflowIntent.value === null ? '' : t('workflows.families.' + workflowIntent.value.familyKey + '.title'),
)

const form = reactive<WorkspaceFormPayload & { title: string }>({
  title: '',
  product_name: '',
  product_category: '',
  content_idea: '',
  product_description: '',
  target_audience: '',
  language: 'english',
  tone: 'natural',
  platform: 'tiktok',
  main_goal: 'affiliate_content',
  key_selling_points: '',
  claims_to_avoid: '',
  brand_notes: '',
  cta_preference: '',
  competitor_notes: '',
  notes: '',
})

function optionalText(value: string | null | undefined): string | null {
  const trimmed = value?.trim() ?? ''

  return trimmed === '' ? null : trimmed
}

function validateForm(): boolean {
  const errors: FormErrors = {}

  if (!form.title.trim()) {
    errors.title = t('projectWorkspace.errors.title')
  } else if (form.title.trim().length > 255) {
    errors.title = t('projectWorkspace.errors.titleLength')
  }

  if (!form.product_name.trim()) {
    errors.product_name = t('projectWorkspace.errors.productName')
  }


  if (!form.product_description.trim()) {
    errors.product_description = t('projectWorkspace.errors.projectContext')
  }

  if (!form.target_audience.trim()) {
    errors.target_audience = t('projectWorkspace.errors.targetAudience')
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitWorkspace(): Promise<void> {
  submitError.value = ''

  if (!validateForm()) {
    void focusFirstInvalidField(formElement.value)
    return
  }

  isSubmitting.value = true

  try {
    const title = form.title.trim()
    const productName = form.product_name.trim()
    const project = await createWorkspaceProject(
      {
        product_name: productName,
        product_category: optionalText(form.product_category),
        content_idea: form.content_idea.trim() || productName,
        product_description: form.product_description.trim(),
        target_audience: form.target_audience.trim(),
        language: form.language,
        tone: form.tone,
        platform: form.platform,
        main_goal: form.main_goal,
        key_selling_points: optionalText(form.key_selling_points),
        claims_to_avoid: optionalText(form.claims_to_avoid),
        brand_notes: optionalText(form.brand_notes),
        cta_preference: optionalText(form.cta_preference),
        competitor_notes: optionalText(form.competitor_notes),
        notes: optionalText(form.notes),
      },
      title,
    )

    await router.push(
      workflowIntent.value === null
        ? { name: 'project-detail', params: { id: project.id } }
        : workflowRouteForProject(workflowIntent.value, project.id),
    )
  } catch (error: unknown) {
    submitError.value =
      error instanceof ApiError
        ? error.message
        : t('projectWorkspace.errors.createFailed')
    void focusFirstInvalidField(formElement.value)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-3xl px-5 pb-14 pt-5 sm:px-6">
      <AppNav />

      <section class="max-w-2xl pt-8">
        <p class="text-sm font-medium text-emerald-400">{{ t('projectWorkspace.kicker') }}</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight sm:text-4xl">
          {{ t('projectWorkspace.title') }}
        </h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          {{ t('projectWorkspace.body') }}
        </p>
        <p class="mt-4 rounded-2xl border border-emerald-900/50 bg-emerald-950/20 p-4 text-sm leading-6 text-emerald-100/80">
          {{ t('projectWorkspace.reusableBriefHelp') }}
        </p>
        <p
          v-if="workflowIntent"
          class="mt-3 rounded-2xl border border-sky-900/50 bg-sky-950/20 p-4 text-sm leading-6 text-sky-100/80"
          data-testid="project-workflow-intent"
        >
          {{ t('projectWorkspace.workflowIntentHelp', { family: workflowIntentFamily, workflow: workflowIntentLabel }) }}
        </p>
      </section>

      <form ref="formElement" class="nusa-form-card mt-8 space-y-8 rounded-[2rem] p-5 sm:p-6" novalidate @submit.prevent="submitWorkspace">
        <section class="space-y-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('projectWorkspace.sections.productBasics') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('projectWorkspace.sections.productBasicsBody') }}
            </p>
          </div>

          <div>
            <label for="project-title" class="nusa-label">{{ t('projectWorkspace.projectName') }}</label>
            <input
              id="project-title"
              v-model="form.title"
              name="title"
              type="text"
              autocomplete="off"
              maxlength="255"
              :placeholder="t('projectWorkspace.projectNamePlaceholder')"
              :aria-invalid="Boolean(fieldErrors.title)"
              :aria-describedby="fieldErrors.title ? 'project-title-error' : undefined"
              class="nusa-input mt-2"
            />
            <p v-if="fieldErrors.title" id="project-title-error" class="mt-2 text-sm text-rose-400">
              {{ fieldErrors.title }}
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="product-name" class="nusa-label">{{ t('projectWorkspace.productName') }}</label>
              <input
                id="product-name"
                v-model="form.product_name"
                name="product_name"
                type="text"
                autocomplete="off"
                :placeholder="t('projectWorkspace.productNamePlaceholder')"
                :aria-invalid="Boolean(fieldErrors.product_name)"
                :aria-describedby="fieldErrors.product_name ? 'product-name-error' : undefined"
                class="nusa-input mt-2"
              />
              <p v-if="fieldErrors.product_name" id="product-name-error" class="mt-2 text-sm text-rose-400">
                {{ fieldErrors.product_name }}
              </p>
            </div>

            <div>
              <label for="product-category" class="nusa-label">{{ t('projectWorkspace.productCategory') }}</label>
              <SearchableCombobox
                id="product-category"
                v-model="form.product_category"
                name="product_category"
                :options="productCategoryOptions"
                :placeholder="t('projectWorkspace.productCategoryPlaceholder')"
              />
              <p class="mt-2 text-xs leading-5 text-neutral-500">
                {{ t('projectWorkspace.productCategoryHelp') }}
              </p>
            </div>
          </div>

          <div>
            <label for="project-context" class="nusa-label">{{ t('projectWorkspace.projectContext') }}</label>
            <textarea
              id="project-context"
              v-model="form.product_description"
              name="product_description"
              rows="5"
              :placeholder="t('projectWorkspace.projectContextPlaceholder')"
              :aria-invalid="Boolean(fieldErrors.product_description)"
              :aria-describedby="fieldErrors.product_description ? 'project-context-error' : undefined"
              class="nusa-textarea mt-2"
            />
            <p v-if="fieldErrors.product_description" id="project-context-error" class="mt-2 text-sm text-rose-400">
              {{ fieldErrors.product_description }}
            </p>
          </div>

          <div>
            <label for="key-selling-points" class="nusa-label">{{ t('projectWorkspace.keySellingPoints') }}</label>
            <textarea
              id="key-selling-points"
              v-model="form.key_selling_points"
              name="key_selling_points"
              rows="3"
              :placeholder="t('projectWorkspace.keySellingPointsPlaceholder')"
              class="nusa-textarea mt-2"
            />
          </div>
        </section>

        <section class="space-y-5 border-t border-neutral-800 pt-7">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('projectWorkspace.sections.audiencePlatform') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('projectWorkspace.sections.audiencePlatformBody') }}
            </p>
          </div>

          <div>
            <label for="target-audience" class="nusa-label">{{ t('projectWorkspace.targetAudience') }}</label>
            <textarea
              id="target-audience"
              v-model="form.target_audience"
              name="target_audience"
              rows="3"
              :placeholder="t('projectWorkspace.targetAudiencePlaceholder')"
              :aria-invalid="Boolean(fieldErrors.target_audience)"
              :aria-describedby="fieldErrors.target_audience ? 'target-audience-error' : undefined"
              class="nusa-textarea mt-2"
            />
            <p v-if="fieldErrors.target_audience" id="target-audience-error" class="mt-2 text-sm text-rose-400">
              {{ fieldErrors.target_audience }}
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="platform" class="nusa-label">{{ t('projectWorkspace.platform') }}</label>
              <select id="platform" v-model="form.platform" name="platform" class="nusa-select mt-2">
                <option v-for="option in workspacePlatformOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div>
              <label for="language" class="nusa-label">{{ t('projectWorkspace.language') }}</label>
              <select id="language" v-model="form.language" name="language" class="nusa-select mt-2">
                <option v-for="option in campaignLanguageOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
          </div>
        </section>

        <section class="space-y-5 border-t border-neutral-800 pt-7">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('projectWorkspace.sections.styleGoals') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('projectWorkspace.sections.styleGoalsBody') }}
            </p>
          </div>

          <div>
            <label for="main-goal" class="nusa-label">{{ t('projectWorkspace.mainGoal') }}</label>
            <select id="main-goal" v-model="form.main_goal" name="main_goal" class="nusa-select mt-2">
              <option v-for="option in workspaceMainGoalOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <fieldset>
            <legend class="nusa-label">{{ t('projectWorkspace.tone') }}</legend>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <label
                v-for="option in campaignToneOptions"
                :key="option.value"
                class="nusa-choice flex items-center"
                :class="form.tone === option.value ? 'nusa-choice-active' : 'nusa-choice-idle'"
              >
                <input v-model="form.tone" class="sr-only" type="radio" name="tone" :value="option.value" />
                {{ option.label }}
              </label>
            </div>
          </fieldset>

          <div>
            <label for="content-idea" class="nusa-label">{{ t('projectWorkspace.contentIdea') }}</label>
            <input
              id="content-idea"
              v-model="form.content_idea"
              name="content_idea"
              type="text"
              autocomplete="off"
              :placeholder="t('projectWorkspace.contentIdeaPlaceholder')"
              class="nusa-input mt-2"
            />
          </div>

          <div>
            <label for="cta-preference" class="nusa-label">{{ t('projectWorkspace.ctaPreference') }}</label>
            <input
              id="cta-preference"
              v-model="form.cta_preference"
              name="cta_preference"
              type="text"
              autocomplete="off"
              :placeholder="t('projectWorkspace.ctaPreferencePlaceholder')"
              class="nusa-input mt-2"
            />
          </div>
        </section>

        <section class="space-y-5 border-t border-neutral-800 pt-7">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
              {{ t('projectWorkspace.sections.brandNotes') }}
            </p>
            <p class="mt-2 text-sm leading-6 text-neutral-500">
              {{ t('projectWorkspace.sections.brandNotesBody') }}
            </p>
          </div>

          <div>
            <label for="claims-to-avoid" class="nusa-label">{{ t('projectWorkspace.claimsToAvoid') }}</label>
            <textarea
              id="claims-to-avoid"
              v-model="form.claims_to_avoid"
              name="claims_to_avoid"
              rows="3"
              :placeholder="t('projectWorkspace.claimsToAvoidPlaceholder')"
              class="nusa-textarea mt-2"
            />
          </div>

          <div>
            <label for="brand-notes" class="nusa-label">{{ t('projectWorkspace.brandNotes') }}</label>
            <textarea
              id="brand-notes"
              v-model="form.brand_notes"
              name="brand_notes"
              rows="3"
              :placeholder="t('projectWorkspace.brandNotesPlaceholder')"
              class="nusa-textarea mt-2"
            />
          </div>

          <div>
            <label for="competitor-notes" class="nusa-label">{{ t('projectWorkspace.competitorNotes') }}</label>
            <textarea
              id="competitor-notes"
              v-model="form.competitor_notes"
              name="competitor_notes"
              rows="3"
              :placeholder="t('projectWorkspace.competitorNotesPlaceholder')"
              class="nusa-textarea mt-2"
            />
          </div>

          <div>
            <label for="project-notes" class="nusa-label">{{ t('projectWorkspace.notes') }}</label>
            <textarea
              id="project-notes"
              v-model="form.notes"
              name="notes"
              rows="3"
              :placeholder="t('projectWorkspace.notesPlaceholder')"
              class="nusa-textarea mt-2"
            />
          </div>
        </section>

        <div v-if="submitError" role="alert" class="rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200">
          {{ submitError }}
        </div>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="nusa-button-primary nusa-focus-ring flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl px-5 py-4 font-black transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <span v-if="isSubmitting" class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950" aria-hidden="true" />
          {{ isSubmitting ? t('projectWorkspace.creating') : t('projectWorkspace.create') }}
        </button>
      </form>
    </div>
  </main>
</template>