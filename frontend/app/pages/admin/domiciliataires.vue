<!-- ============================================================
  pages/admin/domiciliataires.vue
  Page admin uniquement — liste tous les domiciliataires
  Sans données sensibles (CIN, mots de passe)
  Accès en lecture seule
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()

// Rediriger si pas admin
onMounted(async () => {
  if (!auth.isAdmin) {
    await navigateTo('/admin/dashboard')
    return
  }
  load()
})

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

const domiciliataires = ref<any[]>([])
const loading         = ref(true)
const search          = ref('')

async function load(): Promise<void> {
  loading.value = true
  try {
    // Récupère tous les users avec rôle domiciliataire
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/admin/domiciliataires`,
      { headers: authHeaders() }
    )
    domiciliataires.value = res.data ?? []
  } catch (e: any) {
    console.error('Erreur chargement domiciliataires', e)
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!search.value.trim()) return domiciliataires.value
  const q = search.value.toLowerCase()
  return domiciliataires.value.filter(d =>
    `${d.nom} ${d.prenom}`.toLowerCase().includes(q) ||
    d.email?.toLowerCase().includes(q)
  )
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Domiciliataires <em class="text-gold italic">enregistrés</em>
        </h1>
        <p class="text-app-text/50 text-sm mt-1">
          {{ domiciliataires.length }} domiciliataire(s) — lecture seule
        </p>
      </div>
      <!-- Badge admin -->
      <span class="text-xs px-3 py-1 rounded-full font-bold" style="background:rgba(239,68,68,0.15);color:#ef4444">
        Super Admin
      </span>
    </div>

    <!-- Recherche -->
    <div class="card p-3">
      <input v-model="search" class="f-input" placeholder="Rechercher par nom, email..." />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Liste -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="d in filtered" :key="d.id"
        class="card p-4"
      >
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="flex items-center gap-4">
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
              style="background:rgba(200,169,110,0.15);color:#c8a96e"
            >
              {{ (d.nom ?? 'D').slice(0, 2).toUpperCase() }}
            </div>
            <div>
              <p class="font-semibold">{{ d.nom }} {{ d.prenom }}</p>
              <p class="text-xs text-app-text/50">{{ d.email }}</p>
              <p v-if="d.telephone" class="text-xs text-app-text/40">{{ d.telephone }}</p>
            </div>
          </div>

          <!-- Stats du domiciliataire (sans données sensibles) -->
          <div class="flex gap-4 text-center">
            <div>
              <p class="text-xl font-serif text-gold">{{ d.entreprises_count ?? 0 }}</p>
              <p class="text-xs text-app-text/40">Clients</p>
            </div>
            <div>
              <p class="text-xl font-serif text-gold">{{ d.contrats_count ?? 0 }}</p>
              <p class="text-xs text-app-text/40">Contrats</p>
            </div>
          </div>
        </div>

        <!-- Clients du domiciliataire (noms uniquement, pas de données sensibles) -->
        <div v-if="d.entreprises?.length" class="mt-3 pt-3 border-t border-white/5">
          <p class="text-xs text-app-text/40 mb-2">Entreprises clientes :</p>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="e in d.entreprises" :key="e.id"
              class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-app-text/60"
            >
              {{ e.raison_sociale }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">👤</p>
      <p>Aucun domiciliataire trouvé.</p>
    </div>

  </div>
</template>
