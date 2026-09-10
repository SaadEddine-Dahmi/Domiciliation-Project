<script setup lang="ts">
const { state, close } = useDocumentViewer()

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && state.value.open) close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="state.open"
      class="fixed inset-0 z-[450] flex flex-col bg-black/90"
      role="dialog"
      aria-modal="true"
      :aria-label="state.title"
    >
      <div class="flex items-center justify-between gap-3 px-4 py-3 text-white" style="background: rgba(0,0,0,0.35); border-bottom: 1px solid rgba(255,255,255,0.12);">
        <p class="min-w-0 truncate text-sm font-semibold">{{ state.title }}</p>
        <button
          type="button"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20"
          aria-label="Fermer"
          @click="close"
        >
          <UiIcon name="x-circle" :size="18" />
        </button>
      </div>

      <div class="relative flex min-h-0 flex-1 items-center justify-center p-3 sm:p-6">
        <div v-if="state.loading" class="text-center text-white">
          <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-white" />
          <p class="text-sm">Chargement du document...</p>
        </div>

        <iframe
          v-else-if="state.url && state.isPdf"
          :src="state.url"
          class="h-full w-full rounded-xl bg-white shadow-2xl"
          style="border: none;"
        />

        <img
          v-else-if="state.url && state.isImage"
          :src="state.url"
          alt=""
          class="max-h-full max-w-full rounded-xl object-contain shadow-2xl"
        />

        <div
          v-else
          class="max-w-sm rounded-2xl p-5 text-center text-sm"
          style="background: var(--app-surface); color: var(--app-text-muted);"
        >
          Apercu indisponible pour ce format. Utilisez le bouton de telechargement.
        </div>
      </div>
    </div>
  </Teleport>
</template>
