<!-- app/pages/admin/factures.vue -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { error: toastError } = useToast()

// ── API helpers ───────────────────────────────────────────
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

/**
 * Gets the raw token string for ?token= query param on PDF URLs.
 * This is used for the iframe and direct download links because 
 * browsers do not send the Authorization header for those requests.
 */
function getToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch { return '' }
}

/**
 * Builds a backend URL with the token and mode appended.
 * Mode 'preview' streams the PDF, 'download' forces a download.
 */
function tokenUrl(url: string, mode: 'preview' | 'download' = 'preview'): string {
  const token = getToken()
  if (!token) return url
  // Using encodeURIComponent to handle special characters in the Sanctuum token
  return `${url}?token=${encodeURIComponent(token)}&mode=${mode}`
}

// ── State ─────────────────────────────────────────────────
const factures  = ref<any[]>([])
const loading   = ref(true)
const search    = ref('')
const filterStatut = ref('')

// Preview modal
const previewFacture = ref<any>(null)
const showPreview    = ref(false)
const previewLoading = ref(false)

function openPreview(f: any) {
  previewFacture.value  = f
  showPreview.value     = true
  previewLoading.value  = true
}

function closePreview() {
  showPreview.value    = false
  previewFacture.value = null
}

// ── Fetch ─────────────────────────────────────────────────
async function fetchFactures(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/factures`,
      { headers: authHeaders() }
    )
    factures.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement factures')
  } finally {
    loading.value = false
  }
}

// ── Computed ──────────────────────────────────────────────
const filtered = computed(() => {
  let list = factures.value
  if (filterStatut.value) {
    list = list.filter(f => f.statut === filterStatut.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(f =>
      f.numero_facture?.toLowerCase().includes(q) ||
      f.entreprise?.raison_sociale?.toLowerCase().includes(q) ||
      f.statut?.toLowerCase().includes(q)
    )
  }
  return list
})

// Stats
const totalAmount   = computed(() => factures.value.reduce((s, f) => s + Number(f.montant_total ?? 0), 0))
const paidAmount    = computed(() => factures.value.filter(f => f.statut === 'paid').reduce((s, f) => s + Number(f.montant_total ?? 0), 0))
const pendingAmount = computed(() => factures.value.filter(f => f.statut === 'pending').reduce((s, f) => s + Number(f.montant_total ?? 0), 0))
const paidCount     = computed(() => factures.value.filter(f => f.statut === 'paid').length)
const pendingCount  = computed(() => factures.value.filter(f => f.statut === 'pending').length)

// ── Helpers ───────────────────────────────────────────────
function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const statutCfg: Record<string, { cls: string; label: string }> = {
  paid:      { cls: 'text-green-400 bg-green-400/10',  label: 'Payée' },
  pending:   { cls: 'text-yellow-400 bg-yellow-400/10', label: 'En attente' },
  cancelled: { cls: 'text-red-400 bg-red-400/10',      label: 'Annulée' },
}

function sc(statut: string) {
  return statutCfg[statut] ?? { cls: 'text-gray-400 bg-gray-400/10', label: statut }
}

onMounted(fetchFactures)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Factures <em class="italic" style="color:#c8a96e">&amp; Historique</em>
        </h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">
          {{ factures.length }} facture(s) au total
        </p>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif" style="color:#c8a96e">
          {{ totalAmount.toLocaleString('fr-MA') }} DH
        </p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">Total facturé</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-green-400">
          {{ paidAmount.toLocaleString('fr-MA') }} DH
        </p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">
          Payé ({{ paidCount }} facture{{ paidCount > 1 ? 's' : '' }})
        </p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-yellow-400">
          {{ pendingAmount.toLocaleString('fr-MA') }} DH
        </p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">
          En attente ({{ pendingCount }})
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
             width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round"
             style="color:var(--app-text-faint)">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input
          v-model="search"
          class="f-input pl-9"
          placeholder="Rechercher par N° facture, entreprise..."
        />
      </div>
      <select v-model="filterStatut" class="f-input sm:w-48">
        <option value="">Tous les statuts</option>
        <option value="paid">Payées</option>
        <option value="pending">En attente</option>
        <option value="cancelled">Annulées</option>
      </select>
    </div>

    <!-- Table -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="card p-4 animate-pulse flex items-center gap-4">
        <div class="flex-1 space-y-2">
          <div class="h-3 w-1/3 rounded" style="background:var(--app-border)"/>
          <div class="h-3 w-1/2 rounded" style="background:var(--app-border)"/>
        </div>
        <div class="h-3 w-20 rounded" style="background:var(--app-border)"/>
      </div>
    </div>

    <div
      v-else-if="filtered.length"
      class="rounded-2xl overflow-hidden"
      style="background:var(--app-surface);border:1px solid var(--app-border-2)"
    >
      <!-- Table Header -->
      <div
        class="hidden sm:grid gap-4 px-5 py-3 text-[11px] uppercase tracking-widest font-bold"
        style="grid-template-columns:1.2fr 1.8fr 1fr 1fr 1fr auto;
               color:var(--app-text-faint);border-bottom:1px solid var(--app-border-2)"
      >
        <span>N° Facture</span>
        <span>Entreprise</span>
        <span>Date</span>
        <span class="text-right">Montant</span>
        <span class="text-center">Statut</span>
        <span></span>
      </div>

      <!-- Table Rows -->
      <div
        v-for="(f, i) in filtered" :key="f.id"
        class="grid grid-cols-1 sm:grid-cols-[1.2fr_1.8fr_1fr_1fr_1fr_auto] gap-3 sm:gap-4
               px-5 py-4 items-center"
        :class="i < filtered.length - 1 ? 'border-b' : ''"
        :style="i < filtered.length - 1 ? 'border-color:var(--app-border-2)' : ''"
      >
        <p class="font-mono font-bold text-sm" style="color:#c8a96e">
          {{ f.numero_facture ?? ('FAC-' + f.id) }}
        </p>

        <div class="min-w-0">
          <p class="font-medium text-sm truncate" style="color:var(--app-text)">
            {{ f.entreprise?.raison_sociale ?? '—' }}
          </p>
          <p class="text-xs truncate" style="color:var(--app-text-faint)">
            Contrat {{ f.contrat ? fmt(f.contrat.date_debut) + ' → ' + fmt(f.contrat.date_fin) : '—' }}
          </p>
        </div>

        <p class="text-sm" style="color:var(--app-text-muted)">{{ fmt(f.date_facture) }}</p>

        <p class="text-sm font-bold text-right" style="color:var(--app-text)">
          {{ Number(f.montant_total ?? 0).toLocaleString('fr-MA') }} DH
        </p>

        <div class="text-center">
          <span
            class="text-xs px-2.5 py-1 rounded-full font-semibold"
            :class="sc(f.statut).cls"
          >{{ sc(f.statut).label }}</span>
        </div>

        <!-- Row Actions -->
        <div class="flex items-center gap-2 shrink-0 justify-end">
          <button
            class="btn btn-outline btn-sm"
            title="Aperçu PDF"
            @click="openPreview(f)"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>

          <a
            :href="tokenUrl(`${getApiBase()}/api/factures/${f.id}/pdf`, 'download')"
            target="_blank"
            class="btn btn-gold btn-sm"
            title="Télécharger PDF"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </a>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-12 text-center" style="color:var(--app-text-faint)">
      <svg class="mx-auto mb-4 opacity-30" width="40" height="40" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2"/>
        <line x1="9" y1="9" x2="15" y2="9"/>
        <line x1="9" y1="13" x2="15" y2="13"/>
      </svg>
      <p class="font-medium mb-1">Aucune facture trouvée</p>
      <p class="text-sm">
        {{ search || filterStatut ? 'Modifiez vos filtres' : 'Les factures apparaîtront ici après les paiements' }}
      </p>
    </div>


    <!-- ════ PDF Preview Modal ════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showPreview && previewFacture"
        class="fixed inset-0 z-300 flex flex-col"
        style="background:rgba(0,0,0,0.92)"
      >
        <!-- Modal header -->
        <div
          class="flex items-center justify-between px-5 py-3 shrink-0"
          style="background:rgba(0,0,0,0.6);border-bottom:1px solid rgba(255,255,255,0.1)"
        >
          <div class="flex items-center gap-3">
            <span class="font-mono font-bold" style="color:#c8a96e">
              {{ previewFacture.numero_facture ?? ('FAC-' + previewFacture.id) }}
            </span>
            <span class="text-sm text-white/60">
              {{ previewFacture.entreprise?.raison_sociale }}
            </span>
            <span
              class="text-xs px-2 py-0.5 rounded-full font-semibold"
              :class="sc(previewFacture.statut).cls"
            >{{ sc(previewFacture.statut).label }}</span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Download Action in Modal -->
            <a
              :href="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'download')"
              target="_blank"
              class="btn btn-gold btn-sm"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Télécharger
            </a>

            <!-- Close Action -->
            <button
              class="w-9 h-9 rounded-xl flex items-center justify-center text-white transition-colors"
              style="background:rgba(255,255,255,0.1)"
              @click="closePreview"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- PDF iframe Display -->
        <div class="flex-1 relative">
          <!-- Loading overlay for the iframe -->
          <div
            v-if="previewLoading"
            class="absolute inset-0 flex items-center justify-center"
            style="background:rgba(0,0,0,0.5);z-index:1"
          >
            <div class="text-center text-white">
              <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto mb-3"/>
              <p class="text-sm">Chargement du PDF...</p>
            </div>
          </div>

          <iframe
            :src="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'preview')"
            class="w-full h-full"
            style="border:none;display:block"
            @load="previewLoading = false"
          />
        </div>
      </div>
    </Teleport>

  </div>
</template>