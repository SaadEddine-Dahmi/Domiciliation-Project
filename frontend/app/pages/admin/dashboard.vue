<!-- pages/admin/dashboard.vue
  Domiciliataire / admin dashboard.

  Features:
    - Stat cards are clickable, each routing to its relevant page
      (clients list, contracts list filtered by status, payments page).
    - Charts are built with plain inline SVG — no charting library
      dependency. All data is derived from real API responses:
        · Bar chart:   new contracts vs. ended contracts per month,
                        computed client-side from date_debut / date_fin.
        · Donut chart: contract status breakdown (draft/active/expired/
                        terminated), computed from the same /api/contrats list.
        · Line chart:  cumulative client count over the last 6 months,
                        derived from each client's created_at.
    - Expired-contracts stat card computed client-side (statut === 'expired'),
      no backend change required.
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth   = useAuthStore()
const router = useRouter()

function getApiBase(): string {
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

interface Stats {
  total_clients: number; total_contrats: number
  contrats_actifs: number; contrats_draft: number
  total_documents: number; ca_mensuel: string
}

const stats          = ref<Stats | null>(null)
const allContrats    = ref<any[]>([])
const allClients     = ref<any[]>([])
const recentClients  = ref<any[]>([])
const recentContrats = ref<any[]>([])
const recentMessages = ref<any[]>([])
const loading        = ref(true)
const loadError      = ref('')

async function loadDashboard() {
  loading.value = true
  try {
    const [statsRes, clientsRes, contratsRes, msgsRes] = await Promise.all([
      $fetch<{ success: boolean; data: Stats }>(`${getApiBase()}/api/dashboard/stats`, { headers: authHeaders() }),
      $fetch<{ success: boolean; data: any[] }>(`${getApiBase()}/api/clients`, { headers: authHeaders() }),
      $fetch<{ success: boolean; data: any[] }>(`${getApiBase()}/api/contrats`, { headers: authHeaders() }),
      $fetch<{ success: boolean; data: any[] }>(`${getApiBase()}/api/messages`, { headers: authHeaders() }),
    ])

    stats.value = statsRes.data ?? null

    const clientsData  = Array.isArray(clientsRes.data)  ? clientsRes.data  : []
    const contratsData = Array.isArray(contratsRes.data) ? contratsRes.data : []

    allClients.value  = clientsData
    allContrats.value = contratsData

    recentClients.value  = clientsData.slice(0, 4)
    recentContrats.value = contratsData.slice(0, 4)
    recentMessages.value = (msgsRes.data ?? []).slice(0, 4)
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

const statutContratColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR')
}

// ── Expired contracts stat card ─────────────────────────────────────────────

/** Count of contracts currently in the 'expired' status — feeds the stat card */
const expiredContractsCount = computed(() =>
  allContrats.value.filter(c => c.statut === 'expired').length
)

// ── Chart data: last 6 months window ────────────────────────────────────────

const monthLabels = computed(() => {
  const labels: { key: string; label: string }[] = []
  const now = new Date()
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    labels.push({
      key: `${d.getFullYear()}-${d.getMonth()}`,
      label: d.toLocaleDateString('fr-FR', { month: 'short' }),
    })
  }
  return labels
})

/** New contracts per month, counted by date_debut falling in that month */
const newContractsByMonth = computed(() => {
  return monthLabels.value.map(({ key }) => {
    return allContrats.value.filter(c => {
      if (!c.date_debut) return false
      const d = new Date(c.date_debut)
      return `${d.getFullYear()}-${d.getMonth()}` === key
    }).length
  })
})

/** Ended contracts per month — expired or terminated, counted by date_fin */
const endedContractsByMonth = computed(() => {
  return monthLabels.value.map(({ key }) => {
    return allContrats.value.filter(c => {
      if (!c.date_fin) return false
      if (!['expired', 'terminated'].includes(c.statut)) return false
      const d = new Date(c.date_fin)
      return `${d.getFullYear()}-${d.getMonth()}` === key
    }).length
  })
})

const maxMonthlyValue = computed(() => {
  const all = [...newContractsByMonth.value, ...endedContractsByMonth.value]
  return Math.max(1, ...all)
})

/** Bar height as a percentage of the chart's plot area */
function barHeight(value: number): number {
  return Math.round((value / maxMonthlyValue.value) * 100)
}

// ── Status donut ─────────────────────────────────────────────────────────────

const statusBreakdown = computed(() => {
  const counts: Record<string, number> = { draft: 0, active: 0, expired: 0, terminated: 0 }
  allContrats.value.forEach(c => {
    if (counts[c.statut] !== undefined) counts[c.statut]++
  })
  return counts
})

const statusColors: Record<string, string> = {
  draft:      '#facc15',
  active:     '#22c55e',
  expired:    '#ef4444',
  terminated: '#6b7280',
}

const statusLabels: Record<string, string> = {
  draft: 'Brouillons', active: 'Actifs', expired: 'Expirés', terminated: 'Résiliés',
}

/** Precompute SVG donut arc segments (stroke-dasharray technique) */
const donutSegments = computed(() => {
  const total = Object.values(statusBreakdown.value).reduce((a, b) => a + b, 0)
  if (total === 0) return []

  const circumference = 2 * Math.PI * 40 // radius 40
  let offset = 0
  const segments: { key: string; color: string; dash: string; dashOffset: number; pct: number }[] = []

  for (const [key, count] of Object.entries(statusBreakdown.value)) {
    if (count === 0) continue
    const pct = count / total
    const dashLength = pct * circumference
    segments.push({
      key,
      color: statusColors[key],
      dash: `${dashLength} ${circumference - dashLength}`,
      dashOffset: -offset,
      pct: Math.round(pct * 100),
    })
    offset += dashLength
  }
  return segments
})

const totalContractsForDonut = computed(() =>
  Object.values(statusBreakdown.value).reduce((a, b) => a + b, 0)
)

// ── Client growth (cumulative, last 6 months) ────────────────────────────────

const clientGrowthByMonth = computed(() => {
  return monthLabels.value.map(({ key }) => {
    const [year, month] = key.split('-').map(Number)
    const cutoff = new Date(year, month + 1, 0, 23, 59, 59)
    return allClients.value.filter(c => {
      if (!c.created_at) return false
      return new Date(c.created_at) <= cutoff
    }).length
  })
})

const maxClientGrowth = computed(() => Math.max(1, ...clientGrowthByMonth.value))

/** SVG polyline points for the client growth line chart */
const growthLinePoints = computed(() => {
  const width  = 280
  const height = 80
  const step   = width / Math.max(1, clientGrowthByMonth.value.length - 1)

  return clientGrowthByMonth.value
    .map((val, i) => {
      const x = i * step
      const y = height - (val / maxClientGrowth.value) * height
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6 animate-fade-up">

    <div class="flex items-start justify-between flex-wrap gap-2">
      <div>
        <h2 class="font-serif text-[22px]">
          Bonjour, <em class="text-gold italic">{{ auth.user?.name ?? 'Admin' }}</em> 👋
        </h2>
        <p class="text-app-text/50 text-sm mt-1">Vue d'ensemble de votre espace domiciliation</p>
      </div>
      <span class="text-xs px-3 py-1 rounded-full font-bold mt-1"
        style="background:rgba(200,169,110,0.15);color:#c8a96e">
        {{ auth.isAdmin ? 'Super Admin' : 'Domiciliataire' }}
      </span>
    </div>

    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- ══════ Stat cards — clickable ═══════════════════════════════════ -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div v-for="i in 5" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 w-20 bg-white/10 rounded mb-3" /><div class="h-8 w-12 bg-white/10 rounded" />
      </div>
    </div>

    <div v-else-if="stats" class="grid grid-cols-2 md:grid-cols-5 gap-4">

      <!-- Clients -->
      <button type="button" class="card p-5 text-left stat-card" @click="router.push('/admin/clients')">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Clients</div>
        <div class="font-serif text-3xl text-gold">{{ stats.total_clients ?? 0 }}</div>
      </button>

      <!-- Active contracts -->
      <button type="button" class="card p-5 text-left stat-card" @click="router.push('/admin/contrats?statut=active')">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Contrats actifs</div>
        <div class="font-serif text-3xl text-gold">{{ stats.contrats_actifs ?? 0 }}</div>
      </button>

      <!-- Draft contracts -->
      <button type="button" class="card p-5 text-left stat-card" @click="router.push('/admin/contrats?statut=draft')">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Brouillons</div>
        <div class="font-serif text-3xl text-gold">{{ stats.contrats_draft ?? 0 }}</div>
      </button>

      <!-- Expired contracts -->
      <button type="button" class="card p-5 text-left stat-card" @click="router.push('/admin/contrats?statut=expired')">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Contrats expirés</div>
        <div class="font-serif text-3xl" :class="expiredContractsCount > 0 ? 'text-red-400' : 'text-gold'">
          {{ expiredContractsCount }}
        </div>
      </button>

      <!-- Revenue -->
      <button type="button" class="card p-5 text-left stat-card" @click="router.push('/admin/paiements')">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">CA ce mois</div>
        <div class="font-serif text-2xl text-gold">{{ stats.ca_mensuel ?? '0' }} <span class="text-sm">DH</span></div>
      </button>

    </div>

    <!-- ══════ Charts row ═══════════════════════════════════════════════ -->
    <div v-if="!loading" class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Bar chart: new vs ended contracts per month -->
      <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Contrats — 6 derniers mois
          </p>
          <div class="flex items-center gap-3 text-[11px]" style="color:var(--app-text-faint)">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full inline-block" style="background:#c8a96e"/> Nouveaux</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full inline-block" style="background:#ef4444"/> Terminés</span>
          </div>
        </div>

        <div v-if="allContrats.length === 0" class="text-center py-10 text-sm" style="color:var(--app-text-faint)">
          Aucun contrat à afficher pour l'instant.
        </div>

        <div v-else class="flex items-end justify-between gap-2" style="height:160px">
          <div
            v-for="(m, i) in monthLabels"
            :key="m.key"
            class="flex-1 flex flex-col items-center justify-end h-full gap-1"
          >
            <div class="flex items-end gap-1 w-full justify-center" style="height:130px">
              <div
                class="rounded-t-md transition-all duration-500"
                style="width:10px;background:linear-gradient(180deg,#c8a96e,#b89558)"
                :style="`height:${barHeight(newContractsByMonth[i])}%`"
                :title="`${newContractsByMonth[i]} nouveaux`"
              />
              <div
                class="rounded-t-md transition-all duration-500"
                style="width:10px;background:#ef4444"
                :style="`height:${barHeight(endedContractsByMonth[i])}%`"
                :title="`${endedContractsByMonth[i]} terminés`"
              />
            </div>
            <span class="text-[10px] capitalize" style="color:var(--app-text-faint)">{{ m.label }}</span>
          </div>
        </div>
      </div>

      <!-- Donut: contract status breakdown -->
      <div class="card p-5">
        <p class="text-xs uppercase tracking-widest font-bold mb-4" style="color:#c8a96e">
          Répartition des statuts
        </p>

        <div v-if="totalContractsForDonut === 0" class="text-center py-10 text-sm" style="color:var(--app-text-faint)">
          Aucun contrat pour le moment.
        </div>

        <div v-else class="flex items-center gap-5">
          <svg viewBox="0 0 100 100" width="100" height="100" class="shrink-0" style="transform:rotate(-90deg)">
            <circle cx="50" cy="50" r="40" fill="none" stroke="var(--app-border)" stroke-width="14" />
            <circle
              v-for="seg in donutSegments" :key="seg.key"
              cx="50" cy="50" r="40" fill="none"
              :stroke="seg.color" stroke-width="14"
              :stroke-dasharray="seg.dash"
              :stroke-dashoffset="seg.dashOffset"
              stroke-linecap="butt"
            />
          </svg>
          <div class="space-y-1.5 text-xs flex-1 min-w-0">
            <div v-for="(count, key) in statusBreakdown" :key="key" class="flex items-center justify-between gap-2">
              <span class="flex items-center gap-1.5 truncate">
                <span class="w-2 h-2 rounded-full shrink-0" :style="`background:${statusColors[key]}`" />
                {{ statusLabels[key] }}
              </span>
              <span class="font-semibold shrink-0" style="color:var(--app-text)">{{ count }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Client growth line chart -->
    <div v-if="!loading && allClients.length > 0" class="card p-5">
      <p class="text-xs uppercase tracking-widest font-bold mb-4" style="color:#c8a96e">
        Croissance du portefeuille clients
      </p>
      <div class="flex items-center gap-4">
        <svg viewBox="0 0 280 80" width="100%" height="80" preserveAspectRatio="none" class="flex-1">
          <polyline
            :points="growthLinePoints"
            fill="none"
            stroke="#c8a96e"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="flex justify-between mt-2">
        <span v-for="m in monthLabels" :key="m.key" class="text-[10px] capitalize" style="color:var(--app-text-faint)">
          {{ m.label }}
        </span>
      </div>
    </div>

    <!-- ══════ Recent activity ══════════════════════════════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

      <!-- Derniers clients -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-sm">Derniers clients</h3>
          <NuxtLink to="/admin/clients" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>
        <div v-if="loading" class="space-y-2"><div v-for="i in 3" :key="i" class="h-8 bg-white/5 rounded animate-pulse" /></div>
        <div v-else-if="recentClients.length" class="space-y-1">
          <div v-for="c in recentClients" :key="c.id" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/5 transition cursor-pointer" @click="router.push('/admin/clients')">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0" style="background:rgba(200,169,110,0.15);color:#c8a96e">
              {{ (c.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
            </div>
            <div class="min-w-0"><p class="text-xs font-medium truncate">{{ c.raison_sociale }}</p><p class="text-[10px] text-app-text/40 truncate">{{ c.ville ?? '-' }}</p></div>
          </div>
        </div>
        <p v-else class="text-xs text-app-text/40 text-center py-3">Aucun client</p>
      </div>

      <!-- Derniers contrats -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-sm">Derniers contrats</h3>
          <NuxtLink to="/admin/contrats" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>
        <div v-if="loading" class="space-y-2"><div v-for="i in 3" :key="i" class="h-8 bg-white/5 rounded animate-pulse" /></div>
        <div v-else-if="recentContrats.length" class="space-y-1">
          <div v-for="c in recentContrats" :key="c.id" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/5 transition cursor-pointer" @click="router.push('/admin/contrats')">
            <div class="min-w-0 flex-1"><p class="text-xs font-medium truncate">{{ c.entreprise?.raison_sociale ?? `#${c.id}` }}</p><p class="text-[10px] text-app-text/40">{{ c.date_fin ?? '-' }}</p></div>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full flex-shrink-0" :class="statutContratColor[c.statut] ?? 'text-app-text/40 bg-white/5'">{{ c.statut }}</span>
          </div>
        </div>
        <p v-else class="text-xs text-app-text/40 text-center py-3">Aucun contrat</p>
      </div>

      <!-- Messages récents -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-sm">Messages envoyés</h3>
          <NuxtLink to="/admin/messages" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>
        <div v-if="loading" class="space-y-2"><div v-for="i in 3" :key="i" class="h-8 bg-white/5 rounded animate-pulse" /></div>
        <div v-else-if="recentMessages.length" class="space-y-1">
          <div v-for="m in recentMessages" :key="m.id" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/5 transition">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium truncate">{{ m.subject || 'Message' }}</p>
              <p class="text-[10px] text-app-text/40 truncate">{{ m.message }}</p>
            </div>
            <span class="text-[10px] flex-shrink-0" :class="m.is_read ? 'text-green-400' : 'text-app-text/40'">
              {{ m.is_read ? '✓✓' : '✓' }}
            </span>
          </div>
        </div>
        <div v-else class="text-center py-3">
          <p class="text-xs text-app-text/40">Aucun message</p>
          <button class="text-xs text-gold underline mt-1" @click="router.push('/admin/messages')">Envoyer un message →</button>
        </div>
      </div>

    </div>

    <!-- Actions rapides -->
    <div v-if="auth.isDomiciliataire" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <NuxtLink to="/admin/clients" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">👥</div><div class="text-xs font-semibold">Nouveau client</div>
      </NuxtLink>
      <NuxtLink to="/admin/contrat?new=1" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">📄</div><div class="text-xs font-semibold">Nouveau contrat</div>
      </NuxtLink>
      <NuxtLink to="/admin/messages" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">✉</div><div class="text-xs font-semibold">Envoyer un message</div>
      </NuxtLink>
      <NuxtLink to="/admin/scan" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">🖨️</div><div class="text-xs font-semibold">Scanner / Importer</div>
      </NuxtLink>
    </div>

  </div>
</template>

<style scoped>
.stat-card {
  cursor: pointer;
  transition: transform 0.2s ease, border-color 0.2s ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  border-color: rgba(200, 169, 110, 0.35);
}
.stat-card:active {
  transform: translateY(0);
}
</style>