<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center p-5"
           style="background:rgba(0,0,0,0.75);backdrop-filter:blur(4px)" @click.self="close">
        <div class="w-full max-w-lg rounded-3xl overflow-hidden"
             style="background:#13161f;border:1px solid rgba(255,255,255,0.08)">
          <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid rgba(255,255,255,0.06)">
            <h3 class="font-serif text-[17px]">{{ title }}</h3>
            <button class="w-8 h-8 rounded-xl" style="background:rgba(255,255,255,0.06)" @click="close">✕</button>
          </div>
          <div class="p-5"><slot /></div>
          <div v-if="$slots.footer" class="px-5 pb-5 flex justify-end gap-2"><slot name="footer" /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{ open: boolean; title: string }>()
const emit = defineEmits<{ (e:'close'): void }>()
function close() { emit('close') }
</script>