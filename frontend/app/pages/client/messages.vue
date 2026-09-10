<!-- ============================================================
  pages/client/messages.vue
  Messages reçus du domiciliataire
  - Lecture du message → marque automatiquement comme lu
  - Read receipt envoyé au domiciliataire
============================================================ -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { useMessagesStore } from '~/stores/messages'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })
const { error: toastError } = useToast()
const auth = useAuthStore()
const messagesStore = useMessagesStore()

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

const messages      = ref<any[]>([])
const loading       = ref(true)
const loadError     = ref('')
const openMessage   = ref<any | null>(null)

async function load(): Promise<void> {
  loading.value = true
  loadError.value = ''
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/messages`,
      { headers: authHeaders(), query: { per_page: 100 } }
    )
    messages.value = res.data ?? []
    await markAllReceivedRead()
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement des messages'
    toastError?.(loadError.value)
  } finally {
    loading.value = false
  }
}

/** Ouvre un message + marque comme lu immédiatement */
async function openMsg(msg: any): Promise<void> {
  openMessage.value = msg
  const isReceived = (msg.receiver_id ?? msg.user_id) === auth.user?.id
  if (isReceived && !msg.is_read) {
    try {
      await $fetch(`${getApiBase()}/api/messages/${msg.id}/read`, {
        method: 'POST', headers: authHeaders()
      })
      msg.is_read = true
      msg.read_at = new Date().toISOString()
      messagesStore.markOneRead()
    } catch {}
  }
}

async function markAllReceivedRead(): Promise<void> {
  if (!auth.isClient) return

  const unread = messages.value.filter(m => (m.receiver_id ?? m.user_id) === auth.user?.id && !m.is_read)
  if (!unread.length) {
    messagesStore.markAllReadLocally()
    return
  }

  try {
    await $fetch(`${getApiBase()}/api/messages/read-all`, {
      method: 'POST',
      headers: authHeaders(),
    })
    const readAt = new Date().toISOString()
    unread.forEach((msg) => {
      msg.is_read = true
      msg.read_at = readAt
    })
    messagesStore.markAllReadLocally()
  } catch {
    await messagesStore.refreshUnreadCount()
  }
}

const unreadCount = computed(() => messages.value.filter(m => (m.receiver_id ?? m.user_id) === auth.user?.id && !m.is_read).length)

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleString('fr-FR')
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-2xl">

    <div>
      <h1 class="font-serif text-2xl">Mes <em class="text-gold italic">messages</em></h1>
      <p class="text-app-text/50 text-sm mt-1">
        {{ unreadCount > 0 ? `${unreadCount} message(s) non lu(s)` : 'Tous les messages lus' }}
      </p>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-48 bg-white/10 rounded mb-2" />
        <div class="h-3 w-24 bg-white/10 rounded" />
      </div>
    </div>

    <div v-else-if="loadError" class="card p-4 text-red-400 text-sm">
      {{ loadError }}
    </div>

    <div
      v-else-if="messages.length"
      class="rounded-2xl overflow-hidden"
      style="background: var(--app-surface); border: 1px solid var(--app-border-2)"
    >
      <div
        v-for="(msg, i) in messages" :key="msg.id"
        class="px-5 py-4 cursor-pointer transition flex items-start gap-3 max-w-full"
        :class="[i < messages.length - 1 ? 'border-b' : '']"
        :style="[
          i < messages.length - 1 ? 'border-color: var(--app-border-2)' : '',
          !msg.is_read ? 'background: rgba(200,169,110,0.05)' : '',
        ]"
        @click="openMsg(msg)"
      >
        <!-- Point non lu -->
        <div
          class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
          :class="!msg.is_read ? 'bg-gold' : 'bg-transparent'"
        />

        <div class="min-w-0 max-w-full flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="text-sm font-semibold max-w-full break-words [overflow-wrap:anywhere]" :class="!msg.is_read ? '' : 'text-app-text/70'">
              {{ msg.subject || 'Message de votre domiciliataire' }}
            </p>
          </div>
          <p class="text-xs text-app-text/50 mt-0.5 line-clamp-1 max-w-full break-words [overflow-wrap:anywhere]">{{ msg.message }}</p>
          <p class="text-xs text-app-text/30 mt-1">{{ formatDate(msg.created_at) }}</p>
        </div>
      </div>
    </div>

    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">✉</p>
      <p>Aucun message reçu.</p>
    </div>

    <!-- Modal lecture message -->
    <div
      v-if="openMessage"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="openMessage = null"
    >
      <div class="card w-full max-w-lg p-6 space-y-4 overflow-hidden">
        <div class="flex items-center justify-between gap-3">
          <h2 class="font-serif text-lg min-w-0 max-w-full break-words [overflow-wrap:anywhere]">
            {{ openMessage.subject || 'Message' }}
          </h2>
          <button class="text-app-text/40 hover:text-white" @click="openMessage = null">✕</button>
        </div>

        <div class="text-xs text-app-text/40">
          Reçu le {{ formatDate(openMessage.created_at) }}
        </div>

        <div
          class="rounded-xl p-4 text-sm leading-relaxed whitespace-pre-wrap max-w-full break-words [overflow-wrap:anywhere]"
          style="border: 1px solid var(--app-border); background: var(--app-surface-2)"
        >
          {{ openMessage.message }}
        </div>

        <button class="btn btn-outline btn-md w-full" @click="openMessage = null">
          Fermer
        </button>
      </div>
    </div>

  </div>
</template>
