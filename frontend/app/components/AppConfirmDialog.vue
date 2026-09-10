<script setup lang="ts">
const { state, close } = useConfirm()

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && state.value.open) close(false)
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="state.open"
      class="fixed inset-0 z-[500] flex items-center justify-center bg-black/65 p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="state.title"
      @click.self="close(false)"
    >
      <div
        class="w-full max-w-sm rounded-2xl p-5 shadow-2xl"
        style="background: var(--app-surface); color: var(--app-text); border: 1px solid var(--app-border);"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
            :style="state.variant === 'danger'
              ? 'background: rgba(239,68,68,0.12); color: #ef4444;'
              : 'background: rgba(200,169,110,0.14); color: #c8a96e;'"
          >
            <UiIcon :name="state.variant === 'danger' ? 'x-circle' : 'bell'" :size="20" />
          </div>

          <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold leading-tight">
              {{ state.title }}
            </h2>
            <p class="mt-2 text-sm leading-6" style="color: var(--app-text-muted);">
              {{ state.message }}
            </p>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button
            v-if="state.cancelLabel"
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-semibold transition hover:opacity-80"
            style="background: transparent; border: 1px solid var(--app-border); color: var(--app-text);"
            @click="close(false)"
          >
            {{ state.cancelLabel }}
          </button>
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
            :style="state.variant === 'danger' ? 'background: #ef4444;' : 'background: #c8a96e;'"
            @click="close(true)"
          >
            {{ state.confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
