<!-- app/components/AppTopbar.vue -->
<template>
  <header
    class="sticky top-0 z-20 h-14 flex items-center gap-3 px-3 sm:px-6 transition-colors"
    style="background: var(--topbar-bg); backdrop-filter: blur(12px);
           border-bottom: 1px solid var(--app-border-2);"
  >
    <!-- Hamburger — mobile only -->
    <button
      class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center shrink-0 nav-inactive transition-colors"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
      @click="toggleMobile"
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M3 6h18M3 12h18M3 18h18"/>
      </svg>
    </button>

    <h1 class="font-serif text-[16px] sm:text-[17px] font-normal flex-1 truncate"
        style="color: var(--app-text)">
      {{ title }}
    </h1>

    <div class="flex items-center gap-1.5 sm:gap-2">
      <ThemeToggle />

      <NuxtLink
        :to="auth.isInternal ? '/admin/notifs' : '/client/notifs'"
        class="w-9 h-9 rounded-xl flex items-center justify-center transition-colors nav-inactive"
        style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </NuxtLink>

      <NuxtLink :to="auth.isInternal ? '/admin/settings' : '/client/settings'">
        <div
          class="w-9 h-9 rounded-xl flex items-center justify-center text-[12px] font-bold"
          :style="`background:${auth.user?.color}22;color:${auth.user?.color}`"
        >{{ auth.user?.avatar }}</div>
      </NuxtLink>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
defineProps<{ title: string }>()
const auth = useAuthStore()
const { toggleMobile } = useSidebar()
</script>