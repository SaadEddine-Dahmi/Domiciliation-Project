<script setup lang="ts">
const props = withDefaults(defineProps<{
  src?: string | null
  initials?: string | null
  label?: string
  size?: number
  rounded?: 'full' | 'xl'
  color?: string
}>(), {
  src: null,
  initials: '?',
  label: 'Photo de profil',
  size: 44,
  rounded: 'full',
  color: '#c8a96e',
})

const failed = ref(false)
const open = ref(false)
const hasImage = computed(() => Boolean(props.src && !failed.value))
const radiusClass = computed(() => props.rounded === 'full' ? 'rounded-full' : 'rounded-xl')
const dimensionStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  background: hasImage.value ? 'transparent' : `${props.color}22`,
  color: props.color,
}))

watch(() => props.src, () => {
  failed.value = false
})
</script>

<template>
  <button
    type="button"
    class="flex shrink-0 items-center justify-center overflow-hidden font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
    :class="[radiusClass, hasImage ? 'cursor-zoom-in hover:opacity-90' : 'cursor-default']"
    :style="dimensionStyle"
    :disabled="!hasImage"
    :aria-label="label"
    @click="open = true"
  >
    <img
      v-if="hasImage"
      :src="src || undefined"
      alt=""
      class="h-full w-full object-cover"
      @error="failed = true"
    />
    <span v-else>{{ initials || '?' }}</span>
  </button>

  <Teleport to="body">
    <div
      v-if="open && src"
      class="fixed inset-0 z-[460] flex items-center justify-center bg-black/85 p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="label"
      @click.self="open = false"
    >
      <button
        type="button"
        class="absolute right-5 top-5 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
        aria-label="Fermer"
        @click="open = false"
      >
        <UiIcon name="x-circle" :size="18" />
      </button>
      <img :src="src" alt="" class="max-h-[88vh] max-w-[92vw] rounded-xl object-contain shadow-2xl" />
    </div>
  </Teleport>
</template>
