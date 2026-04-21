<!-- app/pages/admin/notifs.vue -->
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

const notifications  = ref<any[]>([])
const loading        = ref(true)
const savingPrefs    = ref(false)
const selectedDelays = ref<number[]>([1])

// ── Expanded notification detail ──────────────────────────
const expandedId = ref<number | null>(null)

function toggleExpand(id: number): void {
  expandedId.value = expandedId.value === id ? null : id
}

// ── localStorage helpers ──────────────────────────────────
function readStorage(): number[] {
  try {
    const raw = localStorage.getItem('astfisc_notif_delays')
    if (raw) {
      const p = JSON.parse(raw)
      if (Array.isArray(p) && p.length) return p
    }
  } catch {}
  return [1]
}

function writeStorage(d: number[]): void {
  localStorage.setItem('astfisc_notif_delays', JSON.stringify(d))
}

async function loadAll(): Promise<void> {
  selectedDelays.value = readStorage()
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
      ).catch(() => ({ success: true, data: { delays: readStorage() } })),
    ])
    notifications.value = notifsRes.data ?? []
    const db = prefsRes.data?.delays ?? []
    if (Array.isArray(db) && db.length) {
      selectedDelays.value = db
      writeStorage(db)
    }
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

function toggleDelay(m: number): void {
  const cur = [...selectedDelays.value]
  if (cur.includes(m)) {
    if (cur.length === 1) return
    selectedDelays.value = cur.filter(d => d !== m)
  } else {
    selectedDelays.value = [...cur, m].sort((a, b) => a - b)
  }
  writeStorage(selectedDelays.value)
}

async function savePreferences(): Promise<void> {
  savingPrefs.value = true
  try {
    await $fetch(`${getApiBase()}/api/notifications/preferences`, {
      method: 'PUT', headers: authHeaders(),
      body: { delays: selectedDelays.value },
    })
    writeStorage(selectedDelays.value)
    success(`✓ Alertes : ${selectedDelays.value.sort((a,b)=>a-b).map(d=>`${d} mois`).join(', ')} avant expiration`)
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur sauvegarde')
  } finally {
    savingPrefs.value = false
  }
}

async function markRead(id: number): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/${id}/read`, { method: 'POST', headers: authHeaders() })
    const n = notifications.value.find(x => x.id === id)
    if (n) n.is_read = true
  } catch {}
}

async function markAllRead(): Promise<void> {
  try {
    await $fetch(`${getApiBase()}/api/notifications/read-all`, { method: 'POST', headers: authHeaders() })
    notifications.value.forEach(n => { n.is_read = true })
    success('Toutes les notifications lues')
  } catch {}
}

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

// Icon color based on notification type keyword
function notifColor(n: any): string {
  const msg = (n.message ?? '').toLowerCase()
  if (msg.includes('paiement'))  return '#22c55e'
  if (msg.includes('expir'))     return '#f59e0b'
  if (msg.includes('contrat'))   return '#c8a96e'
  if (msg.includes('document'))  return '#60a5fa'
  return '#94a3b8'
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl"><em class="text-gold italic">Notifications</em></h1>
        <p class="text-sm mt-1" style="color: var(--app-text-muted)">{{ unreadCount }} non lue(s)</p>
      </div>
      <button v-if="unreadCount > 0" class="btn btn-outline btn-sm" @click="markAllRead">
        ✓ Tout marquer comme lu
      </button>
    </div>

    <!-- Préférences alertes -->
    <div class="card p-5 space-y-4">
      <div>
        <p class="text-xs uppercase tracking-widest font-bold" style="color: #c8a96e">
          Alertes d'expiration
        </p>
        <p class="text-xs mt-1" style="color: var(--app-text-muted)">
          Rappel avant la date d'expiration. Plusieurs délais possibles.
        </p>
      </div>
      <div class="flex gap-3 flex-wrap">
        <button
          v-for="m in [1, 3, 6]" :key="m"
          class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all duration-150"
          :style="selectedDelays.includes(m)
            ? 'border-color:#c8a96e;background:rgba(200,169,110,0.15);color:#c8a96e'
            : 'border-color:var(--app-border);color:var(--app-text-faint)'"
          @click="toggleDelay(m)"
        >
          {{ m }} mois <span v-if="selectedDelays.includes(m)">✓</span>
        </button>
      </div>
      <p class="text-xs" style="color: var(--app-text-muted)">
        Actif : <span class="font-semibold" style="color:#c8a96e">
          {{ selectedDelays.sort((a,b)=>a-b).map(d=>`${d} mois`).join(', ') }}
        </span>
      </p>
      <button class="btn btn-gold btn-md" :disabled="savingPrefs" @click="savePreferences">
        {{ savingPrefs ? 'Enregistrement...' : 'Enregistrer les préférences' }}
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-3/4 rounded mb-2" style="background: var(--app-border)"/>
        <div class="h-3 w-1/3 rounded" style="background: var(--app-border)"/>
      </div>
    </div>

    <!-- Notification list -->
    <div
      v-else-if="notifications.length"
      class="rounded-2xl overflow-hidden"
      style="background: var(--app-surface); border: 1px solid var(--app-border-2)"
    >
      <div
        v-for="(n, i) in notifications" :key="n.id"
        :class="[i < notifications.length - 1 ? 'border-b' : '']"
        :style="i < notifications.length - 1 ? 'border-color: var(--app-border-2)' : ''"
      >
        <!-- Row — clickable to expand detail -->
        <div
          class="px-4 py-3.5 flex items-start gap-3 cursor-pointer transition-colors"
          :style="!n.is_read
            ? 'background: rgba(200,169,110,0.05)'
            : expandedId === n.id ? 'background: var(--app-surface-2)' : ''"
          @click="toggleExpand(n.id)"
        >
          <!-- Color dot -->
          <div
            class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5"
            :style="`background: ${notifColor(n)}; opacity: ${n.is_read ? 0.3 : 1}`"
          />

          <div class="flex-1 min-w-0">
            <p
              class="text-sm leading-snug"
              :class="!n.is_read ? 'font-semibold' : ''"
              :style="n.is_read ? 'color: var(--app-text-muted)' : 'color: var(--app-text)'"
            >{{ n.message }}</p>
            <p class="text-xs mt-0.5" style="color: var(--app-text-faint)">
              {{ formatDate(n.created_at) }}
            </p>
          </div>

          <!-- Right side: read status + expand chevron -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              v-if="!n.is_read"
              class="text-xs font-medium px-2 py-0.5 rounded-lg transition-colors"
              style="color: #c8a96e; background: rgba(200,169,110,0.1)"
              @click.stop="markRead(n.id)"
            >Lu</button>
            <span v-else class="text-xs" style="color: var(--app-text-faint)">✓</span>

            <!-- Expand chevron -->
            <svg
              width="12" height="12" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
              class="transition-transform duration-200"
              :style="`color: var(--app-text-faint); transform: rotate(${expandedId === n.id ? 180 : 0}deg)`"
            >
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </div>
        </div>

        <!-- Expanded detail panel -->
        <Transition name="expand">
          <div
            v-if="expandedId === n.id"
            class="px-5 pb-4 pt-1"
            style="background: var(--app-surface-2); border-top: 1px solid var(--app-border-2)"
          >
            <div class="rounded-xl p-4 space-y-2 text-sm" style="background: var(--app-surface); border: 1px solid var(--app-border)">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-2 h-2 rounded-full flex-shrink-0" :style="`background: ${notifColor(n)}`"/>
                <p class="font-semibold text-xs uppercase tracking-wide" :style="`color: ${notifColor(n)}`">
                  {{ n.type ?? 'Notification système' }}
                </p>
              </div>

              <!-- Full message -->
              <p style="color: var(--app-text)">{{ n.message }}</p>

              <!-- Extra metadata if available -->
              <div class="pt-2 space-y-1 text-xs" style="color: var(--app-text-muted)">
                <p v-if="n.data?.contrat_id">
                  <span class="font-medium" style="color: var(--app-text)">Contrat ID :</span>
                  #{{ n.data.contrat_id }}
                </p>
                <p v-if="n.data?.entreprise">
                  <span class="font-medium" style="color: var(--app-text)">Entreprise :</span>
                  {{ n.data.entreprise }}
                </p>
                <p v-if="n.data?.montant">
                  <span class="font-medium" style="color: var(--app-text)">Montant :</span>
                  {{ n.data.montant }} DH
                </p>
                <p v-if="n.data?.date_expiration">
                  <span class="font-medium" style="color: var(--app-text)">Expiration :</span>
                  {{ n.data.date_expiration }}
                </p>
                <p>
                  <span class="font-medium" style="color: var(--app-text)">Reçu le :</span>
                  {{ formatDate(n.created_at) }}
                </p>
                <p>
                  <span class="font-medium" style="color: var(--app-text)">Statut :</span>
                  <span :style="n.is_read ? 'color:#22c55e' : 'color:#f59e0b'">
                    {{ n.is_read ? 'Lu' : 'Non lu' }}
                  </span>
                </p>
              </div>

              <div class="flex gap-2 pt-2">
                <button
                  v-if="!n.is_read"
                  class="btn btn-outline btn-sm"
                  @click="markRead(n.id)"
                >Marquer comme lu</button>
                <button class="btn btn-outline btn-sm" @click="expandedId = null">Fermer</button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center" style="color: var(--app-text-faint)">
      <p class="text-3xl mb-3">🔔</p>
      <p>Aucune notification.</p>
    </div>

  </div>
</template>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: max-height 0.25s ease, opacity 0.2s ease;
  overflow: hidden;
  max-height: 400px;
}
.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>