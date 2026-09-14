<script setup lang="ts">
const isOpen = ref(false)
const title = ref('Apercu du contrat')
const iframeSrcDoc = ref<string | null>(null)
const iframeUrl = ref<string | null>(null)

function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch {
    return ''
  }
}

function withToken(url: string): string {
  const token = getRawToken()
  if (!token || /[?&]token=/.test(url)) return url
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

function openHtml(html: string, customTitle?: string) {
  iframeUrl.value = null
  iframeSrcDoc.value = html
  title.value = customTitle || 'Apercu du contrat'
  isOpen.value = true
}

function openUrl(url: string, customTitle?: string) {
  iframeSrcDoc.value = null
  iframeUrl.value = withToken(url)
  title.value = customTitle || 'Apercu du contrat'
  isOpen.value = true
}

function close() {
  isOpen.value = false
  iframeSrcDoc.value = null
  iframeUrl.value = null
}

defineExpose({
  openHtml,
  openUrl,
  close,
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-[300] flex flex-col p-4 md:p-8"
      style="background: rgba(0, 0, 0, 0.9)"
    >
      <div class="flex justify-between items-center mb-4 text-white">
        <h3 class="text-lg font-serif">{{ title }}</h3>
        <button
          @click="close"
          class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors"
        >
          x
        </button>
      </div>
      <div class="flex-1 bg-white rounded-xl overflow-hidden relative shadow-2xl">
        <iframe
          v-if="iframeSrcDoc"
          :srcdoc="iframeSrcDoc"
          class="w-full h-full border-0"
        />
        <iframe
          v-else-if="iframeUrl"
          :src="iframeUrl"
          class="w-full h-full border-0"
        />
      </div>
    </div>
  </Teleport>
</template>
