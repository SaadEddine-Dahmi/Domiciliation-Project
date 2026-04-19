<template>
  <header
    class="sticky top-0 z-20 h-14 flex items-center gap-4 px-6 transition-colors"
    style="background: var(--topbar-bg); backdrop-filter: blur(12px); border-bottom: 1px solid var(--app-border-2);"
  >
    <h1 class="font-serif text-[17px] font-normal flex-1 min-w-0 truncate" style="color: var(--app-text)">
      {{ title }}
    </h1>

    <div class="ml-auto flex items-center gap-2 shrink-0">
      <ThemeToggle />

      <NuxtLink
        :to="auth.isInternal ? '/admin/notifs' : '/client/notifs'"
        class="relative w-9 h-9 rounded-xl flex items-center justify-center transition-all"
        style="background: var(--app-surface-2); border: 1px solid var(--app-border);"
      >🔔</NuxtLink>

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
</script>