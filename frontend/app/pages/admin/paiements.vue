<!-- pages/admin/paiements.vue -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function getToken(): string {
  if (!import.meta.client) return ''
  try {
    return JSON.parse(localStorage.getItem((useRuntimeConfig().public.authStorageKey as string) ?? 'app_auth') ?? '{}')?.token ?? ''
  } catch { return '' }
}

function authHeaders(): Record<string, string> {
  const token = getToken()
  return token ? { Authorization: `Bearer ${token}` } : {}
}

function tokenUrl(url: string, mode: 'preview' | 'download' = 'preview'): string {
  const token = getToken()
  if (!token) return url
  return `${url}?token=${encodeURIComponent(token)}&mode=${mode}`
}

const contrats          = ref<any[]>([])
const paiements         = ref<any[]>([])
const summary           = ref<any>(null)
const selectedId        = ref<number | null>(null)
const loading           = ref(true)
const loadingPayments   = ref(false)
const showModal         = ref(false)
const saving            = ref(false)

const previewFacture    = ref<any>(null)
const showPdfPreview    = ref(false)
const pdfPreviewLoading = ref(false)

const form = reactive({
  montant:       '',
  date_paiement: new Date().toISOString().split('T')[0],
  mode_paiement: 'virement',
  note:          '',
})

const modeOptions = ['virement', 'espèces', 'chèque', 'carte bancaire', 'autre']

// ── Balance guard ─────────────────────────────────────────
// Mirrors the backend's remainingBalance() check so the domiciliataire
// gets instant feedback instead of a round-trip 422. The backend remains
// the source of truth — this is a UX convenience, not the real guard.
const remainingBalance = computed<number>(() => summary.value?.restant ?? Infinity)

const montantExceedsBalance = computed<boolean>(() => {
  const v = Number(form.montant)
  return Number.isFinite(v) && v > remainingBalance.value + 0.01
})

async function loadContrats(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/contrats`,
      { headers: authHeaders() }
    )
    contrats.value = (res.data ?? []).filter(c => ['active', 'draft'].includes(c.statut))
  } catch {} finally {
    loading.value = false
  }
}

async function selectContrat(id: number): Promise<void> {
  selectedId.value      = id
  loadingPayments.value = true
  paiements.value       = []
  summary.value         = null
  try {
    const [pRes, sRes] = await Promise.all([
      $fetch<{ success: boolean; data: any[] }>(`${getApiBase()}/api/contrats/${id}/paiements`, { headers: authHeaders() }),
      $fetch<{ success: boolean; data: any }>(`${getApiBase()}/api/contrats/${id}/paiements/summary`, { headers: authHeaders() }),
    ])
    paiements.value = pRes.data ?? []
    summary.value   = sRes.data
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement paiements')
  } finally {
    loadingPayments.value = false
  }
}

/**
 * Submit a new payment.
 *
 * Client-side balance check runs first (fast feedback, no request sent).
 * The backend re-validates independently — this guard is a convenience,
 * never the sole line of defence against over-payment.
 */
async function submitPaiement(): Promise<void> {
  if (!selectedId.value) return
  if (!form.montant || Number(form.montant) <= 0) return toastError?.('Montant invalide')
  if (!form.date_paiement) return toastError?.('Date requise')

  if (montantExceedsBalance.value) {
    toastError?.(
      `Le montant dépasse le solde restant (${remainingBalance.value.toLocaleString('fr-MA')} DH).`
    )
    return
  }

  saving.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${selectedId.value}/paiements`,
      {
        method: 'POST', headers: authHeaders(),
        body: {
          montant:       Number(form.montant),
          date_paiement: form.date_paiement,
          mode_paiement: form.mode_paiement,
          note:          form.note || null,
        },
      }
    )
    paiements.value.unshift(res.data)
    showModal.value = false
    success('Paiement enregistré ✓')
    await selectContrat(selectedId.value)
    Object.assign(form, { montant: '', mode_paiement: 'virement', note: '' })
  } catch (e: any) {
    // Backend rejection (422 over-payment or otherwise) surfaces here too,
    // covering any case the client-side check might have missed.
    toastError?.(e?.data?.message ?? 'Erreur enregistrement')
  } finally {
    saving.value = false
  }
}

const selectedContrat = computed(() => contrats.value.find(c => c.id === selectedId.value))

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

function openFacturePreview(facture: any) {
  previewFacture.value    = facture
  showPdfPreview.value    = true
  pdfPreviewLoading.value = true
}

function closeFacturePreview(): void {
  showPdfPreview.value    = false
  previewFacture.value    = null
  pdfPreviewLoading.value = false
}

const statutColor: Record<string, string> = {
  draft:  'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
}

onMounted(loadContrats)
</script>

<template>
  <div class="space-y-5 animate-fade-up">
    <div>
      <h1 class="font-serif text-2xl">Paiements <em class="text-gold italic">&amp; Facturation</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Suivi des paiements par contrat</p>
    </div>

    <div>
      <button class="btn btn-gold btn-md" @click="$router.push('/admin/factures')">&larr; All Factures</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Contracts list -->
      <div class="card p-4 space-y-2">
        <p class="text-xs uppercase text-gold tracking-widest font-bold mb-3">Contrats</p>
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-12 bg-white/5 rounded animate-pulse"/>
        </div>
        <div v-else-if="contrats.length" class="space-y-1">
          <button v-for="c in contrats" :key="c.id"
                  class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition"
                  :class="selectedId === c.id
                    ? 'bg-gold/15 border border-gold/30'
                    : 'hover:bg-white/5 border border-transparent'"
                  @click="selectContrat(c.id)">
            <p class="font-medium truncate">{{ c.entreprise?.raison_sociale ?? `#${c.id}` }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span class="text-xs px-1.5 py-0.5 rounded-full" :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'">
                {{ c.statut }}
              </span>
              <span class="text-xs text-app-text/40">{{ c.prix_total ?? 0 }} DH</span>
            </div>
          </button>
        </div>
        <p v-else class="text-sm text-app-text/40 text-center py-4">Aucun contrat actif</p>
      </div>

      <!-- Payment detail -->
      <div class="lg:col-span-2 space-y-4">
        <div v-if="!selectedId" class="card p-10 text-center text-app-text/40">
          <p class="text-3xl mb-2">👈</p><p>Sélectionnez un contrat</p>
        </div>

        <template v-else>
          <div v-if="summary" class="card p-4 space-y-3">
            <div class="flex items-center justify-between">
              <p class="font-semibold">{{ selectedContrat?.entreprise?.raison_sociale }}</p>
              <button
                class="btn btn-gold btn-sm"
                :disabled="summary.restant <= 0"
                :title="summary.restant <= 0 ? 'Contrat déjà soldé' : ''"
                @click="showModal = true"
              >
                + Enregistrer un paiement
              </button>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
              <div class="rounded-xl bg-white/5 p-3">
                <p class="text-xs text-app-text/40 mb-1">Total contrat</p>
                <p class="font-serif text-xl text-gold">{{ summary.prix_total }} DH</p>
              </div>
              <div class="rounded-xl bg-green-500/10 p-3">
                <p class="text-xs text-app-text/40 mb-1">Payé</p>
                <p class="font-serif text-xl text-green-400">{{ summary.total_paye }} DH</p>
              </div>
              <div class="rounded-xl bg-red-500/10 p-3">
                <p class="text-xs text-app-text/40 mb-1">Restant</p>
                <p class="font-serif text-xl text-red-400">{{ summary.restant }} DH</p>
              </div>
            </div>
            <div class="space-y-1">
              <div class="flex justify-between text-xs text-app-text/40">
                <span>Progression</span><span>{{ summary.pourcentage }}%</span>
              </div>
              <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                <div class="h-full rounded-full bg-gold transition-all duration-500"
                     :style="`width:${summary.pourcentage}%`"/>
              </div>
            </div>
            <p v-if="summary.restant <= 0" class="text-xs text-green-400 text-center">
              ✓ Ce contrat est entièrement soldé.
            </p>
          </div>

          <div class="card p-4 space-y-3">
            <p class="text-xs uppercase text-gold tracking-widest font-bold">Historique</p>
            <div v-if="loadingPayments" class="space-y-2">
              <div v-for="i in 2" :key="i" class="h-12 bg-white/5 rounded animate-pulse"/>
            </div>
            <div v-else-if="paiements.length" class="space-y-2">
              <div v-for="p in paiements" :key="p.id"
                   class="flex items-center justify-between p-3 rounded-xl gap-3 flex-wrap"
                   style="background:var(--app-surface-2);border:1px solid var(--app-border)">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-green-400">+{{ Number(p.montant).toLocaleString('fr-MA') }} DH</p>
                  <p class="text-xs mt-0.5" style="color:var(--app-text-muted)">
                    {{ p.mode_paiement }} · {{ formatDate(p.date_paiement) }}
                  </p>
                  <p v-if="p.facture?.numero_facture" class="text-xs mt-0.5 font-mono" style="color:#c8a96e">
                    {{ p.facture.numero_facture }}
                  </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-xs text-green-400 bg-green-400/10 px-2 py-0.5 rounded-full font-semibold">Payé</span>
                  <button v-if="p.facture?.id" class="btn btn-outline btn-sm" title="Aperçu facture"
                          @click="openFacturePreview(p.facture)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>
                  <a v-if="p.facture?.id"
                     :href="tokenUrl(`${getApiBase()}/api/factures/${p.facture.id}/pdf`, 'download')"
                     target="_blank" class="btn btn-gold btn-sm" title="Télécharger facture">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                      <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                  </a>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-app-text/40 text-center py-4">Aucun paiement enregistré</p>
          </div>
        </template>
      </div>
    </div>

    <!-- New payment modal -->
    <div v-if="showModal" class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
         @click.self="showModal = false">
      <div class="card w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">Enregistrer un paiement</h2>
          <button class="text-app-text/40 hover:text-white text-xl" @click="showModal = false">✕</button>
        </div>
        <p class="text-sm text-app-text/50">Contrat : <b>{{ selectedContrat?.entreprise?.raison_sociale }}</b></p>

        <!-- Remaining balance banner — sets clear expectations before typing -->
        <div class="rounded-xl px-3 py-2 text-sm"
             style="background:rgba(200,169,110,0.08);border:1px solid rgba(200,169,110,0.2);color:#c8a96e">
          Solde restant : <b>{{ remainingBalance.toLocaleString('fr-MA') }} DH</b>
        </div>

        <div class="space-y-3">
          <div>
            <label class="f-label">Montant (DH) *</label>
            <input
              v-model="form.montant" class="f-input" type="number" min="0" step="0.01"
              :max="Number.isFinite(remainingBalance) ? remainingBalance : undefined"
              placeholder="0.00"
            />
            <p v-if="montantExceedsBalance" class="text-xs text-red-400 mt-1">
              ⚠ Le montant dépasse le solde restant ({{ remainingBalance.toLocaleString('fr-MA') }} DH).
            </p>
          </div>
          <div>
            <label class="f-label">Date du paiement *</label>
            <input v-model="form.date_paiement" class="f-input" type="date"/>
          </div>
          <div>
            <label class="f-label">Mode de paiement *</label>
            <select v-model="form.mode_paiement" class="f-input">
              <option v-for="m in modeOptions" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>
          <div>
            <label class="f-label">Note (optionnel)</label>
            <input v-model="form.note" class="f-input" placeholder="Ex: Acompte premier trimestre..."/>
          </div>
        </div>
        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showModal = false">Annuler</button>
          <button
            class="btn btn-gold btn-md"
            :disabled="saving || montantExceedsBalance || !form.montant"
            @click="submitPaiement"
          >
            {{ saving ? 'Enregistrement...' : '💳 Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Facture PDF preview modal -->
    <Teleport to="body">
      <div v-if="showPdfPreview && previewFacture" class="fixed inset-0 z-300 flex flex-col"
           style="background:rgba(0,0,0,0.92)">
        <div class="flex items-center justify-between px-5 py-3 shrink-0"
             style="background:rgba(0,0,0,0.6);border-bottom:1px solid rgba(255,255,255,0.1)">
          <div class="min-w-0">
            <p class="font-mono font-bold truncate" style="color:#c8a96e">
              {{ previewFacture.numero_facture ?? ('FAC-' + previewFacture.id) }}
            </p>
            <p class="text-xs text-white/60">Aperçu facture</p>
          </div>
          <div class="flex items-center gap-2">
            <a :href="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'download')"
               target="_blank" class="btn btn-gold btn-sm">
              Télécharger
            </a>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                    style="background:rgba(255,255,255,0.1)" @click="closeFacturePreview">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="flex-1 relative">
          <div v-if="pdfPreviewLoading" class="absolute inset-0 flex items-center justify-center"
               style="background:rgba(0,0,0,0.5);z-index:1">
            <div class="text-center text-white">
              <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto mb-3"/>
              <p class="text-sm">Chargement du PDF...</p>
            </div>
          </div>
          <iframe :src="tokenUrl(`${getApiBase()}/api/factures/${previewFacture.id}/pdf`, 'preview')"
                  class="w-full h-full" style="border:none;display:block" @load="pdfPreviewLoading = false" />
        </div>
      </div>
    </Teleport>

  </div>
</template>
