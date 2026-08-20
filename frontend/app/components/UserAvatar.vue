<!-- app/components/UserAvatar.vue -->
<!--
  Single source of truth for "what does the current user's avatar look
  like" — used by the sidebar, topbar, and profile settings page.

  Behavior:
  - Initials render immediately (no blank state while the photo loads).
  - If a photo exists, it crossfades in on top of the initials once
    fully loaded — no pop-in, no flash of a half-loaded image.
  - If the photo URL fails to load, it never shows a broken image icon;
    it simply stays on the initials permanently.
  - Optional colored ring (matching the user's role color) for a more
    polished, "framed" look — can be turned off per instance.
-->
<template>
  <div
    class="relative rounded-full overflow-hidden flex items-center justify-center shrink-0 font-bold select-none"
    :style="wrapperStyle"
  >
    <!-- Initials: always in the DOM, sits underneath the photo, and is
         what remains visible if there's no photo or it fails to load. -->
    <span class="absolute inset-0 flex items-center justify-center">
      {{ initials }}
    </span>

    <!-- Photo: fades in over the initials once it finishes loading.
         :key forces a fresh <img> (and fresh fade-in) whenever the URL
         changes, e.g. right after a new upload. -->
    <img
      v-if="photoUrl"
      :key="photoUrl"
      :src="photoUrl"
      alt=""
      class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300 ease-out"
      :style="{ opacity: loaded ? 1 : 0 }"
      @load="loaded = true"
      @error="onImageError"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'

const props = withDefaults(defineProps<{
  size?: number      // diameter in px
  fontSize?: string  // initials font size, e.g. "11px"
  ring?: boolean     // show a subtle ring in the user's role color
}>(), {
  size: 32,
  fontSize: '11px',
  ring: true,
})

const auth = useAuthStore()

const imageFailed = ref(false)
const loaded       = ref(false)

// Reset both flags whenever the underlying URL changes (fresh upload,
// removal, or a different user's session) so a stale state never
// blocks a URL that would otherwise load fine now.
watch(() => auth.user?.photoUrl, () => {
  imageFailed.value = false
  loaded.value       = false
})

const photoUrl = computed(() => (imageFailed.value ? null : auth.user?.photoUrl ?? null))
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