<!-- ============================================================
  pages/client/messages.vue
  Messages reçus du domiciliataire
  - Lecture du message → marque automatiquement comme lu
  - Read receipt envoyé au domiciliataire
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

const messages      = ref<any[]>([])
const loading       = ref(true)
const openMessage   = ref<any | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/messages`,
      { headers: authHeaders() }
    )
    messages.value = res.data ?? []
  } catch {} finally {
    loading.value = false
  }
}

/** Ouvre un message + marque comme lu immédiatement */
async function openMsg(msg: any): Promise<void> {
  openMessage.value = msg
  if (!msg.is_read) {
    try {
      await $fetch(`${getApiBase()}/api/messages/${msg.id}/read`, {
        method: 'POST', headers: authHeaders()
      })
      msg.is_read = true
      msg.read_at = new Date().toISOString()
    } catch {}
  }
}

const unreadCount = computed(() => messages.value.filter(m => !m.is_read).length)

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

    <div
      v-else-if="messages.length"
      class="rounded-2xl overflow-hidden"
      style="background:#13161f;border:1px solid rgba(255,255,255,0.06)"
    >
      <div
        v-for="(msg, i) in messages" :key="msg.id"
        class="px-5 py-4 cursor-pointer hover:bg-white/3 transition flex items-start gap-3"
        :class="[
          i < messages.length - 1 ? 'border-b border-white/5' : '',
          !msg.is_read ? 'bg-gold/5' : ''
        ]"
        @click="openMsg(msg)"
      >
        <!-- Point non lu -->
        <div
          class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
          :class="!msg.is_read ? 'bg-gold' : 'bg-transparent'"
        />

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="text-sm font-semibold" :class="!msg.is_read ? '' : 'text-app-text/70'">
              {{ msg.subject || 'Message de votre domiciliataire' }}
            </p>
          </div>
          <p class="text-xs text-app-text/50 mt-0.5 line-clamp-1">{{ msg.message }}</p>
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
      <div class="card w-full max-w-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-lg">
            {{ openMessage.subject || 'Message' }}
          </h2>
          <button class="text-app-text/40 hover:text-white" @click="openMessage = null">✕</button>
        </div>

        <div class="text-xs text-app-text/40">
          Reçu le {{ formatDate(openMessage.created_at) }}
        </div>

        <div
          class="rounded-xl border border-white/10 p-4 text-sm leading-relaxed whitespace-pre-wrap bg-white/3"
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
