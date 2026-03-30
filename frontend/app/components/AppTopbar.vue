<template>
  <header class="sticky top-0 z-20 h-14 flex items-center gap-4 px-6"
          style="background:rgba(11,13,19,0.88);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.06)">
    <h1 class="font-serif text-[17px] font-normal">{{ title }}</h1>
    <div class="ml-auto flex items-center gap-2">
      <NuxtLink :to="auth.isAdmin ? '/admin/notifs' : '/client/notifs'"
                class="relative w-9 h-9 rounded-xl flex items-center justify-center"
                style="background:#1a1e2a;border:1px solid rgba(255,255,255,0.07)">
        🔔
      </NuxtLink>
      <div class="w-9 h-9 rounded-xl flex items-center justify-center text-[12px] font-bold"
           :style="`background:${auth.user?.color}22;color:${auth.user?.color}`">
        {{ auth.user?.avatar }}
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
defineProps<{ title: string }>()
const auth = useAuthStore()
</script>