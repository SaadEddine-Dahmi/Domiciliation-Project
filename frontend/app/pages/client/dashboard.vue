<!-- ============================================================
  pages/client/dashboard.vue
  Dashboard client — affiche :
  - Son entreprise et son statut
  - Son domiciliataire (nom, email, tel)
  - Son contrat (statut, période, PDF si disponible)
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

// ── State ─────────────────────────────────────────────────
const loading  = ref(true)
const data     = ref<any>(null)
const loadError = ref('')

// ── Chargement ────────────────────────────────────────────
async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/dashboard/stats`,
      { headers: authHeaders() }
    )
    data.value = res.data
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

// ── Télécharger PDF du contrat ────────────────────────────
async function downloadContrat(): Promise<void> {
  const url = data.value?.contrat?.pdf_url
  if (!url) return
  const response = await fetch(url)
  const blob     = await response.blob()
  const blobUrl  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
  const a        = document.createElement('a')
  a.href         = blobUrl
  a.download     = 'mon-contrat.pdf'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
}

const statutColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
  actif:      'text-green-400 bg-green-400/10',
  inactif:    'text-red-400 bg-red-400/10',
  suspendu:   'text-yellow-400 bg-yellow-400/10',
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Titre -->
    <div>
      <h2 class="font-serif text-[22px]">
        Bonjour, <em class="text-gold italic">{{ auth.user?.name ?? 'Client' }}</em> 👋
      </h2>
      <p class="text-app-text/50 text-sm mt-1">Votre espace personnel de domiciliation</p>
    </div>

    <!-- Erreur -->
    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in 2" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 w-24 bg-white/10 rounded mb-3" />
        <div class="h-6 w-32 bg-white/10 rounded" />
      </div>
    </div>

    <!-- Contenu -->
    <div v-else-if="data" class="grid grid-cols-1 md:grid-cols-2 gap-5">

      <!-- Mon entreprise -->
      <div class="card p-5 space-y-3">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon entreprise</p>
        <div v-if="data.entreprise" class="space-y-2">
          <p class="font-serif text-xl">{{ data.entreprise.raison_sociale }}</p>
          <p class="text-sm text-app-text/50">{{ data.entreprise.ville ?? '-' }}</p>
          <span
            class="inline-block text-xs px-2 py-0.5 rounded-full"
            :class="statutColor[data.entreprise.statut] ?? 'text-app-text/40 bg-white/5'"
          >
            {{ data.entreprise.statut }}
          </span>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucune entreprise liée</p>
      </div>

      <!-- Mon domiciliataire -->
      <div class="card p-5 space-y-3">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon domiciliataire</p>
        <div v-if="data.domiciliataire" class="space-y-1 text-sm">
          <p class="font-semibold">{{ data.domiciliataire.nom }} {{ data.domiciliataire.prenom }}</p>
          <p class="text-app-text/50">{{ data.domiciliataire.email }}</p>
          <p v-if="data.domiciliataire.telephone" class="text-app-text/50">
            {{ data.domiciliataire.telephone }}
          </p>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucun domiciliataire lié</p>
      </div>

      <!-- Mon contrat -->
      <div class="card p-5 space-y-3 md:col-span-2">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon contrat</p>
        <div v-if="data.contrat" class="space-y-3">
          <div class="flex items-center gap-3 flex-wrap">
            <span
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="statutColor[data.contrat.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ data.contrat.statut }}
            </span>
            <p class="text-sm text-app-text/50">
              {{ data.contrat.date_debut ?? '-' }} → {{ data.contrat.date_fin ?? '-' }}
            </p>
            <p v-if="data.contrat.prix_total" class="text-sm font-semibold text-gold">
              {{ data.contrat.prix_total }} DH
            </p>
          </div>

          <!-- Télécharger PDF -->
          <button
            v-if="data.contrat.pdf_url"
            class="btn btn-gold btn-md"
            @click="downloadContrat"
          >
            ⬇ Télécharger mon contrat PDF
          </button>
          <p v-else class="text-xs text-app-text/40">
            Le PDF de votre contrat n'est pas encore disponible.
          </p>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucun contrat généré</p>
      </div>

    </div>

    <!-- Accès rapides -->
    <div class="grid grid-cols-2 gap-3">
      <NuxtLink
        to="/client/documents"
        class="card p-4 text-center hover:border-gold/30 transition"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">📁</div>
        <div class="text-xs font-semibold">Mes Documents</div>
      </NuxtLink>
      <NuxtLink
        to="/client/contrat"
        class="card p-4 text-center hover:border-gold/30 transition"
        style="border:1px solid rgba(255,255,255,0.06)"
      >
        <div class="text-2xl mb-2">📄</div>
        <div class="text-xs font-semibold">Mon Contrat</div>
      </NuxtLink>
    </div>

  </div>
</template>