<!-- ============================================================
  pages/admin/contrats.vue (liste)
  Liste des contrats avec actions state machine :
  - Voir (aperçu HTML rapide, sans DomPDF, rendu via srcdoc — pas de
    navigation ni de fenêtre plein écran incontrôlée)
  - Télécharger PDF (rendu à la demande, RIEN n'est stocké sur disque —
    voir downloadPdf() ci-dessous)
  - Activer (upload PDF signé → active)
  - Résilier (active → terminated)
  - Renouveler (active proche expiration / expired → nouveau brouillon,
    lié via renewed_from_id, repris DIRECTEMENT à l'étape 3 du wizard)

  FIXES in this revision:
  - Dates formatted (dd/mm/yyyy) instead of raw ISO timestamps.
  - Status badge/actions now use an EFFECTIVE status computed client-side
    (date_fin passed but backend cron hasn't flipped statut yet → still
    shown/treated as expired, not active).
  - Legalized contracts (scanned_pdf_path set) show the actual uploaded
    signed document, not the regenerated draft preview.
  - Activation/Renewal modals teleported to <body> so they always center
    on the viewport, regardless of page scroll position.
  - Renewal now routes into the wizard with &step=3&renewal=1, so the
    wizard skips Steps 1/2 entirely (client + domiciliataire already
    fixed) and opens straight at Step 3 with articles/order intact.
  - Search bar now also filters by date range via a statut dropdown +
    text query combined.
  - downloadPdf() no longer generates-and-saves a draft PDF before
    downloading it. It streams the live-rendered document directly from
    the backend, so nothing accumulates in storage on click. The
    download button is no longer hidden behind a "pdf_path exists"
    check, since that field is no longer populated for drafts.
============================================================ -->
<script setup lang="ts">

import ContratPreviewModal from '~/components/ContratPreviewModal.vue'

import { useContractStore } from '~/stores/contrat'
import { contratService } from '~/services/contrat.service'

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
    const raw = localStorage.getItem((useRuntimeConfig().public.authStorageKey as string) ?? 'app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── State ─────────────────────────────────────────────────
const items          = ref<any[]>([])
const loading        = ref(true)
const q              = ref('')
const sortBy         = ref<'recent' | 'oldest' | 'price_desc' | 'price_asc'>('recent')
const statutFilter = ref<'' | 'draft' | 'active' | 'active_soon' | 'expired' | 'terminated' | 'archived' | 'legalized' | 'not_legalized'>('')
const activating     = ref<number | null>(null)
const actionBusyId   = ref<number | null>(null)
const showActivate   = ref(false)
const activateId     = ref<number | null>(null)
const signedFile     = ref<File | null>(null)

// Renewal confirm modal
const showRenewModal = ref(false)
const renewTarget    = ref<any>(null)
const renewing       = ref(false)

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
      `${getApiBase()}/api/contrats?include_archived=1`,
      { headers: authHeaders() }
    )
    items.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

// ── Date formatting ──────────────────────────────────────────
// The API returns raw Carbon ISO timestamps (2026-08-13T00:00:00.000000Z).
// Every date shown in this page goes through this helper — never print
// c.date_debut / c.date_fin directly in the template.
function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/** Relative helper shown next to the date range, e.g. "il y a 3 jours" / "dans 12 jours". */
function fmtRelative(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (isNaN(d.getTime())) return ''
  const days = Math.round((d.getTime() - Date.now()) / 86400000)
  if (days === 0) return "aujourd'hui"
  if (days > 0) return `dans ${days} j`
  return `il y a ${Math.abs(days)} j`
}

// ── Effective status (client-side expiry correction) ──────────
// The backend cron (ExpireContractsCommand) only flips active → expired
// once a day at 01:00. A contract can sit "active" in the DB for up to
// 24h after date_fin has actually passed. Rather than show a wrong
// "Actif" badge until the next cron run, compute the effective status
// here: if statut is still 'active' but date_fin is in the past, treat
// it as expired everywhere in this page (badge, color, actions).
function effectiveStatut(c: any): string {
  if (c.archived_at) return 'archived'
  if (c.statut === 'active' && c.date_fin) {
    const end = new Date(c.date_fin)
    if (!isNaN(end.getTime()) && end.getTime() < Date.now()) {
      return 'expired'
    }
  }
  return c.statut
}

const filtered = computed(() => {
  let list = items.value

  if (statutFilter.value) {
    list = list.filter(c => {
      const eff = effectiveStatut(c)
      switch (statutFilter.value) {
        case 'draft':        return eff === 'draft'
        case 'active':       return eff === 'active'
        case 'active_soon': {
          if (eff !== 'active') return false
          const days = daysUntilExpiry(c)
          return days !== null && days <= 30 && days >= 0
        }
        case 'expired':      return eff === 'expired'
        case 'terminated':   return eff === 'terminated'
        case 'archived':     return !!c.archived_at
        case 'legalized':    return !!c.scanned_pdf_path
        case 'not_legalized':return !c.scanned_pdf_path
        default:              return true
      }
    })
  }

  if (q.value.trim()) {
    const term = q.value.toLowerCase()
    list = list.filter(c =>
      c.entreprise?.raison_sociale?.toLowerCase().includes(term) ||
      c.titre_contrat?.toLowerCase().includes(term) ||
      effectiveStatut(c).toLowerCase().includes(term)
    )
  }

  list = [...list].sort((a, b) => {
    if (sortBy.value === 'oldest') {
      return new Date(a.created_at ?? a.date_debut ?? 0).getTime() - new Date(b.created_at ?? b.date_debut ?? 0).getTime()
    }
    if (sortBy.value === 'price_desc') {
      return Number(b.prix_total ?? 0) - Number(a.prix_total ?? 0)
    }
    if (sortBy.value === 'price_asc') {
      return Number(a.prix_total ?? 0) - Number(b.prix_total ?? 0)
    }
    return new Date(b.created_at ?? b.date_debut ?? 0).getTime() - new Date(a.created_at ?? a.date_debut ?? 0).getTime()
  })

  return list
})

/**
 * Opens the fast HTML-only preview via the shared component.
 *
 * FIX: if the contract is legalized (scanned_pdf_path set), open the
 * actual uploaded signed document instead of the regenerated draft —
 * the draft preview is a live re-render of current DB state and can
 * drift from what was actually signed on paper.
 */
function viewPdf(contrat: any): void {
  if (contrat.scanned_pdf_path) {
    viewScannedPdf(contrat)
    return
  }
  if (!pdfPreview.value || typeof pdfPreview.value.openLive !== 'function') {
    toastError?.("L'aperçu n'est pas disponible pour ce contrat.")
    return
  }
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

/**
 * Opens the actual scanned/legalized PDF the domiciliataire uploaded
 * during activation — this is the real, legally signed document, and
 * takes priority over the live-rendered draft once it exists.
 */
function viewScannedPdf(contrat: any): void {
  if (!contrat.scanned_pdf_path) {
    toastError?.('Aucun document signé trouvé pour ce contrat.')
    return
  }
  const url = `${getApiBase()}/storage/${contrat.scanned_pdf_path}`
  window.open(url, '_blank', 'noopener')
}

// ── Télécharger PDF (rendu à la demande — RIEN n'est stocké) ──────────
// Legalized contracts download the real signed scan (already on disk,
// uploaded once during activation).
// Every other contract is streamed straight from streamPdf() with
// ?mode=download: the backend renders it on the fly from current DB
// state and returns the bytes directly — no intermediate save-to-disk
// step, no accumulating "contrat_123_20260101_113000.pdf" files.
function downloadPdf(contrat: any): void {
  if (contrat.scanned_pdf_path) {
    viewScannedPdf(contrat)
    return
  }

  const url = contratService.streamPdfUrl(String(contrat.id), 'download')
  const a = document.createElement('a')
  a.href = url
  a.download = `contrat_${contrat.id}.pdf`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
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

// ── Renouvellement ──────────────────────────────────────────

function replaceContrat(updated: any): void {
  const index = items.value.findIndex((item) => item.id === updated.id)
  if (index === -1) {
    items.value.unshift(updated)
    return
  }
  items.value.splice(index, 1, { ...items.value[index], ...updated })
}

async function archiveContract(c: any): Promise<void> {
  if (!confirm('Archiver ce contrat ?')) return
  actionBusyId.value = c.id
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${c.id}/archive`,
      { method: 'POST', headers: authHeaders() }
    )
    replaceContrat(res.data)
    success('Contrat archivÃ©')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur archivage')
  } finally {
    actionBusyId.value = null
  }
}

async function restoreContract(c: any): Promise<void> {
  actionBusyId.value = c.id
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${c.id}/restore`,
      { method: 'POST', headers: authHeaders() }
    )
    replaceContrat(res.data)
    success('Contrat restaurÃ©')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur restauration')
  } finally {
    actionBusyId.value = null
  }
}

/** True if this contract already has a draft or active renewal chained to it. */
function hasOpenRenewal(c: any): boolean {
  return Array.isArray(c.renewals) && c.renewals.some((r: any) => ['draft', 'active'].includes(r.statut))
}

/** The open renewal draft's id, if one exists — used to jump straight into it. */
function openRenewalId(c: any): number | null {
  const r = (c.renewals ?? []).find((r: any) => ['draft', 'active'].includes(r.statut))
  return r?.id ?? null
}

/** Days remaining until date_fin (negative if already past, null if no date_fin). */
function daysUntilExpiry(c: any): number | null {
  if (!c.date_fin) return null
  const end = new Date(c.date_fin)
  if (isNaN(end.getTime())) return null
  return Math.ceil((end.getTime() - Date.now()) / 86400000)
}

/** Contracts eligible to show the "Renouveler" action: expired, or active within 30 days of date_fin. */
function isRenewable(c: any): boolean {
  const eff = effectiveStatut(c)
  if (eff === 'expired') return true
  if (eff === 'active') {
    const days = daysUntilExpiry(c)
    return days !== null && days <= 30
  }
  return false
}

function openRenewConfirm(c: any): void {
  renewTarget.value = c
  showRenewModal.value = true
}

/** Preview of the new contiguous period the renewal draft will start with. */
function newPeriodPreview(c: any): { start: string; end: string } {
  if (!c.date_fin) return { start: '—', end: '—' }
  const start = new Date(c.date_fin)
  start.setDate(start.getDate() + 1)
  const end = new Date(start)
  if (c.duree_mois) {
    end.setMonth(end.getMonth() + Number(c.duree_mois))
    end.setDate(end.getDate() - 1)
  }
  return {
    start: start.toLocaleDateString('fr-FR'),
    end: c.duree_mois ? end.toLocaleDateString('fr-FR') : '—',
  }
}

/**
 * Confirms the renewal, creates the new draft (all pricing/duration/
 * articles carried over server-side by Contrat::renew()), then jumps
 * straight into the wizard at Step 3.
 *
 * &step=3&renewal=1 tells contrat.vue to:
 *   - load the draft's existing data (already has entreprise/articles set)
 *   - skip Step 1/2 entirely, since client + domiciliataire are fixed
 *   - open directly on Step 3 so the domiciliataire only reviews/adjusts
 *     articles + financial terms before activating
 */
async function confirmRenew(): Promise<void> {
  if (!renewTarget.value) return
  renewing.value = true
  try {
    const res = await contratService.renew(String(renewTarget.value.id))
    success('Brouillon de renouvellement créé ✓')
    showRenewModal.value = false
    router.push(`/admin/contrat?id=${res.data.id}&step=3&renewal=1`)
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors du renouvellement')
  } finally {
    renewing.value = false
  }
}

// ── Couleurs statuts (basées sur le statut EFFECTIF, pas brut) ──
const statutColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
  archived:   'text-slate-300 bg-slate-400/10',
}

const statutLabel: Record<string, string> = {
  draft:      'Brouillon',
  active:     'Actif',
  archived:   'Archive',
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
        <p class="text-app-text/50 text-sm mt-1">{{ filtered.length }} / {{ items.length }} contrat(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="router.push('/admin/contrat?new=1')">
        + Nouveau contrat
      </button>
    </div>

    <!-- Recherche + filtre statut -->
   <div class="card p-4 rounded-xl border border-[var(--app-border,#212936)] bg-[var(--app-card-bg,#131822)] space-y-3">
  <!-- Top Controls Row -->
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
        v-model="q"
        type="text"
        class="w-full pl-9 pr-3 py-2 bg-[var(--app-bg,#0B0E14)] border border-[var(--app-border,#212936)] rounded-lg text-sm text-[var(--app-text,#FFF)] placeholder:text-[var(--app-text-faint,#8A94A6)] focus:outline-none focus:border-[#E5C158] transition"
        placeholder="Rechercher par entreprise, titre, statut..."
      />
    </div>

    <!-- Status Pill Filter Chips (Replaces Dropdown) -->
    <div class="flex items-center gap-1.5 flex-wrap">
      <span class="text-xs font-medium text-[var(--app-text-faint,#8A94A6)] mr-1">Statut:</span>
      
      <button 
        @click="statutFilter = ''"
        :class="statutFilter === '' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Tous
      </button>

      <button 
        @click="statutFilter = 'draft'"
        :class="statutFilter === 'draft' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Brouillon
      </button>

      <button 
        @click="statutFilter = 'active'"
        :class="statutFilter === 'active' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        ✓ Actif
      </button>

      <button 
        @click="statutFilter = 'expired'"
        :class="statutFilter === 'expired' ? 'bg-[#EF4444]/15 border-[#EF4444] text-[#EF4444] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#EF4444]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Expiré
      </button>

      <button 
        @click="statutFilter = 'terminated'"
        :class="statutFilter === 'terminated' ? 'bg-[#E5C158]/15 border-[#E5C158] text-[#E5C158] font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-[#E5C158]/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Resilies
      </button>
      <button
        @click="statutFilter = 'archived'"
        :class="statutFilter === 'archived' ? 'bg-slate-400/15 border-slate-400 text-slate-200 font-semibold' : 'border-[var(--app-border,#212936)] text-[var(--app-text-faint,#8A94A6)] hover:border-slate-400/50 hover:text-white'"
        class="px-3 py-1.5 rounded-full border text-xs transition duration-150"
      >
        Archives
      </button>
    </div>

    <!-- Sort Dropdown (Right-Aligned) -->
    <div class="relative ml-auto">
      <select 
        v-model="sortBy" 
        class="appearance-none bg-[var(--app-bg,#0B0E14)] border border-[var(--app-border,#212936)] rounded-lg pl-3 pr-8 py-2 text-xs text-[var(--app-text-faint,#8A94A6)] focus:outline-none focus:border-[#E5C158] cursor-pointer"
      >
        <option value="recent">Plus récent</option>
        <option value="oldest">Plus ancien</option>
        <option value="price_desc">Montant ↓</option>
        <option value="price_asc">Montant ↑</option>
      </select>
      <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-[var(--app-text-faint,#8A94A6)]" width="10" height="6" viewBox="0 0 10 6" fill="none">
        <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5"/>
      </svg>
    </div>

  </div>
</div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Liste -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="c in filtered" :key="c.id"
        class="card p-4 flex items-center justify-between flex-wrap gap-3"
        :class="effectiveStatut(c) === 'expired' && !hasOpenRenewal(c) ? 'border-l-4' : ''"
        :style="effectiveStatut(c) === 'expired' && !hasOpenRenewal(c) ? 'border-left-color:#f59e0b' : ''"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-semibold">{{ c.titre_contrat ?? c.entreprise?.raison_sociale ?? `Contrat #${c.id}` }}</p>
            <span
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="statutColor[effectiveStatut(c)] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ statutLabel[effectiveStatut(c)] ?? effectiveStatut(c) }}
            </span>
            <!-- Legalized badge -->
            <span
              v-if="c.scanned_pdf_path"
              class="text-xs px-2 py-0.5 rounded-full font-medium text-blue-300 bg-blue-400/10"
              title="PDF signé importé"
            >
              📎 Légalisé
            </span>
            <!-- Near-expiry nudge on active contracts -->
            <span
              v-if="effectiveStatut(c) === 'active' && daysUntilExpiry(c) !== null && daysUntilExpiry(c) <= 30 && daysUntilExpiry(c) >= 0"
              class="text-xs px-2 py-0.5 rounded-full font-medium text-yellow-400 bg-yellow-400/10"
            >
              Expire dans {{ daysUntilExpiry(c) }} j
            </span>
          </div>
          <p class="text-xs text-app-text/50 mt-1">
            {{ fmtDate(c.date_debut) }} → {{ fmtDate(c.date_fin) }}
            <span v-if="c.date_fin" class="text-app-text/30">({{ fmtRelative(c.date_fin) }})</span>
            <span v-if="c.prix_total" class="ml-2 text-gold font-medium">{{ c.prix_total }} DH</span>
          </p>
        </div>

        <!-- Actions selon statut EFFECTIF -->
        <div class="flex gap-2 flex-wrap">

          <!-- Draft : voir + télécharger + activer -->
          <template v-if="effectiveStatut(c) === 'draft'">
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button class="btn btn-gold btn-sm" @click="openActivate(c.id)">
              ✓ Activer (PDF signé)
            </button>
            <button class="btn btn-outline btn-sm" @click="router.push(`/admin/contrat?id=${c.id}`)">
              Éditer
            </button>
          </template>

          <!-- Active (réellement, date_fin non dépassée) : voir + télécharger + résilier + renouveler si proche expiration -->
          <template v-else-if="effectiveStatut(c) === 'active'">
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <template v-if="hasOpenRenewal(c)">
              <button class="btn btn-outline btn-sm" @click="router.push(`/admin/contrat?id=${openRenewalId(c)}&step=3&renewal=1`)">
                Renouvellement en cours →
              </button>
            </template>
            <button
              v-else-if="isRenewable(c)"
              class="btn btn-outline btn-sm"
              style="color:#c8a96e;border-color:rgba(200,169,110,0.4)"
              @click="openRenewConfirm(c)"
            >
              Renouveler
            </button>
            <button class="btn btn-danger btn-sm" @click="terminate(c.id)">Résilier</button>
          </template>

          <!-- Expired (réellement OU effectivement — date_fin dépassée même si le cron n'a pas encore tourné) -->
          <template v-else-if="effectiveStatut(c) === 'expired'">
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <template v-if="hasOpenRenewal(c)">
              <button class="btn btn-gold btn-sm" @click="router.push(`/admin/contrat?id=${openRenewalId(c)}&step=3&renewal=1`)">
                Renouvellement en cours →
              </button>
            </template>
            <button v-else class="btn btn-gold btn-sm" @click="openRenewConfirm(c)">
              ↻ Renouveler
            </button>
          </template>

          <!-- Terminated : lecture seule -->
          <template v-else>
            <button class="btn btn-outline btn-sm" @click="viewPdf(c)">👁 Aperçu</button>
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <span class="text-xs text-app-text/40">Lecture seule</span>
          </template>

          <button
            v-if="effectiveStatut(c) === 'archived'"
            class="btn btn-outline btn-sm"
            :disabled="actionBusyId === c.id"
            @click="restoreContract(c)"
          >
            Restaurer
          </button>
          <button
            v-else
            class="btn btn-outline btn-sm"
            :disabled="actionBusyId === c.id"
            @click="archiveContract(c)"
          >
            Archiver
          </button>

        </div>
      </div>
    </div>

    <!-- Vide -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p v-if="q || statutFilter">Aucun contrat ne correspond à votre recherche.</p>
      <p v-else>Aucun contrat trouvé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="router.push('/admin/contrat?new=1')">
        Créer le premier contrat
      </button>
    </div>

    <!-- Modal Activation (upload PDF signé) — teleported to center on viewport -->
    <Teleport to="body">
      <div
        v-if="showActivate"
        class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
        @click.self="showActivate = false"
      >
        <div class="card w-full max-w-md p-6 space-y-4">
          <h2 class="font-serif text-xl">Activer le contrat</h2>
          <p class="text-sm text-app-text/50">
            Importez le PDF du contrat légalisé (signé par les deux parties).
            Le statut passera automatiquement à <b class="text-green-400">Actif</b>,
            et ce PDF deviendra le document de référence affiché partout.
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
    </Teleport>

    <!-- Modal Confirmation Renouvellement — teleported to center on viewport -->
    <Teleport to="body">
      <div
        v-if="showRenewModal && renewTarget"
        class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
        @click.self="showRenewModal = false"
      >
        <div class="card w-full max-w-md p-6 space-y-4">
          <h2 class="font-serif text-xl">Renouveler le contrat</h2>

          <p class="text-sm text-app-text/70">
            Client : <b class="text-app-text">{{ renewTarget.entreprise?.raison_sociale ?? `#${renewTarget.id}` }}</b>
          </p>

          <div class="rounded-xl p-3 text-xs space-y-1" style="background:var(--app-surface-2);border:1px solid var(--app-border)">
            <p class="text-app-text/50">
              Ancienne période : <span class="text-app-text">{{ fmtDate(renewTarget.date_debut) }} → {{ fmtDate(renewTarget.date_fin) }}</span>
            </p>
            <p class="text-app-text/50">
              Nouvelle période : <span class="text-gold font-medium">
                {{ newPeriodPreview(renewTarget).start }} → {{ newPeriodPreview(renewTarget).end }}
              </span>
            </p>
          </div>

          <p class="text-xs text-app-text/50 leading-relaxed">
            Un nouveau brouillon sera créé avec les mêmes conditions (prix, durée,
            articles et leur ordre). Vous serez redirigé directement à l'étape 3
            du contrat pour vérifier/ajuster avant activation.
          </p>

          <div class="flex gap-3 justify-end">
            <button class="btn btn-outline btn-md" @click="showRenewModal = false">Annuler</button>
            <button class="btn btn-gold btn-md" :disabled="renewing" @click="confirmRenew">
              {{ renewing ? 'Création...' : 'Renouveler →' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Contract preview (shared component — srcdoc, no navigation) -->
    <ContratPreviewModal ref="pdfPreview" />
  </div>
</template>
