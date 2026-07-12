<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

import AppNav from '@/components/AppNav.vue'
import { t } from '@/i18n'
import { ApiError } from '@/services/api'
import { getCreatorTools } from '@/services/tools'
import { useAuthStore } from '@/stores/auth'
import type { CreatorTool, ToolOutputType, ToolStatus } from '@/types/tool'
import { formatLabel } from '@/utils/formatters'

interface StudioToolCard {
  key: string
  title: string
  short_description: string
  long_description: string | null
  category: string
  output_type: ToolOutputType | null
  status: ToolStatus
  route: string | null
  credit_cost: number
  credit_cost_label: string | null
  badge_label: string | null
  sort_order: number
  source: 'api' | 'static'
}

interface StudioToolSection {
  key: string
  title: string
  description: string
  tools: StudioToolCard[]
}

const tools = ref<CreatorTool[]>([])
const isLoading = ref(true)
const error = ref('')
const auth = useAuthStore()

const visibleTools = computed(() => tools.value.filter((tool) => tool.status !== 'disabled'))
const toolsByKey = computed(() =>
  new Map(visibleTools.value.map((tool) => [tool.key, tool])),
)

const groupedToolSections = computed<StudioToolSection[]>(() => [
  {
    key: 'campaign_content',
    title: t('tools.categories.campaignContent.title'),
    description: t('tools.categories.campaignContent.description'),
    tools: compactTools([
      cardFromApi('affiliate_campaign', {
        title: t('tools.productContentKit.title'),
        short_description: t('tools.productContentKit.description'),
      }),
      cardFromApi('video_to_campaign', {
        title: t('tools.videoToCampaignInsight.title'),
        short_description: t('tools.videoToCampaignInsight.description'),
      }),
      comingSoonCard('hook_generator', t('tools.roadmapCards.hookGenerator'), 'text_insight', 30),
      comingSoonCard('caption_hashtag_pack', t('tools.roadmapCards.captionHashtagPack'), 'script_pack', 31),
      comingSoonCard('script_variations', t('tools.roadmapCards.scriptVariations'), 'script_pack', 32),
    ]),
  },
  {
    key: 'voice',
    title: t('tools.categories.voice.title'),
    description: t('tools.categories.voice.description'),
    tools: compactTools([
      cardFromApi('ai_voiceover'),
    ]),
  },
  {
    key: 'video',
    title: t('tools.categories.video.title'),
    description: t('tools.categories.video.description'),
    tools: compactTools([
      cardFromApi('auto_video_editor'),
    ]),
  },
  {
    key: 'assets',
    title: t('tools.categories.assets.title'),
    description: t('tools.categories.assets.description'),
    tools: [
      staticCard({
        key: 'media_library',
        title: t('tools.mediaLibrary.title'),
        short_description: t('tools.mediaLibrary.description'),
        category: 'assets',
        output_type: 'mixed_media',
        status: 'available',
        route: '/media',
        credit_cost: 0,
        credit_cost_label: t('projectActions.noCreditCharge'),
        badge_label: t('common.available'),
        sort_order: 10,
      }),
    ],
  },
  {
    key: 'roadmap',
    title: t('tools.categories.roadmap.title'),
    description: t('tools.categories.roadmap.description'),
    tools: compactTools([
      cardFromApi('fashion_try_on', { title: t('tools.roadmapCards.fashionStudio') }),
      comingSoonCard('product_photo_studio', t('tools.roadmapCards.productPhotoStudio'), 'image', 52),
      comingSoonCard('product_ad_templates', t('tools.roadmapCards.productAdTemplates'), 'mixed_media', 53),
      cardFromApi('ai_avatar_presenter', { title: t('tools.roadmapCards.aiAvatarPresenter') }),
      cardFromApi('motion_ai_video', { title: t('tools.roadmapCards.motionAiVideo') }),
      comingSoonCard('brand_kit', t('tools.roadmapCards.brandKit'), 'mixed_media', 56),
      comingSoonCard('content_calendar', t('tools.roadmapCards.contentCalendar'), 'text_insight', 57),
    ]),
  },
])

function compactTools(candidates: Array<StudioToolCard | null>): StudioToolCard[] {
  return candidates.filter((tool): tool is StudioToolCard => tool !== null)
}

function cardFromApi(
  key: string,
  overrides: Partial<Pick<StudioToolCard, 'title' | 'short_description' | 'long_description'>> = {},
): StudioToolCard | null {
  const tool = toolsByKey.value.get(key)

  if (!tool) {
    return null
  }

  return {
    key: tool.key,
    title: overrides.title ?? tool.title,
    short_description: overrides.short_description ?? tool.short_description,
    long_description: overrides.long_description ?? tool.long_description,
    category: tool.category,
    output_type: tool.output_type,
    status: tool.status,
    route: tool.route,
    credit_cost: tool.credit_cost,
    credit_cost_label: tool.credit_cost_label,
    badge_label: tool.badge_label,
    sort_order: tool.sort_order,
    source: 'api',
  }
}

function staticCard(tool: Omit<StudioToolCard, 'source' | 'long_description'> & { long_description?: string | null }): StudioToolCard {
  return {
    ...tool,
    long_description: tool.long_description ?? null,
    source: 'static',
  }
}

function comingSoonCard(
  key: string,
  title: string,
  outputType: ToolOutputType | null,
  sortOrder: number,
): StudioToolCard {
  return staticCard({
    key,
    title,
    short_description: t('tools.roadmapCards.comingSoonDescription'),
    category: 'roadmap',
    output_type: outputType,
    status: 'coming_soon',
    route: null,
    credit_cost: 0,
    credit_cost_label: t('tools.creditCostComingSoon'),
    badge_label: t('common.comingSoon'),
    sort_order: sortOrder,
  })
}

function toolDestination(tool: StudioToolCard): RouteLocationRaw {
  const targetPath = tool.source === 'api'
    ? '/dashboard?tool=' + encodeURIComponent(tool.key)
    : tool.route ?? '/tools'

  return auth.isAuthenticated
    ? targetPath
    : { path: '/login', query: { redirect: targetPath } }
}

function isActionable(tool: StudioToolCard): boolean {
  return tool.status === 'available' && tool.route !== null
}

function outputTypeLabel(outputType: ToolOutputType | null): string | null {
  return outputType ? t('tools.outputTypes.' + outputType) : null
}

function statusClasses(status: StudioToolCard['status']): string {
  if (status === 'available') {
    return 'border-emerald-800 bg-emerald-950 text-emerald-300'
  }

  if (status === 'maintenance') {
    return 'border-amber-800 bg-amber-950 text-amber-300'
  }

  return 'border-neutral-700 bg-neutral-800 text-neutral-300'
}

async function loadTools(): Promise<void> {
  isLoading.value = true
  error.value = ''

  try {
    tools.value = await getCreatorTools()
  } catch (requestError: unknown) {
    error.value =
      requestError instanceof ApiError
        ? requestError.message
        : t('errors.toolsLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadTools()
})
</script>

<template>
  <main class="nusa-page min-h-screen text-white">
    <div class="nusa-page-container mx-auto min-h-screen max-w-6xl px-5 pb-16 pt-5 sm:px-6">
      <AppNav />

      <header class="mx-auto max-w-3xl pt-12 text-center lg:pt-16">
        <p class="text-sm font-medium text-emerald-400">{{ t('tools.kicker') }}</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight">{{ t('tools.headline') }}</h1>
        <p class="mt-4 text-sm leading-6 text-neutral-400">
          {{ t('tools.body') }}
        </p>
      </header>

      <div v-if="isLoading" class="mt-10 space-y-4" :aria-label="t('tools.loading')">
        <div
          v-for="index in 3"
          :key="index"
          class="h-52 animate-pulse rounded-[2rem] border border-slate-800/70 bg-slate-900/60"
        />
      </div>

      <div
        v-else-if="error"
        role="alert"
        class="mt-10 rounded-3xl border border-rose-900/60 bg-rose-950/30 p-5"
      >
        <h2 class="text-lg font-semibold text-rose-100">{{ t('tools.unavailable') }}</h2>
        <p class="mt-2 text-sm leading-6 text-rose-200">{{ error }}</p>
        <button
          type="button"
          class="mt-5 min-h-11 rounded-xl bg-white px-5 text-sm font-semibold text-neutral-950 transition hover:bg-emerald-300"
          @click="loadTools"
        >
          {{ t('common.tryAgain') }}
        </button>
      </div>

      <template v-else>
        <section
          v-for="section in groupedToolSections"
          :key="section.key"
          :data-testid="'tool-category-section-' + section.key"
          class="mt-10"
        >
          <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
              {{ t('tools.workflowCategory') }}
            </p>
            <h2 class="mt-1 text-2xl font-semibold">{{ section.title }}</h2>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
              {{ section.description }}
            </p>
          </div>

          <div v-if="section.tools.length" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template v-for="tool in section.tools" :key="tool.key">
              <RouterLink
                v-if="isActionable(tool)"
                :to="toolDestination(tool)"
                :data-testid="'tool-card-' + tool.key"
                class="nusa-card-interactive nusa-focus-ring group block rounded-[2rem] p-5 transition"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-400">
                      {{ formatLabel(tool.category) }}
                    </p>
                    <h3 class="mt-2 break-words text-xl font-semibold">{{ tool.title }}</h3>
                  </div>
                  <span class="nusa-badge shrink-0 rounded-full px-3 py-1 text-xs font-bold">
                    {{ tool.badge_label ?? t('common.available') }}
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span
                    v-if="outputTypeLabel(tool.output_type)"
                    class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300"
                  >
                    {{ outputTypeLabel(tool.output_type) }}
                  </span>
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ tool.credit_cost_label ?? tool.credit_cost + ' credits' }}
                  </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-neutral-300">
                  {{ tool.short_description }}
                </p>
                <p v-if="tool.long_description" class="mt-3 text-sm leading-6 text-neutral-500">
                  {{ tool.long_description }}
                </p>

                <div class="mt-5 flex items-center justify-between border-t border-neutral-800 pt-4">
                  <span class="text-xs font-medium text-neutral-400">
                    {{ tool.status === 'available' ? t('tools.ready') : formatLabel(tool.status) }}
                  </span>
                  <span class="text-sm font-semibold text-emerald-300">
                    {{ auth.isAuthenticated ? t('tools.openTool') : t('tools.loginToUse') }}
                    <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </RouterLink>

              <article
                v-else
                :data-testid="'tool-card-' + tool.key"
                aria-disabled="true"
                class="nusa-card-soft rounded-[2rem] p-5 opacity-80"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-500">
                      {{ formatLabel(tool.category) }}
                    </p>
                    <h3 class="mt-2 break-words text-lg font-semibold text-neutral-200">{{ tool.title }}</h3>
                  </div>
                  <span
                    class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold"
                    :class="statusClasses(tool.status)"
                  >
                    {{ tool.badge_label ?? formatLabel(tool.status) }}
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <span
                    v-if="outputTypeLabel(tool.output_type)"
                    class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300"
                  >
                    {{ outputTypeLabel(tool.output_type) }}
                  </span>
                  <span class="rounded-full border border-neutral-700 bg-neutral-950 px-2.5 py-1 text-[11px] font-semibold text-neutral-300">
                    {{ tool.credit_cost_label ?? t('tools.creditCostComingSoon') }}
                  </span>
                </div>

                <p class="mt-3 text-sm leading-6 text-neutral-400">{{ tool.short_description }}</p>
                <p v-if="tool.status === 'maintenance'" class="mt-4 text-sm leading-6 text-amber-200">
                  {{ t('tools.maintenanceBody') }}
                </p>
                <p v-else class="mt-4 text-xs font-medium text-neutral-600">
                  {{ tool.status === 'coming_soon' ? t('tools.comingSoon') : formatLabel(tool.status) }}
                </p>
              </article>
            </template>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>