<!-- ============================================================
  pages/admin/contrats.vue (liste)
  Liste des contrats avec actions state machine :
  - Voir (aperçu HTML rapide, sans DomPDF, rendu via srcdoc — pas de
    navigation ni de fenêtre plein écran incontrôlée)
  - Générer PDF (draft)
  - Activer (upload PDF signé → active)
  - Résilier (active → terminated)
============================================================ -->
<script setup lang="ts">

import ContratPreviewModal from '~/components/ContratPreviewModal.vue' // Add this line!

import { useContractStore } from '~/stores/contrat'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()
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

// ── State ─────────────────────────────────────────────────
const items          = ref<any[]>([])
const loading        = ref(true)
const q              = ref('')
const activating     = ref<number | null>(null)
const showActivate   = ref(false)
const activateId     = ref<number | null>(null)
const signedFile     = ref<File | null>(null)

// FEATURE: shared contract preview modal. Fetches the HTML preview via
// openUrl() and renders it through <iframe srcdoc> — see
// components/ContratPreviewModal.vue. Same component is used by the
// client detail page and the wizard's live preview.
// Plain ref() — do NOT type this with InstanceType<typeof import(...)>,
// that syntax breaks the build.
const pdfPreview = ref()

// ── Chargement ────────────────────────────────────────────
async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/contrats`,
      { headers: authHeaders() }
    )
    items.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!q.value.trim()) return items.value
  const term = q.value.toLowerCase()
  return items.value.filter(c =>
    c.entreprise?.raison_sociale?.toLowerCase().includes(term) ||
    c.statut?.toLowerCase().includes(term)
  )
})

/**
 * Opens the fast HTML-only preview via the shared component —
 */
function viewPdf(contrat: any): void {
  if (!pdfPreview.value || typeof pdfPreview.value.openLive !== 'function') return

  pdfPreview.value.openLive({
    titreContrat: contrat.titre_contrat,
    instruction_no: contrat.instruction_no,
    duree_mois: contrat.duree_mois,
    date_debut: contrat.date_debut,
    date_fin: contrat.date_fin,
    date_signature: contrat.date_signature,
    redevanceMensuelle: contrat.prix_mensuel,
    redevanceAnnuelle: contrat.prix_total,
    mode_paiement: contrat.mode_paiement,
    caution: contrat.caution,
    ville_signature: contrat.ville_signature,
    
    // Entreprise / Client details
    societe: contrat.entreprise?.raison_sociale,
    forme_juridique: contrat.entreprise?.forme_juridique,
    ville_client: contrat.entreprise?.ville,
    gerantNom: contrat.entreprise?.representant?.nom_complet || contrat.entreprise?.client_nom,
    gerantCIN: contrat.entreprise?.representant?.cin,
    tel: contrat.entreprise?.representant?.telephone,
    email: contrat.entreprise?.representant?.email,
    adressePerso: contrat.entreprise?.representant?.adresse,
    
    // Articles and structural details
    articles: contrat.articles || [],
  }, contrat.titre_contrat ?? `Contrat #${contrat.id}`)
}

// ── Télécharger PDF draft (real generated file) ────────────
async function downloadPdf(contrat: any): Promise<void> {
  if (!contrat.pdf_path) {
    const res = await $fetch<{ success: boolean; data: { url: string } }>(
      `${getApiBase()}/api/contrats/${contrat.id}/pdf`,
      { method: 'POST', headers: authHeaders() }
    )
    contrat.pdf_path = res.data.url
  }
  const url  = `${getApiBase()}/storage/${contrat.pdf_path}`
  const resp = await fetch(url)
  const blob = await resp.blob()
  const a    = document.createElement('a')
  a.href     = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
  a.download = `contrat_${contrat.id}.pdf`
  document.body.appendChild(a); a.click(); document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(a.href), 3000)
}

// ── Activer (upload PDF signé) ─────────────────────────────
function openActivate(id: number): void {
  activateId.value = id
  signedFile.value = null
  showActivate.value = true
}

function onSignedFileChange(e: Event): void {
  const input = e.target as HTMLInputElement
  signedFile.value = input.files?.[0] ?? null
}

async function submitActivate(): Promise<void> {
  if (!activateId.value || !signedFile.value) {
    toastError?.('Sélectionnez le PDF signé')
    return
  }
  activating.value = activateId.value
  try {
    const fd = new FormData()
    fd.append('signed_pdf', signedFile.value)
    await fetch(`${getApiBase()}/api/contrats/${activateId.value}/activate`, {
      method: 'POST',
      headers: authHeaders(),
      body: fd,
    })
    success('Contrat activé ✓')
    showActivate.value = false
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur activation')
  } finally {
    activating.value = null
  }
}

// ── Résilier ──────────────────────────────────────────────
async function terminate(id: number): Promise<void> {
  if (!confirm('Résilier ce contrat ? Cette action est irréversible.')) return
  try {
    await $fetch(`${getApiBase()}/api/contrats/${id}/terminate`, {
      method: 'POST',
      headers: authHeaders(),
    })
    success('Contrat résilié')
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur résiliation')
  }
}

// ── Couleurs statuts ──────────────────────────────────────
const statutColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

const statutLabel: Record<string, string> = {
  draft:      'Brouillon',
  active:     'Actif',
  expired:    'Expiré',
  terminated: 'Résilié',
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Contrats <em class="text-gold italic">de domiciliation</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ items.length }} contrat(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="router.push('/admin/contrat?new=1')">
        + Nouveau contrat
      </button>
    </div>

    <!-- Recherche -->
    <div class="card p-3">
      <input v-model="q" class="f-input" placeholder="Rechercher par entreprise, statut..." />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Liste -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="c in filtered" :key="c.id"
        class="card p-4 flex items-center justify-between flex-wrap gap-3"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-semibold">{{ c.titre_contrat ?? c.entreprise?.raison_sociale ?? `Contrat #${c.id}` }}</p>
            <span
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ statutLabel[c.statut] ?? c.statut }}
            </span>
          </div>
          <p class="text-xs text-app-text/50 mt-1">
            {{ c.date_debut ?? '-' }} → {{ c.date_fin ?? '-' }}
            <span v-if="c.prix_total" class="ml-2 text-gold font-medium">{{ c.prix_total }} DH</span>
          </p>
        </div>

        <!-- Actions selon statut -->
        <div class="flex gap-2 flex-wrap">

          <!-- Draft : voir + générer PDF + activer -->
          <template v-if="c.statut === 'draft'">
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button class="btn btn-gold btn-sm" @click="openActivate(c.id)">
              ✓ Activer (PDF signé)
            </button>
            <button class="btn btn-outline btn-sm" @click="router.push(`/admin/contrat?id=${c.id}`)">
              Éditer
            </button>
          </template>

          <!-- Active : voir + télécharger + résilier -->
          <template v-else-if="c.statut === 'active'">
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button class="btn btn-danger btn-sm" @click="terminate(c.id)">Résilier</button>
          </template>

          <!-- Expired / Terminated : lecture seule -->
          <template v-else>
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button v-if="c.pdf_path" class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <span class="text-xs text-app-text/40">Lecture seule</span>
          </template>

        </div>
      </div>
    </div>

    <!-- Vide -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>Aucun contrat trouvé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="router.push('/admin/contrat?new=1')">
        Créer le premier contrat
      </button>
    </div>

    <!-- Modal Activation (upload PDF signé) -->
    <div
      v-if="showActivate"
      class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
      @click.self="showActivate = false"
    >
      <div class="card w-full max-w-md p-6 space-y-4">
        <h2 class="font-serif text-xl">Activer le contrat</h2>
        <p class="text-sm text-app-text/50">
          Importez le PDF du contrat légalisé (signé par les deux parties).
          Le statut passera automatiquement à <b class="text-green-400">Actif</b>.
        </p>

        <div>
          <label class="f-label">PDF signé/légalisé *</label>
          <input class="f-input" type="file" accept=".pdf" @change="onSignedFileChange" />
          <p v-if="signedFile" class="text-xs text-green-400 mt-1">✓ {{ signedFile.name }}</p>
        </div>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showActivate = false">Annuler</button>
          <button
            class="btn btn-gold btn-md"
            :disabled="!signedFile || !!activating"
            @click="submitActivate"
          >
            {{ activating ? 'Activation...' : 'Activer le contrat' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Contract preview (shared component — srcdoc, no navigation) -->
    <ContratPreviewModal ref="pdfPreview" />
  </div>
</template>