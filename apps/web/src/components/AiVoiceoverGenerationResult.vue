<script setup lang="ts">
import type { AiVoiceoverResult } from '@/types/project'

defineProps<{ result: AiVoiceoverResult }>()
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Voiceover title
      </p>
      <h2 class="mt-3 text-xl font-bold">{{ result.voiceover_title }}</h2>
      <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-neutral-500">
        Estimated duration
      </p>
      <p class="mt-2 text-sm leading-7 text-neutral-200">
        {{ result.estimated_duration_seconds }} seconds · {{ result.suggested_audio_format }}
      </p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Narration script
      </p>
      <p class="mt-4 whitespace-pre-line text-base leading-8 text-neutral-100">
        {{ result.narration_script }}
      </p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Scene timing
      </p>
      <div class="mt-4 space-y-4">
        <article
          v-for="scene in result.scene_timing"
          :key="scene.timestamp + scene.section"
          class="rounded-2xl border border-neutral-800 bg-neutral-950/70 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <p class="font-semibold">{{ scene.section }}</p>
            <span class="rounded-full bg-neutral-800 px-3 py-1 text-xs text-emerald-300">
              {{ scene.timestamp }}
            </span>
          </div>
          <dl class="mt-4 space-y-3 text-sm">
            <div>
              <dt class="text-neutral-500">Narration focus</dt>
              <dd class="mt-1 leading-6 text-neutral-200">{{ scene.narration_focus }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">Delivery note</dt>
              <dd class="mt-1 leading-6 text-neutral-200">{{ scene.delivery_note }}</dd>
            </div>
          </dl>
        </article>
      </div>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Voice direction
      </p>
      <p class="mt-3 text-sm leading-7 text-neutral-200">{{ result.voice_direction }}</p>
    </section>

    <section class="grid gap-5">
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Pronunciation notes
        </p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="note in result.pronunciation_notes" :key="note">&bull; {{ note }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Pacing notes
        </p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="note in result.pacing_notes" :key="note">&bull; {{ note }}</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
          Platform notes
        </p>
        <ul class="mt-4 space-y-2 text-sm leading-6 text-neutral-300">
          <li v-for="note in result.platform_notes" :key="note">&bull; {{ note }}</li>
        </ul>
      </div>
    </section>

    <section class="rounded-3xl border border-sky-900/60 bg-sky-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-400">
        Mock audio placeholder
      </p>
      <p class="mt-3 text-sm leading-7 text-sky-100/80">
        {{ result.mock_audio.message }}
      </p>
      <p class="mt-3 text-xs text-sky-300">
        Provider: {{ result.mock_audio.provider }} · Status: {{ result.mock_audio.status }}
      </p>
    </section>

    <section class="rounded-3xl border border-neutral-800 bg-neutral-900/80 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
        Usage notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-neutral-300">
        <li v-for="note in result.usage_notes" :key="note">&#10003; {{ note }}</li>
      </ul>
    </section>

    <section class="rounded-3xl border border-amber-900/50 bg-amber-950/20 p-5">
      <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-400">
        Compliance notes
      </p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-amber-100/80">
        <li v-for="note in result.compliance_notes" :key="note">&bull; {{ note }}</li>
      </ul>
    </section>
  </div>
</template>