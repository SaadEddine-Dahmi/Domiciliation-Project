<!-- ============================================================
  pages/admin/notifications.vue
  Notifications + préférences d'alerte
  FIXES:
  - Préférences sauvegardées en localStorage ET en DB
  - Au chargement, restaure depuis DB en priorité
  - Les délais sélectionnés persistent au refresh
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()

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

// ── State ─────────────────────────────────────────────────
const notifications  = ref<any[]>([])
const loading        = ref(true)
const savingPrefs    = ref(false)
// Initialisé depuis localStorage pour éviter le flash au chargement
const selectedDelays = ref<number[]>(loadDelaysFromStorage())

// ── Persistance locale des délais ─────────────────────────
function loadDelaysFromStorage(): number[] {
  if (!import.meta.client) return [1]
  try {
    const raw = localStorage.getItem('astfisc_notif_delays')
    if (raw) return JSON.parse(raw)
  } catch {}
  return [1]
}

function saveDelaysToStorage(delays: number[]): void {
  if (!import.meta.client) return
  localStorage.setItem('astfisc_notif_delays', JSON.stringify(delays))
}

// ── Chargement depuis l'API ───────────────────────────────
async function loadAll(): Promise<void> {
  loading.value = true
  try {
    const [notifsRes, prefsRes] = await Promise.all([
      $fetch<{ success: boolean; data: any[] }>(
        `${getApiBase()}/api/notifications`,
        { headers: authHeaders() }
      ),
      $fetch<{ success: boolean; data: { delays: number[] } }>(
        `${getApiBase()}/api/notifications/preferences`,
        { headers: authHeaders() }
      ).catch(() => ({ success: true, data: { delays: loadDelaysFromStorage() } })),
    ])
    notifications.value  = notifsRes.data ?? []
    // DB est source de vérité — override localStorage
    const dbDelays = prefsRes.data?.delays ?? []
    if (dbDelays.length > 0) {
      selectedDelays.value = dbDelays
      saveDelaysToStorage(dbDelays)
    }
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

// ── Toggle délai ──────────────────────────────────────────
function toggleDelay(months: number): void {
  if (selectedDelays.value.includes(months)) {
    if (selectedDelays.value.length === 1) return  // garder au moins 1
    selectedDelays.value = selectedDelays.value.filter(d => d !== months)
  } else {
    selectedDelays.value = [...selectedDelays.value, months].sort((a, b) => a - b)
  }
  // Sauvegarde locale immédiate (avant même le POST)
  saveDelaysToStorage(selectedDelays.value)
}

// ── Sauvegarder les préférences ───────────────────────────
async function savePreferences(): Promise<void> {
  savingPrefs.value = true
  try {
    await $fetch(`${getApiBase()}/api/notifications/preferences`, {
      method:  'PUT',
      headers: authHeaders(),
      body:    { delays: selectedDelays.value },
    })
    saveDelaysToStorage(selectedDelays.value)
    success(`Préférences sauvegardées : alertes ${selectedDelays.value.sort((a,b)=>a-b).join(', ')} mois avant expiration ✓`)
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur sauvegarde')
  } finally {
    savingPrefs.value = false
  }
}

// ── Marquer comme lu ──────────────────────────────────────
async function markRead(id: number): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/${id}/read`, {
      method: 'POST', headers: authHeaders()
    })
    const n = notifications.value.find(x => x.id === id)
    if (n) n.is_read = true
  } catch {}
}

async function markAllRead(): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/read-all`, {
      method: 'POST', headers: authHeaders()
    })
    notifications.value.forEach(n => { n.is_read = true })
    success('Toutes les notifications marquées comme lues')
  } catch {}
}

// ── Helpers ───────────────────────────────────────────────
const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl"><em class="text-gold italic">Notifications</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ unreadCount }} non lue(s)</p>
      </div>
      <button v-if="unreadCount > 0" class="btn btn-outline btn-sm" @click="markAllRead">
        ✓ Tout marquer comme lu
      </button>
    </div>

    <!-- Préférences alertes -->
    <div class="card p-5 space-y-4">
      <div>
        <p class="text-xs uppercase text-gold tracking-widest font-bold">
          🔔 Alertes d'expiration de contrat
        </p>
        <p class="text-xs text-app-text/50 mt-1">
          Recevez un rappel avant la date d'expiration. Sélectionnez un ou plusieurs délais.
        </p>
      </div>

      <!-- Boutons délai -->
      <div class="flex gap-3 flex-wrap">
        <button
          v-for="m in [1, 3, 6]" :key="m"
          class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all"
          :class="selectedDelays.includes(m)
            ? 'border-gold bg-gold/20 text-gold shadow-[0_0_12px_rgba(200,169,110,0.3)]'
            : 'border-white/10 text-app-text/40 hover:border-white/30 hover:text-app-text/70'"
          @click="toggleDelay(m)"
        >
          {{ m }} mois
          <span v-if="selectedDelays.includes(m)" class="ml-1">✓</span>
        </button>
      </div>

      <!-- Résumé sélection -->
      <p class="text-xs text-app-text/50">
        Alertes actives :
        <span class="text-gold font-semibold">
          {{ selectedDelays.length > 0
              ? selectedDelays.sort((a,b)=>a-b).map(d => `${d} mois`).join(', ')
              : 'aucune' }}
        </span>
        avant expiration
      </p>

      <button
        class="btn btn-gold btn-md"
        :disabled="savingPrefs"
        @click="savePreferences"
      >
        {{ savingPrefs ? '⏳ Sauvegarde...' : '💾 Enregistrer les préférences' }}
      </button>
    </div>

    <!-- Liste notifications -->
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
          <p class="text-sm" :class="!n.is_read ? 'font-semibold' : 'text-app-text/60'">
            {{ n.message }}
          </p>
          <p class="text-xs text-app-text/40 mt-1">{{ formatDate(n.created_at) }}</p>
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