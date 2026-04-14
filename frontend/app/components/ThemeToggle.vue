<!-- app/components/ThemeToggle.vue -->
<!-- Sun/moon toggle button — used in AppTopbar -->
<script setup lang="ts">
const { isDark, toggle } = useTheme()
</script>

<template>
  <button
    class="w-9 h-9 rounded-xl flex items-center justify-center transition-all"
    :title="isDark ? 'Passer en mode clair' : 'Passer en mode sombre'"
    style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
    @click="toggle"
  >
    <!-- Sun icon — shown in dark mode (click to go light) -->
    <svg
      v-if="isDark"
      xmlns="http://www.w3.org/2000/svg"
      width="16" height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      style="color: #c8a96e"
    >
      <circle cx="12" cy="12" r="4"/>
      <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
    </svg>

    <!-- Moon icon — shown in light mode (click to go dark) -->
    <svg
      v-else
      xmlns="http://www.w3.org/2000/svg"
      width="16" height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      style="color: var(--app-text-muted)"
    >
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
  </button>
</template>