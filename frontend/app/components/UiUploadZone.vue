<template>
  <div
    class="rounded-2xl border border-dashed p-6 text-center cursor-pointer"
    style="border-color:rgba(200,169,110,0.35);background:rgba(200,169,110,0.04)"
    @dragover.prevent
    @drop.prevent="onDrop"
    @click="pick"
  >
    <input ref="inputRef" type="file" class="hidden" :accept="accept" multiple @change="onChange" />
    <p class="font-serif text-lg">{{ title }}</p>
    <p class="text-sm text-app-text/50 mt-1">{{ subtitle }}</p>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  title?: string
  subtitle?: string
  accept?: string
}>(), {
  title: 'Déposez vos fichiers',
  subtitle: 'PDF, JPG, PNG',
  accept: '.pdf,image/*',
})

const emit = defineEmits<{ (e: 'files', files: FileList): void }>()
const inputRef = ref<HTMLInputElement | null>(null)

function pick() {
  inputRef.value?.click()
}
function onChange(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files && files.length) emit('files', files)
}
function onDrop(e: DragEvent) {
  const files = e.dataTransfer?.files
  if (files && files.length) emit('files', files)
}
</script>