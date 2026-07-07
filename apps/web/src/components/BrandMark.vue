<script setup lang="ts">
withDefaults(
  defineProps<{
    size?: 'sm' | 'md' | 'lg' | 'xl'
    decorative?: boolean
  }>(),
  {
    size: 'md',
    decorative: true,
  },
)
</script>

<template>
  <svg
    data-testid="brand-mark"
    viewBox="0 0 64 64"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    :aria-hidden="decorative"
    :class="{
      'size-10': size === 'sm',
      'size-12 sm:size-14': size === 'md',
      'size-14 sm:size-16': size === 'lg',
      'size-16 sm:size-20': size === 'xl',
    }"
  >
    <rect width="64" height="64" rx="20" fill="#071024" />
    <rect x="1" y="1" width="62" height="62" rx="19" stroke="url(#nusa-mark-border)" stroke-opacity="0.55" />
    <path
      d="M18 45V19C18 15.7 21.9 14 24.3 16.25L41.8 32.7V19.5C41.8 17 43.8 15 46.3 15C48.8 15 50.8 17 50.8 19.5V45C50.8 48.28 46.94 50 44.53 47.79L27 31.74V45C27 47.49 24.99 49.5 22.5 49.5C20.01 49.5 18 47.49 18 45Z"
      fill="url(#nusa-mark-gradient)"
    />
    <path
      d="M18 45V19C18 15.7 21.9 14 24.3 16.25L41.8 32.7"
      stroke="white"
      stroke-opacity="0.35"
      stroke-width="2"
      stroke-linecap="round"
    />
    <defs>
      <linearGradient id="nusa-mark-gradient" x1="17" y1="15" x2="51" y2="50" gradientUnits="userSpaceOnUse">
        <stop stop-color="#8B5CF6" />
        <stop offset="0.34" stop-color="#38BDF8" />
        <stop offset="0.68" stop-color="#FB7185" />
        <stop offset="1" stop-color="#F59E0B" />
      </linearGradient>
      <linearGradient id="nusa-mark-border" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
        <stop stop-color="#8B5CF6" />
        <stop offset="0.5" stop-color="#38BDF8" />
        <stop offset="1" stop-color="#FB7185" />
      </linearGradient>
    </defs>
  </svg>
</template>
