<!-- ============================================================
  pages/admin/dashboard.vue
  Tableau de bord — affiché pour admin ET domiciliataire
  - admin        : voit les stats globales (sans données sensibles)
  - domiciliataire : voit ses propres stats
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()

// ── Helpers ───────────────────────────────────────────────
function getApiBase(): string {
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

// ── Types ─────────────────────────────────────────────────
interface Stats {
  total_clients:   number
  total_contrats:  number
  contrats_actifs: number
  contrats_draft:  number
  total_documents: number
  ca_mensuel:      string
}

// ── State ─────────────────────────────────────────────────
const stats          = ref<Stats | null>(null)
const recentClients  = ref<any[]>([])
const recentContrats = ref<any[]>([])
const loading        = ref(true)
const loadError      = ref('')

// ── Chargement données ────────────────────────────────────
async function loadDashboard(): Promise<void> {
  loading.value   = true
  loadError.value = ''
  try {
    const [statsRes, clientsRes, contratsRes] = await Promise.all([
      $fetch<{ success: boolean; data: Stats }>(
        `${getApiBase()}/api/dashboard/stats`,
        { headers: authHeaders() }
      ),
      $fetch<{ success: boolean; data: any[] }>(
        `${getApiBase()}/api/clients`,
        { headers: authHeaders() }
      ),
      $fetch<{ success: boolean; data: any[] }>(
        `${getApiBase()}/api/contrats`,
        { headers: authHeaders() }
      ),
    ])

    stats.value = statsRes.data ?? null

    // Gère les deux formats : tableau direct ou paginé Laravel
    const clientsData = Array.isArray(clientsRes.data)
      ? clientsRes.data
      : (clientsRes as any)?.data?.data ?? []
    recentClients.value = clientsData.slice(0, 5)

    const contratsData = Array.isArray(contratsRes.data)
      ? contratsRes.data
      : (contratsRes as any)?.data?.data ?? []
    recentContrats.value = contratsData.slice(0, 5)

  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
    console.error('Dashboard error:', e)
  } finally {
    loading.value = false
  }
}

// ── Couleurs statuts ──────────────────────────────────────
const statutContratColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

const statutClientColor: Record<string, string> = {
  actif:    'text-green-400 bg-green-400/10',
  inactif:  'text-red-400 bg-red-400/10',
  suspendu: 'text-yellow-400 bg-yellow-400/10',
}

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6 animate-fade-up">

    <!-- Titre avec badge de rôle -->
    <div class="flex items-start justify-between flex-wrap gap-2">
      <div>
        <h2 class="font-serif text-[22px]">
          Bonjour, <em class="text-gold italic">{{ auth.user?.name ?? 'Admin' }}</em> 👋
        </h2>
        <p class="text-app-text/50 text-sm mt-1">Vue d'ensemble de votre espace domiciliation</p>
      </div>
      <!-- Badge rôle -->
      <span
        class="text-xs px-3 py-1 rounded-full font-bold mt-1"
        :style="auth.isAdmin
          ? 'background:rgba(239,68,68,0.15);color:#ef4444'
          : 'background:rgba(200,169,110,0.15);color:#c8a96e'"
      >
        {{ auth.isAdmin ? 'Super Admin' : 'Domiciliataire' }}
      </span>
    </div>

    <!-- Message d'erreur -->
    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton loading -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div v-for="i in 5" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 w-20 bg-white/10 rounded mb-3" />
        <div class="h-8 w-12 bg-white/10 rounded" />
      </div>
    </div>

    <!-- Stats réelles -->
    <div v-else-if="stats" class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="card p-5">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Clients</div>
        <div class="font-serif text-3xl text-gold">{{ stats.total_clients ?? 0 }}</div>
      </div>
      <div class="card p-5">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Contrats actifs</div>
        <div class="font-serif text-3xl text-gold">{{ stats.contrats_actifs ?? 0 }}</div>
      </div>
      <div class="card p-5">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Brouillons</div>
        <div class="font-serif text-3xl text-gold">{{ stats.contrats_draft ?? 0 }}</div>
      </div>
      <div class="card p-5">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">Total contrats</div>
        <div class="font-serif text-3xl text-gold">{{ stats.total_contrats ?? 0 }}</div>
      </div>
      <div class="card p-5">
        <div class="text-[11px] text-app-text/40 uppercase mb-2">CA ce mois</div>
        <div class="font-serif text-2xl text-gold">
          {{ stats.ca_mensuel ?? '0' }} <span class="text-sm">DH</span>
        </div>
      </div>
    </div>

    <!-- Grille clients + contrats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

      <!-- Derniers clients -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Derniers clients</h3>
          <NuxtLink to="/admin/clients" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-10 bg-white/5 rounded animate-pulse" />
        </div>

        <div v-else-if="recentClients.length" class="space-y-1">
          <div
            v-for="c in recentClients" :key="c.id"
            class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 transition"
          >
            <!-- Avatar initiales -->
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
              style="background:rgba(200,169,110,0.15);color:#c8a96e"
            >
              {{ (c.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
            </div>

            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium truncate">{{ c.raison_sociale }}</p>
              <!-- Admin ne voit pas les détails sensibles (CIN etc.) -->
              <p class="text-xs text-app-text/40 truncate">{{ c.ville ?? '-' }}</p>
            </div>

            <span
              class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
              :class="statutClientColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ c.statut ?? '-' }}
            </span>
          </div>
        </div>

        <div v-else class="text-sm text-app-text/40 text-center py-6">
          <p>Aucun client</p>
          <NuxtLink
            v-if="auth.isDomiciliataire"
            to="/admin/clients"
            class="text-gold underline text-xs mt-1 block"
          >
            Créer un client →
          </NuxtLink>
        </div>
      </div>

      <!-- Derniers contrats -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold">Derniers contrats</h3>
          <NuxtLink to="/admin/contrats" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-10 bg-white/5 rounded animate-pulse" />
        </div>

        <div v-else-if="recentContrats.length" class="space-y-1">
          <div
            v-for="c in recentContrats" :key="c.id"
            class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 transition"
          >
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium truncate">
                {{ c.entreprise?.raison_sociale ?? `Contrat #${c.id}` }}
              </p>
              <p class="text-xs text-app-text/40">
                {{ c.date_debut ?? '-' }} → {{ c.date_fin ?? '-' }}
              </p>
            </div>
            <span
              class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
              :class="statutContratColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ c.statut ?? '-' }}
            </span>
          </div>
        </div>

        <div v-else class="text-sm text-app-text/40 text-center py-6">
          <p>Aucun contrat</p>
          <NuxtLink
            v-if="auth.isDomiciliataire"
            to="/admin/contrat?new=1"
            class="text-gold underline text-xs mt-1 block"
          >
            Créer un contrat →
          </NuxtLink>
        </div>
      </div>

    </div>

    <!-- Actions rapides — seulement pour domiciliataire, pas admin lecture -->
    <div v-if="auth.isDomiciliataire" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <NuxtLink
        to="/admin/clients"
        class="card p-4 text-center hover:border-gold/30 transition cursor-pointer"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">👥</div>
        <div class="text-xs font-semibold">Nouveau client</div>
      </NuxtLink>
      <NuxtLink
        to="/admin/contrat?new=1"
        class="card p-4 text-center hover:border-gold/30 transition cursor-pointer"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">📄</div>
        <div class="text-xs font-semibold">Nouveau contrat</div>
      </NuxtLink>
      <NuxtLink
        to="/admin/documents"
        class="card p-4 text-center hover:border-gold/30 transition cursor-pointer"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">📁</div>
        <div class="text-xs font-semibold">Documents</div>
      </NuxtLink>
      <NuxtLink
        to="/admin/scan"
        class="card p-4 text-center hover:border-gold/30 transition cursor-pointer"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">🖨️</div>
        <div class="text-xs font-semibold">Scanner / Importer</div>
      </NuxtLink>
    </div>

  </div>
</template>