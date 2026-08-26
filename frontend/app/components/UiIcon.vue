<script setup lang="ts">
const props = withDefaults(defineProps<{
  name: string
  size?: number | string
  strokeWidth?: number | string
}>(), {
  size: 18,
  strokeWidth: 1.9,
})

const px = computed(() => typeof props.size === 'number' ? `${props.size}px` : props.size)
</script>

<template>
  <svg
    :width="px"
    :height="px"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    class="ui-icon"
  >
    <template v-if="name === 'contract'">
      <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z" />
      <path d="M14 2v5h5" />
      <path d="M9 14l2 2 4-5" />
    </template>
    <template v-else-if="name === 'building'">
      <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />
      <path d="M18 8h1a1 1 0 0 1 1 1v12" />
      <path d="M8 7h4M8 11h4M8 15h4" />
      <path d="M3 21h18" />
    </template>
    <template v-else-if="name === 'folder'">
      <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
    </template>
    <template v-else-if="name === 'card'">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M3 10h18" />
    </template>
    <template v-else-if="name === 'lock'">
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V8a4 4 0 0 1 8 0v3" />
    </template>
    <template v-else-if="name === 'bell'">
      <path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8" />
      <path d="M13.7 21a2 2 0 0 1-3.4 0" />
    </template>
    <template v-else-if="name === 'mail'">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m3 7 9 6 9-6" />
    </template>
    <template v-else-if="name === 'calendar'">
      <rect x="3" y="4" width="18" height="17" rx="2" />
      <path d="M8 2v4M16 2v4M3 10h18" />
    </template>
    <template v-else-if="name === 'x-circle'">
      <circle cx="12" cy="12" r="9" />
      <path d="m15 9-6 6M9 9l6 6" />
    </template>
    <template v-else-if="name === 'eye'">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
      <circle cx="12" cy="12" r="3" />
    </template>
    <template v-else-if="name === 'download'">
      <path d="M12 3v12" />
      <path d="m7 10 5 5 5-5" />
      <path d="M5 21h14" />
    </template>
    <template v-else-if="name === 'paperclip'">
      <path d="m21.4 11.6-8.5 8.5a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5" />
    </template>
    <template v-else-if="name === 'check'">
      <path d="M20 6 9 17l-5-5" />
    </template>
    <template v-else-if="name === 'refresh'">
      <path d="M21 12a9 9 0 0 1-15.5 6.2L3 16" />
      <path d="M3 21v-5h5" />
      <path d="M3 12a9 9 0 0 1 15.5-6.2L21 8" />
      <path d="M21 3v5h-5" />
    </template>
    <template v-else-if="name === 'user'">
      <path d="M20 21a8 8 0 0 0-16 0" />
      <circle cx="12" cy="7" r="4" />
    </template>
    <template v-else-if="name === 'users'">
      <path d="M16 21a6 6 0 0 0-12 0" />
      <circle cx="10" cy="7" r="4" />
      <path d="M22 21a5 5 0 0 0-4-4.9" />
      <path d="M17 3.4a4 4 0 0 1 0 7.2" />
    </template>
    <template v-else-if="name === 'scan'">
      <path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2" />
      <path d="M8 12h8" />
    </template>
    <template v-else-if="name === 'clipboard'">
      <rect x="8" y="3" width="8" height="4" rx="1" />
      <path d="M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3" />
    </template>
    <template v-else-if="name === 'image'">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <circle cx="8.5" cy="10.5" r="1.5" />
      <path d="m21 15-4-4L5 19" />
    </template>
    <template v-else>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v4l3 2" />
    </template>
  </svg>
</template>

<style scoped>
.ui-icon {
  display: inline-block;
  flex: 0 0 auto;
}
</style>
