<!-- ============================================================
  pages/admin/dashboard.vue — avec widget Messages récents
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()

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

interface Stats {
  total_clients: number; total_contrats: number
  contrats_actifs: number; contrats_draft: number
  total_documents: number; ca_mensuel: string
}

const stats          = ref<Stats | null>(null)
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
    stats.value         = statsRes.data ?? null
    recentClients.value  = (Array.isArray(clientsRes.data) ? clientsRes.data : (clientsRes as any)?.data?.data ?? []).slice(0, 4)
    recentContrats.value = (Array.isArray(contratsRes.data) ? contratsRes.data : (contratsRes as any)?.data?.data ?? []).slice(0, 4)
    recentMessages.value = (msgsRes.data ?? []).slice(0, 4)
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

const router = useRouter()

const statutContratColor: Record<string, string> = {
  draft: 'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
  expired: 'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR')
}

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6 animate-fade-up">

    <!-- Titre -->
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

    <!-- Stats skeleton -->
    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 w-20 bg-white/10 rounded mb-3" /><div class="h-8 w-12 bg-white/10 rounded" />
      </div>
    </div>

    <!-- Stats -->
    <div v-else-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="card p-5"><div class="text-[11px] text-app-text/40 uppercase mb-2">Clients</div><div class="font-serif text-3xl text-gold">{{ stats.total_clients ?? 0 }}</div></div>
      <div class="card p-5"><div class="text-[11px] text-app-text/40 uppercase mb-2">Contrats actifs</div><div class="font-serif text-3xl text-gold">{{ stats.contrats_actifs ?? 0 }}</div></div>
      <div class="card p-5"><div class="text-[11px] text-app-text/40 uppercase mb-2">Brouillons</div><div class="font-serif text-3xl text-gold">{{ stats.contrats_draft ?? 0 }}</div></div>
      <div class="card p-5"><div class="text-[11px] text-app-text/40 uppercase mb-2">CA ce mois</div><div class="font-serif text-2xl text-gold">{{ stats.ca_mensuel ?? '0' }} <span class="text-sm">DH</span></div></div>
    </div>

    <!-- Grille 3 colonnes -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

      <!-- Derniers clients -->
      <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-sm">Derniers clients</h3>
          <NuxtLink to="/admin/clients" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>
        <div v-if="loading" class="space-y-2"><div v-for="i in 3" :key="i" class="h-8 bg-white/5 rounded animate-pulse" /></div>
        <div v-else-if="recentClients.length" class="space-y-1">
          <div v-for="c in recentClients" :key="c.id" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/5 transition cursor-pointer" @click="$router.push('/admin/clients')">
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
          <NuxtLink to="/admin/contrat" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>
        <div v-if="loading" class="space-y-2"><div v-for="i in 3" :key="i" class="h-8 bg-white/5 rounded animate-pulse" /></div>
        <div v-else-if="recentContrats.length" class="space-y-1">
          <div v-for="c in recentContrats" :key="c.id" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/5 transition">
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

    <!-- Actions rapides (domiciliataire seulement) -->
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