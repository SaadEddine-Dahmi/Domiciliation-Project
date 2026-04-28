<!-- app/components/ThemeToggle.vue -->
<script setup lang="ts">
const { current, setTheme, toggle } = useTheme()

// Label and icon per theme
const themeConfig = {
  light: { label: 'Clair',  icon: 'sun'  },
  gray:  { label: 'Gris',   icon: 'cloud' },
  dark:  { label: 'Sombre', icon: 'moon'  },
} as const
</script>

<template>
  <div class="flex items-center gap-1 rounded-xl p-1"
       style="background: var(--app-surface-2); border: 1px solid var(--app-border);">

    <!-- Light -->
    <button
      :title="'Mode clair'"
      class="w-7 h-7 rounded-lg flex items-center justify-center transition-all"
      :style="current === 'light'
        ? 'background: var(--app-surface); box-shadow: 0 1px 3px rgba(0,0,0,0.12)'
        : 'background: transparent; opacity: 0.45'"
      @click="setTheme('light')"
    >
      <!-- Sun -->
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round"
           style="color: #e8a020">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
      </svg>
    </button>

    <!-- Gray -->
    <button
      :title="'Mode gris'"
      class="w-7 h-7 rounded-lg flex items-center justify-center transition-all"
      :style="current === 'gray'
        ? 'background: var(--app-surface); box-shadow: 0 1px 3px rgba(0,0,0,0.12)'
        : 'background: transparent; opacity: 0.45'"
      @click="setTheme('gray')"
    >
      <!-- Cloud / contrast -->
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round"
           style="color: #7a8499">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 2a10 10 0 0 1 0 20V2z" fill="currentColor" stroke="none"/>
      </svg>
    </button>

    <!-- Dark -->
    <button
      :title="'Mode sombre'"
      class="w-7 h-7 rounded-lg flex items-center justify-center transition-all"
      :style="current === 'dark'
        ? 'background: var(--app-surface); box-shadow: 0 1px 3px rgba(0,0,0,0.12)'
        : 'background: transparent; opacity: 0.45'"
      @click="setTheme('dark')"
    >
      <!-- Moon -->
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round"
           style="color: #c8a96e">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

  </div>
</template>