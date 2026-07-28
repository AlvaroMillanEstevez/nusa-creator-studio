<script setup lang="ts">
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'
import type { ProjectType } from '@/types/project'

interface GenerationActionCopy {
  title: string
  description: string
  button: string
  loadingButton: string
}

const props = defineProps<{
  projectType: ProjectType
  hasGeneration: boolean
  isGenerating: boolean
  error: string
  remainingCredits: number | null
  creditCost: number | null
}>()

defineEmits<{ generate: [] }>()

const auth = useAuthStore()
const hasUnlimitedAccess = computed(() => auth.user?.is_unlimited === true)
const creditCostLabel = computed(() => {
  if (hasUnlimitedAccess.value) {
    return 'Unlimited access'
  }

  if (props.creditCost === null) {
    return 'Credit cost unavailable'
  }

  if (props.creditCost === 0) {
    return 'No credit charge'
  }

  return props.creditCost + (props.creditCost === 1 ? ' credit' : ' credits')
})

const copy = computed<GenerationActionCopy>(() => {
  if (props.projectType === 'motion_ai_video') {
    return {
      title: 'Configure Motion AI Video',
      description:
        'Choose text, campaign context or product imagery to generate Motion AI Video from the dedicated setup page.',
      button: props.hasGeneration ? 'Create another motion plan' : 'Configure Motion AI Video',
      loadingButton: 'Opening Motion AI Video...',
    }
  }

  if (props.projectType === 'fashion_try_on') {
    return {
      title: 'Configure AI Fashion Try-On',
      description:
        'Choose a person photo, clothing/product photo, Standard quality settings and permission confirmation before generating.',
      button: props.hasGeneration ? 'Generate another try-on' : 'Setup Try-On',
      loadingButton: 'Opening try-on setup...',
    }
  }

  if (props.projectType === 'ai_voiceover') {
    return {
      title: 'Generate voiceover',
      description:
        'Generate narration audio from your source script when real voiceover is enabled, or keep using the mock narration plan. Tip: paste a Campaign Generator or Product-to-Video script into the source text when creating a voiceover project.',
      button: props.hasGeneration ? 'Generate another voiceover' : 'Generate voiceover',
      loadingButton: 'Generating voiceover...',
    }
  }

  if (props.projectType === 'product_to_video') {
    return {
      title: 'Generate video script pack',
      description:
        'Create a deterministic storyboard, shot list, voiceover plan and production checklist. No video is rendered.',
      button: 'Generate video script pack',
      loadingButton: 'Generating video script pack...',
    }
  }

  if (props.projectType === 'video_enhancer') {
    return {
      title: 'Generate caption pack',
      description:
        'Create a deterministic caption, subtitle timing, cut plan, posting copy and editing notes from this written brief.',
      button: 'Generate caption pack',
      loadingButton: 'Generating caption pack...',
    }
  }

  return {
    title: 'Create campaign content',
    description:
      'Generate deterministic hooks, a TikTok script, caption, hashtags, CTA, and practical filming notes.',
    button: props.hasGeneration ? 'Generate again' : 'Generate campaign',
    loadingButton: 'Generating campaign...',
  }
})
</script>

<template>
  <section
    class="mt-5 rounded-3xl border border-emerald-900/70 bg-gradient-to-br from-neutral-900 to-emerald-950/40 p-5"
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Creator studio
        </p>
        <h2 class="mt-2 text-xl font-semibold">
          {{ hasGeneration ? 'Generate another version' : copy.title }}
        </h2>
      </div>
      <span
        class="shrink-0 rounded-full border border-neutral-700 bg-neutral-950/70 px-3 py-1 text-xs text-neutral-300"
      >
        {{ creditCostLabel }}
      </span>
    </div>

    <p class="mt-3 text-sm leading-6 text-neutral-400">{{ copy.description }}</p>

    <p v-if="hasUnlimitedAccess" class="mt-3 text-sm font-medium text-emerald-300">
      No credit charge for this account
    </p>
    <p v-else-if="remainingCredits !== null" class="mt-3 text-sm font-medium text-emerald-300">
      {{ remainingCredits }} credits remaining
    </p>

    <div
      v-if="error"
      role="alert"
      class="mt-4 rounded-2xl border border-rose-900/60 bg-rose-950/50 px-4 py-3 text-sm leading-6 text-rose-200"
    >
      {{ error }}
    </div>

    <button
      type="button"
      :disabled="isGenerating"
      class="mt-5 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 font-semibold text-neutral-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-neutral-700 disabled:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
      @click="$emit('generate')"
    >
      <span
        v-if="isGenerating"
        class="size-5 animate-spin rounded-full border-2 border-neutral-400 border-t-neutral-950"
        aria-hidden="true"
      />
      {{ isGenerating ? copy.loadingButton : copy.button }}
    </button>
  </section>
</template>
