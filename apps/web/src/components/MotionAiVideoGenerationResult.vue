<script setup lang="ts">
import type { MotionVideoResult } from '@/types/project'
import { formatLabel } from '@/utils/formatters'

defineProps<{ result: MotionVideoResult }>()

function joinedFutureInputs(inputs: string[]): string {
  return inputs.map((input) => formatLabel(input)).join(', ')
}
</script>

<template>
  <div class="space-y-5" data-testid="motion-ai-video-result">
    <section class="nusa-card-premium rounded-[2rem] p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            Motion AI Video
          </p>
          <h2 class="mt-3 text-xl font-bold">{{ result.motion_summary.creative_angle }}</h2>
        </div>
        <span class="rounded-full border border-amber-800 bg-amber-950/60 px-3 py-1 text-xs font-semibold text-amber-200">
          Render coming next
        </span>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">Platform</p>
          <p class="mt-1 text-sm font-semibold">{{ formatLabel(result.motion_summary.platform) }}</p>
        </div>
        <div class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">Duration</p>
          <p class="mt-1 text-sm font-semibold">{{ result.motion_summary.duration }}s</p>
        </div>
        <div class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">Aspect ratio</p>
          <p class="mt-1 text-sm font-semibold">{{ formatLabel(result.motion_summary.aspect_ratio) }}</p>
        </div>
        <div class="rounded-2xl bg-neutral-950/70 p-3">
          <p class="text-xs text-neutral-500">Source</p>
          <p class="mt-1 text-sm font-semibold">{{ formatLabel(result.motion_summary.source_type) }}</p>
        </div>
      </div>
      <p class="mt-4 text-sm leading-7 text-neutral-300">
        This beta result is a structured motion plan. It does not include a rendered MP4 yet.
      </p>
    </section>

    <section class="grid gap-5 lg:grid-cols-[1fr_0.8fr]">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Motion prompt
        </p>
        <p class="mt-4 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
          {{ result.generation_prompt.main_prompt }}
        </p>
        <dl class="mt-4 grid gap-3 text-sm text-neutral-300">
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Camera motion</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.camera_motion }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Lighting</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.lighting_style }}</dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-[0.14em] text-neutral-500">Negative prompt</dt>
            <dd class="mt-1 leading-6">{{ result.generation_prompt.negative_prompt }}</dd>
          </div>
        </dl>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Product / scenario inputs
        </p>
        <dl class="mt-4 space-y-3 text-sm">
          <div>
            <dt class="text-neutral-500">Product image</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              Asset #{{ result.asset_plan.product_image_asset_id }}
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Scenario image</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.scenario_image_asset_id">
                Asset #{{ result.asset_plan.scenario_image_asset_id }}
              </span>
              <span v-else>Not selected</span>
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Campaign source</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.campaign_generation_id">
                Generation #{{ result.asset_plan.campaign_generation_id }}
              </span>
              <span v-else>Project brief</span>
            </dd>
          </div>
          <div>
            <dt class="text-neutral-500">Voiceover source</dt>
            <dd class="mt-1 font-semibold text-neutral-100">
              <span v-if="result.asset_plan.voiceover_generation_id">
                Generation #{{ result.asset_plan.voiceover_generation_id }}
              </span>
              <span v-else>Plan only</span>
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Scene plan timeline
      </p>
      <div class="mt-4 space-y-3">
        <article
          v-for="scene in result.scene_plan"
          :key="scene.scene_number"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
        >
          <p class="text-xs font-semibold text-emerald-300">
            {{ scene.start_seconds }}–{{ scene.end_seconds }}s · Scene {{ scene.scene_number }}
          </p>
          <p class="mt-3 text-sm leading-6 text-neutral-200">{{ scene.visual_direction }}</p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            Product action: {{ scene.product_action }}
          </p>
          <p class="mt-2 text-sm leading-6 text-neutral-400">
            Camera: {{ scene.camera_direction }}
          </p>
          <p class="mt-2 text-xs font-semibold text-neutral-300">
            Caption: {{ scene.overlay_or_caption }}
          </p>
        </article>
      </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Script / voiceover plan
        </p>
        <p class="mt-4 rounded-2xl bg-neutral-950/70 p-4 text-sm leading-7 text-neutral-200">
          {{ result.script_plan.voiceover_ready_script }}
        </p>
      </div>

      <div class="nusa-card-premium rounded-[2rem] p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Captions / CTA
        </p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="caption in result.script_plan.caption_lines" :key="caption">
            • {{ caption }}
          </li>
        </ul>
        <p class="mt-4 rounded-2xl border border-emerald-900/60 bg-emerald-950/20 p-3 text-sm font-semibold text-emerald-100">
          CTA: {{ result.script_plan.CTA_line }}
        </p>
      </div>
    </section>

    <section class="nusa-card-premium rounded-[2rem] p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Provider readiness
      </p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          Provider: {{ result.provider_readiness.provider }}
        </p>
        <p class="rounded-2xl bg-neutral-950/70 p-3 text-sm text-neutral-300">
          Type: {{ formatLabel(result.provider_readiness.recommended_provider_type) }}
        </p>
      </div>
      <p class="mt-4 text-sm leading-7 text-neutral-400">
        Future provider inputs:
        {{ joinedFutureInputs(result.provider_readiness.future_provider_inputs) }}.
      </p>
      <p class="mt-4 rounded-2xl border border-amber-900/50 bg-amber-950/20 p-4 text-sm leading-6 text-amber-100/80">
        Real motion video rendering coming next.
      </p>
    </section>

    <section class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">
        Compliance notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="item in result.compliance_notes" :key="item">• {{ item }}</li>
      </ul>
    </section>
  </div>
</template>
