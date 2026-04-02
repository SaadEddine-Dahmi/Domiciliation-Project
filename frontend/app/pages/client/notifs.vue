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

const notifications = ref<any[]>([])
const loading       = ref(true)

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/notifications`,
      { headers: authHeaders() }
    )
    notifications.value = res.data ?? []
  } catch {} finally {
    loading.value = false
  }
}

async function markRead(id: number): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/${id}/read`, {
      method: 'POST', headers: authHeaders()
    })
    const n = notifications.value.find(x => x.id === id)
    if (n) n.is_read = true
  } catch {}
}

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="animate-fade-up max-w-2xl space-y-4">

    <div class="flex items-center justify-between flex-wrap gap-2">
      <h1 class="font-serif text-[22px]"><em class="text-gold italic">Notifications</em></h1>
      <span v-if="unreadCount > 0" class="text-xs px-2 py-0.5 rounded-full bg-gold/20 text-gold font-bold">
        {{ unreadCount }} non lue(s)
      </span>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-48 bg-white/10 rounded mb-2" />
        <div class="h-3 w-24 bg-white/10 rounded" />
      </div>
    </div>

    <div
      v-else-if="notifications.length"
      class="rounded-2xl overflow-hidden"
      style="background:#13161f;border:1px solid rgba(255,255,255,0.06)"
    >
      <div
        v-for="(n, i) in notifications" :key="n.id"
        class="px-5 py-4 flex items-start justify-between gap-3"
        :class="[
          i < notifications.length - 1 ? 'border-b border-white/5' : '',
          !n.is_read ? 'bg-gold/5' : ''
        ]"
      >
        <div class="min-w-0 flex-1">
          <p class="text-[13px]" :class="!n.is_read ? 'font-semibold' : 'text-app-text/70'">
            {{ n.message }}
          </p>
          <p class="text-[11px] text-app-text/40 mt-1">{{ formatDate(n.created_at) }}</p>
        </div>
        <button
          v-if="!n.is_read"
          class="text-xs text-gold flex-shrink-0 hover:underline"
          @click="markRead(n.id)"
        >Lu</button>
        <span v-else class="text-xs text-app-text/20 flex-shrink-0">✓</span>
      </div>
    </div>

    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">🔔</p>
      <p>Aucune notification pour le moment.</p>
    </div>

  </div>
</template>