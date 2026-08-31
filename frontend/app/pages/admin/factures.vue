<!-- pages/admin/factures.vue -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()
const route = useRoute()

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

function getToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return ''
    return JSON.parse(raw)?.token ?? ''
  } catch { return '' }
}

// Builds a signed URL for PDF endpoints — these are opened directly by
// the browser (new tab / iframe / <a href>) so a Bearer header can't be
// attached; the token travels as a query param instead.
function tokenUrl(url: string, mode: 'preview' | 'download' = 'preview'): string {
  const token = getToken()
  if (!token) return url
  return `${url}?token=${encodeURIComponent(token)}&mode=${mode}`
}

const factures     = ref<any[]>([])
const loading      = ref(true)
const search       = ref('')
const filterStatut = ref(typeof route.query.statut === 'string' ? route.query.statut : '')

const previewFacture = ref<any>(null)
const showPreview    = ref(false)
const previewLoading = ref(false)
const actionBusyId   = ref<number | null>(null)

function openPreview(f: any) {
  previewFacture.value = f
  showPreview.value    = true
  previewLoading.value = true
}

function openFactureFromQuery(): void {
  const factureId = Number(route.query.facture_id)
  if (!factureId) return
  const facture = factures.value.find(f => Number(f.id) === factureId)
  if (facture) openPreview(facture)
}

function closePreview() {
  showPreview.value    = false
  previewFacture.value = null
}

async function fetchFactures(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/factures`,
      { headers: authHeaders(), query: { include_archived: 1, per_page: 100 } }
    )
    factures.value = res.data ?? []
    openFactureFromQuery()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement factures')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  let list = factures.value
  if (filterStatut.value === 'archived') {
    list = list.filter(f => f.archived_at)
  } else {
    list = list.filter(f => !f.archived_at)
    if (filterStatut.value) list = list.filter(f => displayStatut(f) === filterStatut.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(f =>
      f.numero_facture?.toLowerCase().includes(q) ||
      f.entreprise?.raison_sociale?.toLowerCase().includes(q) ||
      displayStatut(f).toLowerCase().includes(q)
    )
  }
  return list
})

const activeFactures = computed(() => factures.value.filter(f => !f.archived_at))
const archivedCount  = computed(() => factures.value.filter(f => f.archived_at).length)
const totalAmount    = computed(() => activeFactures.value.reduce((s, f) => s + Number(f.montant_total ?? 0), 0))
const paidAmount     = computed(() => activeFactures.value.reduce((s, f) => s + Number(f.total_paye ?? (displayStatut(f) === 'paid' ? f.montant_total : 0)), 0))
const pendingAmount  = computed(() => activeFactures.value.reduce((s, f) => s + Number(f.montant_restant ?? (displayStatut(f) === 'paid' ? 0 : f.montant_total)), 0))
const paidCount      = computed(() => activeFactures.value.filter(f => displayStatut(f) === 'paid').length)
const partialCount   = computed(() => activeFactures.value.filter(f => displayStatut(f) === 'partial').length)
const overdueCount   = computed(() => activeFactures.value.filter(f => displayStatut(f) === 'overdue').length)
const pendingCount   = computed(() => activeFactures.value.filter(f => ['unpaid', 'overdue', 'partial', 'pending'].includes(displayStatut(f))).length)

function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const statutCfg: Record<string, { cls: string; label: string }> = {
  paid:      { cls: 'text-green-400 bg-green-400/10',   label: 'Payée' },
  partial:   { cls: 'text-sky-400 bg-sky-400/10',       label: 'Partielle' },
  unpaid:    { cls: 'text-yellow-400 bg-yellow-400/10', label: 'Impayee' },
  overdue:   { cls: 'text-red-400 bg-red-400/10',       label: 'En retard' },
  pending:   { cls: 'text-yellow-400 bg-yellow-400/10', label: 'En attente' },
  cancelled: { cls: 'text-red-400 bg-red-400/10',       label: 'Annulée' },
  archived:  { cls: 'text-slate-300 bg-slate-400/10',   label: 'Archivée' },
}

function displayStatut(f: any): string {
  return f.archived_at ? 'archived' : (f.effective_statut ?? f.statut)
}

function sc(statut: string) {
  return statutCfg[statut] ?? { cls: 'text-gray-400 bg-gray-400/10', label: statut }
}

function replaceFacture(updated: any): void {
  const index = factures.value.findIndex(f => f.id === updated.id)
  if (index === -1) return
  factures.value.splice(index, 1, updated)
}

async function archiveFacture(f: any): Promise<void> {
  if (!confirm(`Archiver la facture ${f.numero_facture ?? ('FAC-' + f.id)} ?`)) return

  actionBusyId.value = f.id
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/factures/${f.id}/archive`,
      { method: 'POST', headers: authHeaders() }
    )
    replaceFacture(res.data)
    success('Facture archivée')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? "Erreur lors de l'archivage")
  } finally {
    actionBusyId.value = null
  }
}

async function restoreFacture(f: any): Promise<void> {
  actionBusyId.value = f.id
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/factures/${f.id}/restore`,
      { method: 'POST', headers: authHeaders() }
    )
    replaceFacture(res.data)
    success('Facture restaurée')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors de la restauration')
  } finally {
    actionBusyId.value = null
  }
}

async function deleteFacture(f: any): Promise<void> {
  if (!confirm(`Supprimer définitivement la facture ${f.numero_facture ?? ('FAC-' + f.id)} ?`)) return

  actionBusyId.value = f.id
  try {
    await $fetch(
      `${getApiBase()}/api/factures/${f.id}`,
      { method: 'DELETE', headers: authHeaders() }
    )
    factures.value = factures.value.filter(item => item.id !== f.id)
    if (previewFacture.value?.id === f.id) closePreview()
    success('Facture supprimée')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors de la suppression')
  } finally {
    actionBusyId.value = null
  }
}

watch(() => route.query.facture_id, openFactureFromQuery)
watch(() => route.query.statut, value => {
  filterStatut.value = typeof value === 'string' ? value : ''
})

onMounted(fetchFactures)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Factures <em class="italic" style="color:#c8a96e">&amp; Historique</em>
        </h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">
          {{ activeFactures.length }} facture(s) active(s) · {{ archivedCount }} archivée(s)
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif" style="color:#c8a96e">{{ totalAmount.toLocaleString('fr-MA') }} DH</p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">Total facturé</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-green-400">{{ paidAmount.toLocaleString('fr-MA') }} DH</p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">Payé ({{ paidCount }} facture{{ paidCount > 1 ? 's' : '' }})</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-yellow-400">{{ pendingAmount.toLocaleString('fr-MA') }} DH</p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">En attente ({{ pendingCount }})</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif" :class="overdueCount ? 'text-red-400' : 'text-sky-400'">{{ overdueCount }}</p>
        <p class="text-xs mt-1" style="color:var(--app-text-faint)">En retard - {{ partialCount }} partielle(s)</p>
      </div>
    </div>

    <div class="card p-4 rounded-xl border border-[var(--app-border,#212936)] bg-[var(--app-card-bg,#131822)] space-y-3">
  <!-- Controls Row -->
  <div class="flex flex-wrap items-center gap-3">
    
    <!-- Search Input -->
    <div class="relative flex-1 min-w-[220px]">
      <svg 
        class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
        width="15" height="15" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round"
        style="color: var(--app-text-faint, #8A94A6)"
      >
        <circle cx="11" cy="11" r="8"/>
        <path d="M21 21l-4.35-4.35"/>
      </svg>
      <input
        v-model="search"
        type="text"
        autocomplete="off"
        class="w-full pl-9 pr-3 py-2 bg-[var(--app-bg,#0B0E14)] border border-[var(--app-border,#212936)] rounded-lg text-sm text-[var(--app-text,#FFF)] placeholder:text-[var(--app-text-faint,#8A94A6)] focus:outline-none focus:border-[#E5C158] transition"
        placeholder="Rechercher par N° facture, entreprise..."
      />
    </div>

    <!-- Status Pill Filter Chips -->
    <div class="flex items-center gap-1.5 flex-wrap">
      <span class="text-xs font-medium text-[var(--app-text-faint,#8A94A6)] mr-1">Statut:</span>
      
      <button 
        @click="filterStatut = ''"
        :class="filterStatut === '' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Tous
      </button>

      <button 
        @click="filterStatut = 'paid'"
        :class="filterStatut === 'paid' ? 'bg-[#10B981]/15 border-[#10B981] text-[#10B981] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#10B981]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        ✓ Payées
      </button>

      <button 
        @click="filterStatut = 'pending'"
        :class="filterStatut === 'pending' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        En attente
      </button>

      <button
        @click="filterStatut = 'partial'"
        :class="filterStatut === 'partial' ? 'bg-sky-400/15 border-sky-400 text-sky-300 font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-sky-400/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Partielles
      </button>

      <button
        @click="filterStatut = 'overdue'"
        :class="filterStatut === 'overdue' ? 'bg-[#EF4444]/15 border-[#EF4444] text-[#EF4444] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#EF4444]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        En retard
      </button>

      <button 
        @click="filterStatut = 'cancelled'"
        :class="filterStatut === 'cancelled' ? 'bg-[#EF4444]/15 border-[#EF4444] text-[#EF4444] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#EF4444]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Annulées
      </button>

      <button
        @click="filterStatut = 'archived'"
        :class="filterStatut === 'archived' ? 'bg-slate-400/15 border-slate-300 text-slate-100 font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-slate-300/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Archivées
      </button>
    </div>

  </div>
</div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="card p-4 animate-pulse flex items-center gap-4">
        <div class="flex-1 space-y-2">
          <div class="h-3 w-1/3 rounded" style="background:var(--app-border)"/>
          <div class="h-3 w-1/2 rounded" style="background:var(--app-border)"/>
        </div>
        <div class="h-3 w-20 rounded" style="background:var(--app-border)"/>
      </div>
    </div>

    <div v-else-if="filtered.length" class="rounded-2xl overflow-hidden"
         style="background:var(--app-surface);border:1px solid var(--app-border-2)">
      <div class="hidden sm:grid gap-4 px-5 py-3 text-[11px] uppercase tracking-widest font-bold"
           style="grid-template-columns:1.2fr 1.8fr 1fr 1fr 1fr auto;color:var(--app-text-faint);border-bottom:1px solid var(--app-border-2)">
        <span>N° Facture</span><span>Entreprise</span><span>Date</span>
        <span class="text-right">Montant</span><span class="text-center">Statut</span><span></span>
      </div>

      <div v-for="(f, i) in filtered" :key="f.id"
           class="grid grid-cols-1 sm:grid-cols-[1.2fr_1.8fr_1fr_1fr_1fr_auto] gap-3 sm:gap-4 px-5 py-4 items-center"
           :class="i < filtered.length - 1 ? 'border-b' : ''"
           :style="i < filtered.length - 1 ? 'border-color:var(--app-border-2)' : ''">
        <p class="font-mono font-bold text-sm" style="color:#c8a96e">
          {{ f.numero_facture ?? ('FAC-' + f.id) }}
        </p>
        <div class="min-w-0">
          <p class="font-medium text-sm truncate" style="color:var(--app-text)">{{ f.entreprise?.raison_sociale ?? '—' }}</p>
          <p class="text-xs truncate" style="color:var(--app-text-faint)">
            Contrat {{ f.contrat ? fmt(f.contrat.date_debut) + ' → ' + fmt(f.contrat.date_fin) : '—' }}
          </p>
        </div>
        <p class="text-sm" style="color:var(--app-text-muted)">{{ fmt(f.date_facture) }}</p>
        <p class="text-sm font-bold text-right" style="color:var(--app-text)">
          {{ Number(f.montant_total ?? 0).toLocaleString('fr-MA') }} DH
          <span v-if="Number(f.montant_restant ?? 0) > 0" class="block text-[10px] font-normal" style="color:var(--app-text-faint)">
            Reste {{ Number(f.montant_restant ?? 0).toLocaleString('fr-MA') }} DH
          </span>
        </p>
        <div class="text-center">
          <span class="text-xs px-2.5 py-1 rounded-full font-semibold" :class="sc(displayStatut(f)).cls">
            {{ sc(displayStatut(f)).label }}
          </span>
        </div>
        <div class="flex items-center gap-2 shrink-0 justify-end">
          <button class="btn btn-outline btn-sm" title="Aperçu PDF" @click="openPreview(f)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
          <a :href="tokenUrl(`${getApiBase()}/api/factures/${f.id}/pdf`, 'download')"
             target="_blank" class="btn btn-gold btn-sm" title="Télécharger PDF">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </a>
          <button
            v-if="f.archived_at"
            class="btn btn-outline btn-sm"
            title="Restaurer"
            :disabled="actionBusyId === f.id"
            @click="restoreFacture(f)"
          >
            Restaurer
          </button>
          <button
            v-else
            class="btn btn-outline btn-sm"
            title="Archiver"
            :disabled="actionBusyId === f.id"
            @click="archiveFacture(f)"
          >
            Archiver
          </button>
          <button
            class="btn btn-danger btn-sm"
            title="Supprimer"
            :disabled="actionBusyId === f.id"
            @click="deleteFacture(f)"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <div v-else class="card p-12 text-center" style="color:var(--app-text-faint)">
      <svg class="mx-auto mb-4 opacity-30" width="40" height="40" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2"/>
        <line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/>
      </svg>
      <p class="font-medium mb-1">Aucune facture trouvée</p>
      <p class="text-sm">{{ search || filterStatut ? 'Modifiez vos filtres' : 'Les factures apparaîtront ici après les paiements' }}</p>
    </div>

    <!-- PDF Preview Modal -->
    <Teleport to="body">
      <div v-if="showPreview && previewFacture" class="fixed inset-0 z-300 flex flex-col"
           style="background:rgba(0,0,0,0.92)">
        <div class="flex items-center justify-between px-5 py-3 shrink-0"
             style="background:rgba(0,0,0,0.6);border-bottom:1px solid rgba(255,255,255,0.1)">
          <div class="flex items-center gap-3">
            <span class="font-mono font-bold" style="color:#c8a96e">
              {{ previewFacture.numero_facture ?? ('FAC-' + previewFacture.id) }}
            </span>
            <span class="text-sm text-white/60">{{ previewFacture.entreprise?.raison_sociale }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full font-semibold" :class="sc(displayStatut(previewFacture)).cls">
              {{ sc(displayStatut(previewFacture)).label }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <a :href="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'download')"
               target="_blank" class="btn btn-gold btn-sm">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Télécharger
            </a>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                    style="background:rgba(255,255,255,0.1)" @click="closePreview">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="flex-1 relative">
          <div v-if="previewLoading" class="absolute inset-0 flex items-center justify-center"
               style="background:rgba(0,0,0,0.5);z-index:1">
            <div class="text-center text-white">
              <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto mb-3"/>
              <p class="text-sm">Chargement du PDF...</p>
            </div>
          </div>
          <iframe :src="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'preview')"
                  class="w-full h-full" style="border:none;display:block" @load="previewLoading = false" />
        </div>
      </div>
    </Teleport>

  </div>
</template>
