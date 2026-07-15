<script setup lang="ts">
import MotionAiVideoGenerationResult from '@/components/MotionAiVideoGenerationResult.vue'
import type { MotionVideoResult } from '@/types/project'

defineProps<{ result: MotionVideoResult }>()
</script>

<template>
  <MotionAiVideoGenerationResult :result="result" />
</template>