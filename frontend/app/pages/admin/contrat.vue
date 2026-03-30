<!-- ============================================================
  pages/admin/contrat.vue
  Création et édition d'un contrat de domiciliation
  - Stepper 5 étapes : AST-FISC / Client / Durée / Articles / Récap
  - Articles chargés depuis la DB (GET /api/articles)
  - Clients chargés depuis la DB (GET /api/clients)
  - Autosave vers Laravel toutes les 1.2s après modification
  - Génération PDF via POST /api/contrats/{id}/pdf
  - storeToRefs pour la réactivité Pinia dans le template
============================================================ -->
<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, navigateTo } from '#app'
import { useContractStore } from '~/stores/contrat'
import { useArticlesStore } from '~/stores/articles'
import { useClientsStore }  from '~/stores/clients'
import type { ContratForm } from '~/types/contrat'
import { useContratValidation } from '~/composables/useContratValidation'
import { useContratDraft }      from '~/composables/useContratDraft'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

// ── Stores ────────────────────────────────────────────────
const route         = useRoute()
const contract      = useContractStore()
const articlesStore = useArticlesStore()
const clientsStore  = useClientsStore()
const { success, error: toastError } = useToast()

// storeToRefs — nécessaire pour que items soit réactif dans le template
// Sans ça, articleItems.length ne se met pas à jour après fetchAll()
const { items: articleItems, loading: articlesLoading } = storeToRefs(articlesStore)
const { items: clientItems }                             = storeToRefs(clientsStore)

// ── Stepper ───────────────────────────────────────────────
const step  = ref(0)
const steps = ['AST-FISC', 'Client', 'Durée', 'Articles', 'Récap']

// ── IDs courants ──────────────────────────────────────────
const currentContratId    = ref<string>('')
const selectedEntrepriseId = ref<number | null>(null)

// ── Autosave ──────────────────────────────────────────────
const autosaveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')
const autosaveAt    = ref<string>('')
const autosaveError = ref<string>('')
let autosaveTimer: ReturnType<typeof setTimeout> | null = null
const AUTOSAVE_DELAY = 1200  // ms

// ── Articles (sélection + édition inline) ─────────────────
const selectedIds  = ref<string[]>([])   // UUIDs des articles sélectionnés
const editingId    = ref<string | null>(null)
const editTitle    = ref<string>('')
const editBody     = ref<string>('')
const newArtTitle  = ref<string>('')
const newArtBody   = ref<string>('')
const savingArticle = ref(false)

// ── PDF ───────────────────────────────────────────────────
const showPreview = ref(false)
const pdfUrl      = ref<string>('')
const pdfLoading  = ref(false)

// ── Composables ───────────────────────────────────────────
const formTyped = contract.form as ContratForm
const { validateStep } = useContratValidation(formTyped)
const { saveDraft, loadDraft, clearDraft, lastSavedAt } = useContratDraft(
  formTyped,
  selectedIds,
  ref([])  // customArticles obsolète — tout est en DB
)

// ── Computed ──────────────────────────────────────────────

/** Articles sélectionnés dans l'ordre de sélection */
const selectedSorted = computed(() =>
  selectedIds.value
    .map(id => articleItems.value.find(a => a.id === id))
    .filter(Boolean) as typeof articleItems.value
)

// ── Helpers API ───────────────────────────────────────────
function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw    = localStorage.getItem('astfisc_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── Calcul durée depuis les dates ─────────────────────────
function monthsBetween(start: string, end: string): number {
  if (!start || !end) return 0
  const diff = new Date(end).getTime() - new Date(start).getTime()
  if (diff < 0) return 0
  return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24 * 30.44)))
}

function syncMonthsFromDates(): void {
  const m = monthsBetween(contract.form.dateDebut, contract.form.dateFin)
  if (m > 0) { contract.form.months = m; contract.syncFromMonths() }
}

// ── Articles CRUD ─────────────────────────────────────────

/** Sélectionne ou désélectionne un article */
function toggleArticle(id: string): void {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter(x => x !== id)
    : [...selectedIds.value, id]
}

/** Passe un article en mode édition inline */
function startEdit(a: { id: string; title: string; body: string }): void {
  editingId.value = a.id
  editTitle.value = a.title
  editBody.value  = a.body
}

function cancelEdit(): void {
  editingId.value = null
  editTitle.value = ''
  editBody.value  = ''
}

/** Sauvegarde les modifications d'un article en DB */
async function saveEdit(): Promise<void> {
  if (!editingId.value || !editTitle.value.trim()) return
  savingArticle.value = true
  try {
    await articlesStore.update(editingId.value, editTitle.value.trim(), editBody.value.trim(), true)
    success('Article mis à jour')
    cancelEdit()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur mise à jour article')
  } finally { savingArticle.value = false }
}

/** Supprime un article de la DB et désélectionne */
async function deleteArticle(id: string): Promise<void> {
  if (!confirm('Supprimer cet article définitivement ?')) return
  try {
    await articlesStore.remove(id)
    selectedIds.value = selectedIds.value.filter(x => x !== id)
    success('Article supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression article')
  }
}

/** Crée un nouvel article en DB et le sélectionne automatiquement */
async function addArticle(): Promise<void> {
  if (!newArtTitle.value.trim()) return
  savingArticle.value = true
  try {
    const created = await articlesStore.create(newArtTitle.value.trim(), newArtBody.value.trim())
    selectedIds.value.push(created.id)
    newArtTitle.value = ''
    newArtBody.value  = ''
    success('Article ajouté à la bibliothèque')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur création article')
  } finally { savingArticle.value = false }
}

// ── Sauvegarde contrat ────────────────────────────────────

// Délai de notification avant expiration (1, 3 ou 6 mois)
const notificationDelayMonths = ref<1 | 3 | 6>(1)

/** Construit le body de la requête Laravel */
function buildContratBody() {
  return {
    entreprise_id:             selectedEntrepriseId.value,
    date_debut:                contract.form.dateDebut  || null,
    date_fin:                  contract.form.dateFin    || null,
    duree_mois:                contract.form.months     || null,
    prix_mensuel:              contract.monthlyTotal    || null,
    prix_total:                contract.grandTotal      || null,
    statut:                    'draft',
    notification_delay_months: notificationDelayMonths.value,
    article_ids:               selectedIds.value,
  }
}

/** Sauvegarde le contrat (création ou mise à jour) */
async function saveAsDraft(silent = false): Promise<void> {
  if (!selectedEntrepriseId.value) {
    if (!silent) toastError?.('Sélectionnez une entreprise cliente (étape 2)')
    return
  }
  try {
    if (!silent) autosaveState.value = 'saving'
    const body = buildContratBody()

    if (!currentContratId.value) {
      // Création
      const res = await $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/contrats`,
        { method: 'POST', headers: authHeaders(), body }
      )
      currentContratId.value = String(res.data.id)
      await navigateTo(
        { path: '/admin/contrat', query: { id: currentContratId.value } },
        { replace: true }
      )
      if (!silent) success('Brouillon créé')
    } else {
      // Mise à jour
      await $fetch(
        `${getApiBase()}/api/contrats/${currentContratId.value}`,
        { method: 'PUT', headers: authHeaders(), body }
      )
      if (!silent) success('Brouillon mis à jour')
    }

    autosaveState.value = 'saved'
    autosaveAt.value    = new Date().toISOString()
    autosaveError.value = ''
  } catch (e: any) {
    autosaveState.value = 'error'
    autosaveError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur de sauvegarde'
    if (!silent) toastError?.(autosaveError.value)
  }
}

/** Planifie un autosave après inactivité */
function scheduleAutosave(): void {
  if (!selectedEntrepriseId.value) return
  if (autosaveTimer) clearTimeout(autosaveTimer)
  autosaveState.value = 'saving'
  autosaveTimer = setTimeout(() => saveAsDraft(true), AUTOSAVE_DELAY)
}

// ── PDF ───────────────────────────────────────────────────

/** Génère le PDF via Laravel et déclenche le téléchargement */
async function generateAndDownloadPdf(): Promise<void> {
  if (!currentContratId.value) {
    toastError?.("Sauvegardez d'abord le contrat avant de générer le PDF")
    return
  }
  pdfLoading.value = true
  try {
    // Étape 1 : demander à Laravel de générer le PDF et retourner l'URL
    const res = await $fetch<{ success: boolean; data: { url: string; pdf_path: string } }>(
      `${getApiBase()}/api/contrats/${currentContratId.value}/pdf`,
      { method: 'POST', headers: authHeaders() }
    )
    pdfUrl.value = res.data.url

    // Étape 2 : fetcher le PDF comme Blob pour forcer le téléchargement
    // (évite le problème de Content-Type incorrect en ouvrant l'URL directement)
    const response = await fetch(res.data.url)
    const blob     = await response.blob()

    // Créer un object URL temporaire avec le bon type MIME
    const blobUrl = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))

    const a       = document.createElement('a')
    a.href        = blobUrl
    a.download    = `contrat_${currentContratId.value}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)

    // Libérer la mémoire
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)

    success('PDF généré et téléchargé ✓')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur génération PDF')
  } finally { pdfLoading.value = false }
}

// ── Stepper navigation ────────────────────────────────────
function next(): void {
  if (!validateStep(step.value)) return toastError?.('Veuillez corriger les champs requis')
  if (step.value < steps.length - 1) step.value++
}
function prev(): void { if (step.value > 0) step.value-- }

// ── Reset / Hydrate ───────────────────────────────────────
function resetForNewContract(): void {
  contract.resetForm?.()
  contract.selectedServices  = []
  selectedIds.value          = articleItems.value.filter(a => a.is_active).map(a => a.id)
  currentContratId.value     = ''
  selectedEntrepriseId.value = null
  pdfUrl.value               = ''
  step.value                 = 0
  autosaveState.value        = 'idle'
  autosaveAt.value           = ''
  autosaveError.value        = ''
}

/** Charge un contrat existant depuis l'URL (?id=X) */
async function preloadFromRouteId(): Promise<void> {
  const id = String(route.query.id || '')
  if (!id) return
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/contrats/${id}`,
      { headers: authHeaders() }
    )
    const c = res.data
    currentContratId.value     = String(c.id)
    selectedEntrepriseId.value = c.entreprise_id ?? null

    // Hydration des champs du formulaire
    contract.form.dateDebut = c.date_debut ?? ''
    contract.form.dateFin   = c.date_fin   ?? ''
    contract.form.months    = c.duree_mois ?? 12
    contract.setMonthly(Number(c.prix_mensuel ?? 0))

    // Restauration des articles sélectionnés
    if (Array.isArray(c.articles)) {
      selectedIds.value = c.articles.map((a: any) => a.id)
    }

    // URL du PDF si déjà généré
    if (c.pdf_path) pdfUrl.value = `${getApiBase()}/storage/${c.pdf_path}`

  } catch (e) {
    console.error('Erreur chargement contrat:', e)
  }
}

// ── Watches ───────────────────────────────────────────────
watch(() => [contract.form.dateDebut, contract.form.dateFin], syncMonthsFromDates)
watch(() => contract.form.months, () => contract.syncFromMonths())
watch(
  () => [contract.form, selectedIds.value, contract.selectedServices],
  () => { saveDraft(); scheduleAutosave() },
  { deep: true }
)

// ── Lifecycle ─────────────────────────────────────────────
onMounted(async () => {
  // Charger les données de référence depuis la DB en parallèle
  await Promise.all([articlesStore.fetchAll(), clientsStore.fetchAll()])

  const isNew = String(route.query.new || '') === '1'
  if (isNew) {
    resetForNewContract()
    clearDraft()
    await navigateTo({ path: '/admin/contrat', query: {} }, { replace: true })
    return
  }

  // Charger contrat existant si ?id= présent
  await preloadFromRouteId()

  // Sinon, charger brouillon local
  if (!currentContratId.value) {
    loadDraft()
    // Sélectionner tous les articles actifs par défaut si aucun sélectionné
    if (!selectedIds.value.length) {
      selectedIds.value = articleItems.value.filter(a => a.is_active).map(a => a.id)
    }
  }

  syncMonthsFromDates()
})

onBeforeUnmount(() => {
  if (autosaveTimer) clearTimeout(autosaveTimer)
})

// ── Aperçu HTML (preview) ─────────────────────────────────
const contractPreviewHtml = computed(() => {
  const f          = contract.form as any
  const entreprise = clientItems.value.find(c => c.id === selectedEntrepriseId.value)

  const articleLines = selectedSorted.value.map((a, i) => `
    <div style="margin-bottom:14px">
      <p style="margin:0 0 6px;font-weight:700">ARTICLE ${i + 1} : ${a.title}</p>
      <p style="margin:0;line-height:1.65;text-align:justify">${a.body ?? ''}</p>
    </div>
  `).join('')

  return `
    <div style="font-family:'Times New Roman',serif;color:#111;background:#fff;padding:38px 46px;max-width:900px;margin:0 auto">
      <h1 style="text-align:center;font-size:24px;margin:0 0 6px">CONTRAT DE DOMICILIATION</h1>
      <p style="text-align:center;font-size:12px;color:#555;margin:0 0 16px">
        ${f.astNom || 'AST-FISC'} — RC: ${f.astRC || '-'} — IF: ${f.astIF || '-'}
      </p>
      <hr style="border:none;border-top:1px solid #c8a96e;margin:10px 0 18px"/>

      <p style="margin:0 0 8px"><b>Domiciliataire :</b> ${f.astNom || '-'}, représentée par ${f.astRepresentant || '-'}, CIN ${f.astCIN || '-'}</p>
      <p style="margin:0 0 8px"><b>Adresse siège :</b> ${f.astAdresse || '-'}</p>

      <p style="margin:0 0 8px"><b>Domicilié :</b> ${entreprise?.raison_sociale || f.societe || '-'}</p>
      <p style="margin:0 0 8px"><b>Gérant :</b> ${f.gerantNom || '-'}, CIN ${f.gerantCIN || '-'}</p>
      <p style="margin:0 0 14px"><b>Contact :</b> ${f.tel || '-'} · ${f.email || '-'}</p>

      <p style="margin:0 0 8px"><b>Période :</b> ${f.dateDebut || '-'} → ${f.dateFin || '-'} (${f.months || 0} mois)</p>
      <p style="margin:0 0 16px"><b>Redevance :</b> ${contract.monthlyTotal} DH/mois — <b>${contract.grandTotal} DH total</b></p>

      <hr style="border:none;border-top:1px solid #ddd;margin:8px 0 18px"/>
      ${articleLines || '<p>Aucun article sélectionné</p>'}

      <div style="margin-top:40px;display:flex;justify-content:space-between;gap:20px">
        <div>
          <p style="margin:0 0 50px">Fait à __________________</p>
          <p style="margin:0">Signature du domiciliataire</p>
        </div>
        <div>
          <p style="margin:0 0 50px">Le __________________</p>
          <p style="margin:0">Signature du domicilié</p>
        </div>
      </div>
    </div>`
})
</script>

<template>
  <div class="space-y-4 animate-fade-up">

    <!-- ── Header ─────────────────────────────────────── -->
    <div class="card p-4 flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="font-serif text-2xl">
          {{ currentContratId ? `Contrat #${currentContratId}` : 'Nouveau contrat' }}
        </h1>
        <p class="text-xs mt-1 text-app-text/40">
          <span v-if="autosaveState === 'saved'">✓ Sauvegardé {{ new Date(autosaveAt).toLocaleTimeString() }}</span>
          <span v-else-if="autosaveState === 'saving'">⏳ Sauvegarde en cours...</span>
          <span v-else-if="autosaveState === 'error'" class="text-red-400">✗ {{ autosaveError }}</span>
          <span v-else-if="lastSavedAt">Brouillon local : {{ new Date(lastSavedAt).toLocaleString() }}</span>
        </p>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-outline btn-sm" @click="showPreview = true">👁 Aperçu</button>
        <button class="btn btn-outline btn-sm" @click="saveAsDraft(false)">💾 Sauvegarder</button>
      </div>
    </div>

    <!-- ── Stepper ────────────────────────────────────── -->
    <div class="card p-3">
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-for="(s, i) in steps" :key="s"
          class="px-3 py-1.5 rounded-lg text-xs border font-bold transition"
          :class="step === i
            ? 'border-gold text-gold bg-gold/10'
            : 'border-white/10 text-app-text/50 hover:border-white/20'"
          @click="step = i"
        >
          {{ i + 1 }}. {{ s }}
        </button>
      </div>
    </div>

    <!-- ── Étape 0 : AST-FISC ────────────────────────── -->
    <div v-if="step === 0" class="card p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
      <div><label class="f-label">Raison sociale</label><input v-model="contract.form.astNom" class="f-input" /></div>
      <div><label class="f-label">RC</label><input v-model="contract.form.astRC" class="f-input" /></div>
      <div><label class="f-label">IF</label><input v-model="contract.form.astIF" class="f-input" /></div>
      <div><label class="f-label">Représentant</label><input v-model="contract.form.astRepresentant" class="f-input" /></div>
      <div><label class="f-label">CIN Représentant</label><input v-model="contract.form.astCIN" class="f-input" /></div>
      <div><label class="f-label">Adresse siège</label><input v-model="contract.form.astAdresse" class="f-input" /></div>
    </div>

    <!-- ── Étape 1 : Client ───────────────────────────── -->
    <div v-else-if="step === 1" class="card p-4 space-y-4">

      <!-- Dropdown entreprises depuis DB -->
      <div>
        <label class="f-label">Entreprise cliente *</label>
        <select v-model="selectedEntrepriseId" class="f-input">
          <option :value="null" disabled>-- Sélectionner une entreprise --</option>
          <option v-for="c in clientItems" :key="c.id" :value="c.id">
            {{ c.raison_sociale }}{{ c.ville ? ` — ${c.ville}` : '' }}
          </option>
        </select>
        <p v-if="!clientItems.length && !clientsStore.loading" class="text-xs text-app-text/40 mt-1">
          Aucun client —
          <NuxtLink to="/admin/clients" class="text-gold underline">créer un client d'abord</NuxtLink>
        </p>
      </div>

      <!-- Fiche client affichée depuis DB -->
      <div
        v-if="selectedEntrepriseId"
        class="rounded-xl border border-white/10 p-3 space-y-1 text-sm bg-white/3"
      >
        <p class="text-app-text/40 text-xs uppercase tracking-widest mb-2">Informations depuis la base de données</p>
        <template v-for="c in clientItems.filter(x => x.id === selectedEntrepriseId)" :key="c.id">
          <p><b>Raison sociale :</b> {{ c.raison_sociale }}</p>
          <p><b>Forme juridique :</b> {{ c.forme_juridique ?? '-' }}</p>
          <p><b>Adresse :</b> {{ c.adresse ?? '-' }}, {{ c.ville ?? '-' }}</p>
          <p v-if="c.client_user"><b>Gérant :</b> {{ c.client_user.nom }} {{ c.client_user.prenom }}</p>
          <p v-if="c.client_user"><b>Email :</b> {{ c.client_user.email }}</p>
        </template>
      </div>

      <!-- Champs supplémentaires pour le contrat -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div><label class="f-label">Nom gérant (contrat)</label><input v-model="contract.form.gerantNom" class="f-input" /></div>
        <div><label class="f-label">CIN gérant</label><input v-model="contract.form.gerantCIN" class="f-input" /></div>
        <div><label class="f-label">Téléphone</label><input v-model="contract.form.tel" class="f-input" /></div>
        <div><label class="f-label">Email</label><input v-model="contract.form.email" class="f-input" type="email" /></div>
        <div class="md:col-span-2">
          <label class="f-label">Adresse personnelle</label>
          <input v-model="contract.form.adressePerso" class="f-input" />
        </div>
      </div>
    </div>

    <!-- ── Étape 2 : Durée & Montants ────────────────── -->
    <div v-else-if="step === 2" class="card p-4 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div><label class="f-label">Date début *</label><input v-model="contract.form.dateDebut" class="f-input" type="date" /></div>
        <div><label class="f-label">Date fin</label><input v-model="contract.form.dateFin" class="f-input" type="date" /></div>
        <div>
          <label class="f-label">Redevance mensuelle (DH)</label>
          <input
            :value="contract.form.redevanceMensuelle" class="f-input" type="number"
            @input="(e: any) => contract.setMonthly(Number(e.target.value) || 0)"
          />
        </div>
        <div>
          <label class="f-label">Redevance annuelle (DH)</label>
          <input
            :value="contract.form.redevanceAnnuelle" class="f-input" type="number"
            @input="(e: any) => contract.setAnnual(Number(e.target.value) || 0)"
          />
        </div>
      </div>

      <!-- Récap calculé automatiquement -->
      <div class="rounded-xl border border-white/10 p-3 space-y-1 text-sm bg-white/3">
        <p>Durée calculée : <b>{{ contract.form.months }} mois</b></p>
        <p>Total mensuel (avec services) : <b>{{ contract.monthlyTotal }} DH</b></p>
        <p>Total global : <b class="text-gold text-base">{{ contract.grandTotal }} DH</b></p>
      </div>

      <!-- Délai de notification avant expiration -->
      <div class="rounded-xl border border-gold/20 p-3 space-y-2 bg-gold/5">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">🔔 Rappel avant expiration</p>
        <p class="text-xs text-app-text/50">Vous recevrez une notification ce nombre de mois avant la date de fin.</p>
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="m in [1, 3, 6]" :key="m"
            class="px-4 py-2 rounded-lg text-sm font-bold border transition"
            :class="notificationDelayMonths === m
              ? 'border-gold bg-gold/20 text-gold'
              : 'border-white/10 text-app-text/50 hover:border-white/30'"
            @click="notificationDelayMonths = (m as 1|3|6)"
          >
            {{ m }} mois
          </button>
        </div>
      </div>
    </div>

    <!-- ── Étape 3 : Articles depuis DB ──────────────── -->
    <div v-else-if="step === 3" class="card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <p class="font-semibold text-sm">
          Bibliothèque d'articles
          <span class="text-app-text/40 font-normal ml-1">({{ articleItems.length }} disponibles)</span>
        </p>
        <p class="text-xs text-gold">{{ selectedIds.length }} sélectionné(s)</p>
      </div>

      <!-- Loading articles -->
      <div v-if="articlesLoading" class="text-center py-8 text-app-text/40">
        Chargement des articles...
      </div>

      <!-- Liste des articles -->
      <div v-else class="space-y-2">
        <div
          v-for="a in articleItems" :key="a.id"
          class="rounded-xl border p-3 transition"
          :class="selectedIds.includes(a.id)
            ? 'border-gold/40 bg-gold/5'
            : 'border-white/10 hover:border-white/20'"
        >
          <!-- Mode lecture -->
          <div v-if="editingId !== a.id">
            <div class="flex items-start justify-between gap-2">
              <!-- Clic pour sélectionner/désélectionner -->
              <button class="text-left flex-1 min-w-0" @click="toggleArticle(a.id)">
                <p class="font-semibold text-sm">{{ a.title }}</p>
                <p class="text-xs text-app-text/50 mt-1 line-clamp-2">{{ a.body }}</p>
              </button>

              <!-- Actions -->
              <div class="flex gap-1 flex-shrink-0 ml-2 items-center">
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="selectedIds.includes(a.id)
                    ? 'text-gold bg-gold/10'
                    : 'text-app-text/40 bg-white/5'"
                >
                  {{ selectedIds.includes(a.id) ? '✓ Sélectionné' : '○' }}
                </span>
                <button class="btn btn-outline btn-sm" @click.stop="startEdit(a)">Éditer</button>
                <button class="btn btn-danger btn-sm" @click.stop="deleteArticle(a.id)">✕</button>
              </div>
            </div>
          </div>

          <!-- Mode édition inline -->
          <div v-else class="space-y-2">
            <input v-model="editTitle" class="f-input text-sm" placeholder="Titre de l'article" />
            <textarea v-model="editBody" class="f-input text-sm min-h-[80px] resize-y" placeholder="Contenu de l'article" />
            <div class="flex gap-2">
              <button class="btn btn-gold btn-sm" :disabled="savingArticle" @click="saveEdit">
                {{ savingArticle ? 'Enregistrement...' : '✓ Enregistrer' }}
              </button>
              <button class="btn btn-outline btn-sm" @click="cancelEdit">Annuler</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Formulaire ajout d'un nouvel article -->
      <div class="border-t border-white/10 pt-4 space-y-2">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Ajouter un article à la bibliothèque</p>
        <input v-model="newArtTitle" class="f-input" placeholder="Titre du nouvel article" />
        <textarea v-model="newArtBody" class="f-input min-h-[80px] resize-y" placeholder="Contenu du nouvel article" />
        <button
          class="btn btn-gold btn-md"
          :disabled="savingArticle || !newArtTitle.trim()"
          @click="addArticle"
        >
          {{ savingArticle ? 'Ajout...' : '+ Ajouter à la bibliothèque' }}
        </button>
      </div>
    </div>

    <!-- ── Étape 4 : Récapitulatif ────────────────────── -->
    <div v-else class="card p-4 space-y-3">
      <p class="font-serif text-lg">Récapitulatif du contrat</p>
      <div class="space-y-1 text-sm">
        <p>Entreprise : <b>{{ clientItems.find(c => c.id === selectedEntrepriseId)?.raison_sociale ?? '—' }}</b></p>
        <p>Période : <b>{{ contract.form.dateDebut || '—' }} → {{ contract.form.dateFin || '—' }}</b></p>
        <p>Durée : <b>{{ contract.form.months }} mois</b></p>
        <p>Redevance mensuelle : <b>{{ contract.monthlyTotal }} DH</b></p>
        <p>Total global : <b class="text-gold text-base">{{ contract.grandTotal }} DH</b></p>
        <p>Articles inclus : <b>{{ selectedIds.length }}</b></p>
      </div>

      <!-- Lien PDF si déjà généré -->
      <div v-if="pdfUrl" class="p-3 rounded-xl border border-green-500/30 bg-green-500/10">
        <p class="text-sm text-green-400">
          ✓ PDF disponible —
          <a :href="pdfUrl" target="_blank" class="underline">Ouvrir dans un nouvel onglet</a>
        </p>
      </div>
    </div>

    <!-- ── Navigation ─────────────────────────────────── -->
    <div class="flex items-center justify-between gap-2">
      <button class="btn btn-outline btn-md" :disabled="step === 0" @click="prev">← Précédent</button>
      <div class="flex gap-2 flex-wrap justify-end">
        <button class="btn btn-outline btn-sm" @click="clearDraft">🗑 Vider brouillon</button>

        <!-- Bouton PDF sur l'étape récap -->
        <button
          v-if="step === 4"
          class="btn btn-gold btn-md"
          :disabled="pdfLoading"
          @click="generateAndDownloadPdf"
        >
          {{ pdfLoading ? '⏳ Génération...' : '⬇ Générer PDF' }}
        </button>

        <!-- Bouton Suivant sur les autres étapes -->
        <button v-if="step < 4" class="btn btn-gold btn-md" @click="next">Suivant →</button>
      </div>
    </div>

    <!-- ── Modal aperçu ───────────────────────────────── -->
    <div
      v-if="showPreview"
      class="fixed inset-0 z-[100] bg-black/80 p-4 md:p-8 overflow-y-auto"
      @click.self="showPreview = false"
    >
      <div class="max-w-5xl mx-auto">
        <div class="flex justify-end mb-3 gap-2">
          <button class="btn btn-outline btn-sm" @click="showPreview = false">✕ Fermer</button>
          <button
            class="btn btn-gold btn-sm"
            :disabled="pdfLoading"
            @click="generateAndDownloadPdf"
          >
            {{ pdfLoading ? 'Génération...' : '⬇ Télécharger PDF' }}
          </button>
        </div>
        <div class="rounded-xl overflow-hidden bg-neutral-200 p-4">
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div v-html="contractPreviewHtml" />
        </div>
      </div>
    </div>

  </div>
</template>