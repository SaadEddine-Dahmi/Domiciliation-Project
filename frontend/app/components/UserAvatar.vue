<template>
  <div
    class="relative rounded-full overflow-hidden flex items-center justify-center shrink-0 font-bold select-none"
    :style="wrapperStyle"
  >
    <span class="absolute inset-0 flex items-center justify-center">
      {{ initials }}
    </span>

    <img
      v-if="photoUrl"
      :key="photoUrl"
      :src="photoUrl"
      alt=""
      class="absolute inset-0 w-full h-full object-cover avatar-fade-in"
      @error="onImageError"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'

const props = withDefaults(defineProps<{
  size?: number
  fontSize?: string
  ring?: boolean
}>(), {
  size: 32,
  fontSize: '11px',
  ring: true,
})

const auth = useAuthStore()

const imageFailed = ref(false)

// Support both camelCase (JS) and snake_case (Laravel JSON API) property names
const rawPhotoUrl = computed(() => auth.user?.photoUrl ?? auth.user?.photo_url ?? null)

// Reset error state whenever the underlying photo URL changes or photoVersion increments
watch([rawPhotoUrl, () => auth.photoVersion], () => {
  imageFailed.value = false
})

const photoUrl = computed(() => (imageFailed.value ? null : rawPhotoUrl.value))
const initials = computed(() => auth.user?.avatar ?? '?')

const wrapperStyle = computed(() => {
  const color = auth.user?.color ?? '#c8a96e'
  return {
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: props.fontSize,
    background: `${color}22`,
    color,
    boxShadow: props.ring ? `0 0 0 2px ${color}55` : 'none',
  }
})

function onImageError(): void {
  imageFailed.value = true
}
</script>

<style scoped>
.avatar-fade-in {
  animation: avatar-fade-in 0.3s ease-out;
}
@keyframes avatar-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
</style>