<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-5 z-[200] flex flex-col gap-2 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-bold shadow-xl pointer-events-auto"
          :class="toastClass(t.type)"
        >
          {{ t.message }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '../composables/useToast'
const { toasts } = useToast()

function toastClass(type: string) {
  if (type === 'success') return 'bg-green-900/80 text-green-300 border border-green-500/20'
  if (type === 'error') return 'bg-red-900/80 text-red-300 border border-red-500/20'
  return 'bg-blue-900/80 text-blue-300 border border-blue-500/20'
}
</script>