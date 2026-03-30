<template>
  <div class="animate-fade-up max-w-2xl">
    <h2 class="font-serif text-[22px] mb-5"><em class="text-gold italic">Notifications</em></h2>

    <div class="rounded-2xl overflow-hidden" style="background:#13161f;border:1px solid rgba(255,255,255,0.06)">
      <div v-for="(n, i) in notifs.items" :key="n.id"
           class="px-5 py-4"
           :style="i < notifs.items.length - 1 ? 'border-bottom:1px solid rgba(255,255,255,0.04)' : ''">
        <p class="text-[13px]">{{ n.text }}</p>
        <p class="text-[11px] text-app-text/40 mt-1">{{ n.time }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useNotificationsStore } from '~/stores/notifs'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })
const notifs = useNotificationsStore()
</script>