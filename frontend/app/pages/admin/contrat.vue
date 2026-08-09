<script setup lang="ts">
/**
 * pages/admin/contrat.vue
 * 4-step contract creation wizard using shared ContratPreviewModal component.
 */

import { useContractStore } from '~/stores/contrat'
import { useClientsStore  } from '~/stores/clients'
import { useArticlesStore } from '~/stores/articles'
import { contratService } from '~/services/contrat.service'
import { templateService, type TemplateEntity } from '~/services/template.service'
import ContratPreviewModal from '~/components/ContratPreviewModal.vue' 


definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const contract      = useContractStore()
const clientsStore  = useClientsStore()
const articlesStore = useArticlesStore()
const { success, error: toastError } = useToast()

const pdfPreview = ref()

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

// ── Wizard state ─────────────────────────────────────────────────────────

const step       = ref(1)
const totalSteps = 4
const saving     = ref(false)
const contratId  = ref<number | null>(null)

// ── Step 1 ──────────────────────────────────────────────────────────────

const profile       = ref<any>({})
const profileLoaded = ref(false)
const addresses     = ref<{ label: string; value: string }[]>([])
const selectedAddress = ref('')

function normaliseAddresses(raw: any): { label: string; value: string }[] {
    if (!raw) return []
    if (Array.isArray(raw)) {
        return raw
            .map((item, i) => {
                if (typeof item === 'string') return { label: `Adresse ${i + 1}`, value: item }
                if (item && typeof item === 'object') {
                    const value = item.value ?? item.adresse ?? item.address ?? String(item)
                    const label = item.label ?? item.nom    ?? item.name    ?? `Adresse ${i + 1}`
                    return { label, value }
                }
                return { label: `Adresse ${i + 1}`, value: String(item) }
            })
            .filter(a => a.value.trim())
    }
    if (typeof raw === 'string' && raw.trim()) {
        return [{ label: 'Adresse principale', value: raw.trim() }]
    }
    return []
}

async function loadProfile(): Promise<void> {
    try {
        const res = await $fetch<{ success: boolean; data: any }>(
            `${getApiBase()}/api/profile`,
            { headers: authHeaders() }
        )
        profile.value = res.data ?? {}
        contract.fillFromProfile(res.data ?? {})

        const raw = res.data?.adresses ?? res.data?.addresses ?? res.data?.adresse ?? res.data?.address ?? null
        addresses.value = normaliseAddresses(raw)

        profileLoaded.value = !!(res.data?.nom_societe && res.data?.representant_legal)
    } catch {
        profileLoaded.value = false
        addresses.value     = []
    }
}

function pickAddress(addr: { label: string; value: string }): void {
    selectedAddress.value        = addr.value
    contract.form.companyAdresse = addr.value
}

// ── Step 2 ──────────────────────────────────────────────────────────────

const clientMode        = ref<'select' | 'create'>('select')
const selectedClientId  = ref<number | null>(null)
const selectedClient    = ref<any>(null)
const clientSearchQuery = ref('')

const newClientForm = reactive({
    raison_sociale: '', forme_juridique: '', gerantNom: '', gerantCIN: '',
    dateNaissance: '', adressePerso: '', tel: '', email: '', password: '',
})

const filteredClients = computed(() => {
    const q     = clientSearchQuery.value.toLowerCase().trim()
    const items = clientsStore.items ?? []
    if (!q) return items
    return items.filter(c =>
        c.raison_sociale?.toLowerCase().includes(q) ||
        c.client_user?.email?.toLowerCase().includes(q)
    )
})

function selectClient(client: any): void {
    selectedClientId.value = client.id
    selectedClient.value   = client

    contract.form.societe      = client.raison_sociale ?? ''
    contract.form.gerantNom    = client.representant?.nom_complet
                                 ?? (client.client_user
                                     ? `${client.client_user.nom ?? ''} ${client.client_user.prenom ?? ''}`.trim()
                                     : '')
    contract.form.gerantCIN    = client.representant?.cin       ?? ''
    contract.form.tel          = client.representant?.telephone  ?? client.client_user?.telephone ?? ''
    contract.form.email        = client.representant?.email      ?? client.client_user?.email     ?? ''
    contract.form.adressePerso = client.representant?.adresse    ?? ''

    clientSearchQuery.value = ''
}

function switchToCreate(): void {
    selectedClientId.value = null
    selectedClient.value   = null
    clientMode.value       = 'create'
    Object.assign(newClientForm, {
        raison_sociale: '', forme_juridique: '', gerantNom: '', gerantCIN: '',
        dateNaissance: '', adressePerso: '', tel: '', email: '', password: '',
    })
}

watch(() => newClientForm.raison_sociale, v => { contract.form.societe      = v })
watch(() => newClientForm.gerantNom,      v => { contract.form.gerantNom    = v })
watch(() => newClientForm.gerantCIN,      v => { contract.form.gerantCIN    = v })
watch(() => newClientForm.tel,            v => { contract.form.tel          = v })
watch(() => newClientForm.email,          v => { contract.form.email        = v })
watch(() => newClientForm.adressePerso,   v => { contract.form.adressePerso = v })

// ── Templates ──────────────────────────────────────────────────────────

const templates          = ref<TemplateEntity[]>([])
const selectedTemplateId = ref<number | null>(null)
const showTemplatePicker = ref(false)

async function loadTemplates(): Promise<void> {
    try {
        const res = await templateService.list()
        templates.value = res.data ?? []
    } catch { templates.value = [] }
}

function loadTemplate(template: TemplateEntity): void {
    selectedTemplateId.value = template.id
    const sorted = [...template.articles].sort((a, b) => (a.pivot?.ordre ?? 0) - (b.pivot?.ordre ?? 0))
    selectedArticleIds.value = sorted.map(a => String(a.id))
    orderedArticles.value = sorted.map((a, i) => ({ ...a, ordre: a.pivot?.ordre ?? i + 1, _expanded: false }))
}

function onTemplatePicked(template: TemplateEntity): void {
    loadTemplate(template)
    success(`Modèle "${template.name}" chargé`)
}

function clearSelection(): void {
    selectedTemplateId.value = null
    selectedArticleIds.value = []
    orderedArticles.value    = []
}

// ── Step 3 ──────────────────────────────────────────────────────────────

const selectedArticleIds = ref<string[]>([])
const orderedArticles = ref<any[]>([])

function toggleArticle(article: any): void {
    const id = String(article.id)
    selectedTemplateId.value = null

    if (selectedArticleIds.value.includes(id)) {
        selectedArticleIds.value = selectedArticleIds.value.filter(x => x !== id)
        orderedArticles.value    = orderedArticles.value
            .filter(a => String(a.id) !== id)
            .map((a, i) => ({ ...a, ordre: i + 1 }))
    } else {
        const newOrdre = orderedArticles.value.length + 1
        selectedArticleIds.value.push(id)
        orderedArticles.value.push({ ...article, ordre: newOrdre, _expanded: false })
    }
}

function resetArticleBody(article: any): void {
    const original = articlesStore.items.find(a => String(a.id) === String(article.id))
    if (original) article.body = original.body
}

// Drag & drop
const dragIndex = ref<number | null>(null)
function onDragStart(index: number, event: DragEvent): void {
    dragIndex.value = index
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}
function onDragOver(index: number, event: DragEvent): void {
    event.preventDefault()
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
}
function onDrop(targetIndex: number): void {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return
    const arr = [...orderedArticles.value]
    const [moved] = arr.splice(dragIndex.value, 1)
    arr.splice(targetIndex, 0, moved)
    orderedArticles.value = arr.map((a, i) => ({ ...a, ordre: i + 1 }))
    dragIndex.value = null
    selectedTemplateId.value = null
}
function onDragEnd(): void { dragIndex.value = null }

// Save template
const showSaveTemplateModal = ref(false)
const newTemplateName       = ref('')
const newTemplateDesc       = ref('')
const savingTemplate        = ref(false)

function openSaveTemplateModal(): void {
    if (orderedArticles.value.length === 0) {
        toastError?.('Sélectionnez au moins un article avant de créer un modèle')
        return
    }
    newTemplateName.value = ''
    newTemplateDesc.value = ''
    showSaveTemplateModal.value = true
}

async function submitSaveTemplate(): Promise<void> {
    if (!newTemplateName.value.trim()) {
        toastError?.('Le nom du modèle est obligatoire')
        return
    }
    savingTemplate.value = true
    try {
        const payload = orderedArticles.value.map(a => ({ id: String(a.id), ordre: a.ordre }))
        const res = await templateService.create(newTemplateName.value.trim(), newTemplateDesc.value.trim(), payload)
        templates.value.unshift(res.data)
        selectedTemplateId.value    = res.data.id
        showSaveTemplateModal.value = false
        success('Modèle créé avec succès')
    } catch (e: any) {
        toastError?.(e?.data?.message ?? 'Erreur lors de la création du modèle')
    } finally {
        savingTemplate.value = false
    }
}

// Navigation
function canProceed(): boolean {
    if (step.value === 1) return selectedAddress.value.trim().length > 0
    if (step.value === 2) {
        if (clientMode.value === 'select') return selectedClientId.value !== null
        return !!(newClientForm.raison_sociale && newClientForm.email && newClientForm.password)
    }
    return true
}

async function nextStep(): Promise<void> {
    if (!canProceed()) {
        toastError?.(step.value === 1
            ? 'Veuillez sélectionner une adresse de domiciliation'
            : 'Veuillez remplir les champs obligatoires')
        return
    }

    if (step.value === 2 && clientMode.value === 'create') {
        saving.value = true
        try {
            const newClient = await clientsStore.create({
                raison_sociale:   newClientForm.raison_sociale,
                forme_juridique:  newClientForm.forme_juridique || undefined,
                client_nom:       newClientForm.gerantNom,
                client_email:     newClientForm.email,
                client_password:  newClientForm.password,
                client_telephone: newClientForm.tel || undefined,
                statut:           'actif',
                pays:             'Maroc',
            })
            selectedClientId.value = newClient.id
            selectedClient.value   = newClient
            clientMode.value       = 'select'
            success('Client créé avec succès')
        } catch (e: any) {
            const msg = e?.data?.errors
                ? Object.values(e.data.errors).flat().join(' · ')
                : e?.data?.message ?? 'Erreur lors de la création du client'
            toastError?.(msg)
            saving.value = false
            return
        } finally {
            saving.value = false
        }
    }

    step.value++
}

function prevStep(): void { if (step.value > 1) step.value-- }

// Save Draft
async function saveDraft(): Promise<void> {
    if (!selectedClientId.value) {
        toastError?.('Aucun client sélectionné')
        return
    }

    saving.value = true
    try {
        const body = {
            entreprise_id:   selectedClientId.value,
            titre_contrat:   contract.form.titreContrat  || null,
            date_debut:      contract.form.dateDebut     || null,
            date_fin:        contract.form.dateFin       || null,
            duree_mois:      contract.form.months        || null,
            prix_mensuel:    contract.monthlyTotal       || null,
            prix_total:      contract.grandTotal         || null,
            caution:         contract.form.caution       || null,
            mode_paiement:   contract.form.mode_paiement  || null,
            ville_signature: contract.form.ville_signature || null,
            date_signature:  contract.form.date_signature  || null,
            instruction_no:  contract.form.instruction_no  || null,
            statut:          'draft',
            articles: orderedArticles.value.map(a => ({ id: String(a.id), ordre: a.ordre })),
        }

        const res = await $fetch<{ success: boolean; data: any }>(
            `${getApiBase()}/api/contrats`,
            { method: 'POST', headers: authHeaders(), body }
        )

        contratId.value = res.data.id
        success('Contrat enregistré avec succès')
        step.value = 4
    } catch (e: any) {
        const msg = e?.data?.errors
            ? Object.values(e.data.errors).flat().join(' · ')
            : e?.data?.message ?? 'Erreur lors de la sauvegarde'
        toastError?.(msg)
    } finally {
        saving.value = false
    }
}

// Live Preview Handler using the universal component
function openLivePreview(): void {
    if (!pdfPreview.value || typeof pdfPreview.value.openLive !== 'function') {
        console.warn("L'aperçu n'est pas prêt. Veuillez rafraîchir la page ou vérifier l'import du composant.")
        return
    }

    pdfPreview.value.openLive({
        titreContrat: contract.form.titreContrat,
        instruction_no: contract.form.instruction_no,
        duree_mois: contract.form.months,
        date_debut: contract.form.dateDebut,
        date_fin: contract.form.dateFin,
        date_signature: contract.form.date_signature,
        redevanceMensuelle: contract.form.redevanceMensuelle,
        redevanceAnnuelle: contract.form.redevanceAnnuelle,
        mode_paiement: contract.form.mode_paiement,
        caution: contract.form.caution,
        companyName: contract.form.companyName,
        companyRC: contract.form.companyRC,
        companyIF: contract.form.companyIF,
        companyTP: contract.form.companyTP,
        companyAdresse: contract.form.companyAdresse,
        companyRepresentant: contract.form.companyRepresentant,
        companyCIN: contract.form.companyCIN,
        societe: contract.form.societe,
        forme_juridique: selectedClient.value?.forme_juridique,
        ville_client: selectedClient.value?.ville,
        gerantNom: contract.form.gerantNom,
        gerantCIN: contract.form.gerantCIN,
        tel: contract.form.tel,
        email: contract.form.email,
        adressePerso: contract.form.adressePerso,
        ville_signature: contract.form.ville_signature,
        articles: orderedArticles.value,
    }, contract.form.titreContrat || 'Aperçu du contrat')
}

function openSavedPreview(): void {
    if (!contratId.value) return
    const url = contratService.streamPdfUrl(String(contratId.value), 'preview')
    if (pdfPreview.value && typeof pdfPreview.value.openUrl === 'function') {
        pdfPreview.value.openUrl(url, contract.form.titreContrat || `Contrat #${contratId.value}`)
    }
}

watch(() => contract.form.dateDebut, recalcMonths)
watch(() => contract.form.dateFin,   recalcMonths)

function recalcMonths(): void {
    if (!contract.form.dateDebut || !contract.form.dateFin) return
    const start  = new Date(contract.form.dateDebut)
    const end    = new Date(contract.form.dateFin)
    const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
    if (!isNaN(months) && months > 0) {
        contract.form.months = months
        contract.syncFromMonths()
    }
}

onMounted(async () => {
    await Promise.all([
        loadProfile(),
        clientsStore.fetchAll(),
        articlesStore.fetchAll(),
        loadTemplates(),
    ])
    if (templates.value.length > 0) {
        showTemplatePicker.value = true
    }
})
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl mx-auto">

    <!-- Header + live preview button -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h1 class="font-serif text-2xl" style="color:var(--app-text)">
          Nouveau <em class="italic" style="color:#c8a96e">Contrat</em>
        </h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">Étape {{ step }} sur {{ totalSteps }}</p>
      </div>
      <button type="button" class="btn btn-outline btn-md shrink-0" @click="openLivePreview">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        Aperçu du contrat
      </button>
    </div>

    <!-- Progress bar -->
    <div class="flex items-center gap-2">
      <div v-for="s in totalSteps" :key="s" class="h-1.5 flex-1 rounded-full transition-all duration-300"
           :style="`background: ${s <= step ? '#c8a96e' : 'var(--app-border)'}`" />
    </div>

    <!-- STEP 1 -->
    <div v-if="step === 1" class="space-y-4">
      <div class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm"
        :style="profileLoaded
          ? 'background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#22c55e'
          : 'background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);color:#f59e0b'">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <template v-if="profileLoaded">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </template>
          <template v-else>
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </template>
        </svg>
        <span>
          {{ profileLoaded ? 'Profil chargé automatiquement' : 'Profil incomplet —' }}
          <NuxtLink to="/admin/profile" class="underline ml-1" style="opacity:0.8">
            {{ profileLoaded ? 'Modifier →' : 'Compléter votre profil →' }}
          </NuxtLink>
        </span>
      </div>

      <div v-if="templates.length" class="flex items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm flex-wrap"
           style="background:rgba(200,169,110,0.08);border:1px solid rgba(200,169,110,0.2)">
        <span style="color:#c8a96e">
          📋 {{ selectedTemplateId
            ? `Modèle "${templates.find(t => t.id === selectedTemplateId)?.name}" chargé`
            : `${templates.length} modèle(s) disponible(s)` }}
        </span>
        <button type="button" class="text-xs underline" style="color:#c8a96e" @click="showTemplatePicker = true">
          Choisir un modèle →
        </button>
      </div>

      <div class="card p-5">
        <p class="text-xs uppercase tracking-widest font-bold mb-4" style="color:#c8a96e">
          Domiciliataire (depuis votre profil)
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Société</p><p class="font-medium" style="color:var(--app-text)">{{ contract.form.companyName || '—' }}</p></div>
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Représentant</p><p class="font-medium" style="color:var(--app-text)">{{ contract.form.companyRepresentant || '—' }}</p></div>
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">CIN</p><p style="color:var(--app-text)">{{ contract.form.companyCIN || '—' }}</p></div>
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">RC</p><p style="color:var(--app-text)">{{ contract.form.companyRC || '—' }}</p></div>
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">IF</p><p style="color:var(--app-text)">{{ contract.form.companyIF || '—' }}</p></div>
          <div><p class="text-xs mb-0.5" style="color:var(--app-text-faint)">TP</p><p style="color:var(--app-text)">{{ contract.form.companyTP || '—' }}</p></div>
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Titre du contrat</p>
        <div>
          <label class="f-label">Intitulé affiché sur le document</label>
          <input v-model="contract.form.titreContrat" class="f-input" placeholder="Contrat de Domiciliation" maxlength="255" />
        </div>
        <div>
          <label class="f-label">Numéro d'instruction <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">(optionnel)</span></label>
          <input v-model="contract.form.instruction_no" class="f-input" placeholder="INS-2026-001" />
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Adresse de domiciliation *</p>
        </div>

        <div v-if="addresses.length > 0" class="space-y-3">
          <div class="flex flex-col gap-2">
            <button v-for="addr in addresses" :key="addr.value" type="button"
                    class="w-full text-left rounded-xl px-4 py-3 transition-all text-sm"
                    :style="selectedAddress === addr.value
                      ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e;color:var(--app-text)'
                      : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'"
                    @click="pickAddress(addr)">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-xs uppercase tracking-wide mb-0.5"
                     :style="selectedAddress === addr.value ? 'color:#c8a96e' : 'color:var(--app-text-faint)'">{{ addr.label }}</p>
                  <p class="truncate" style="color:var(--app-text)">{{ addr.value }}</p>
                </div>
              </div>
            </button>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div>
            <label class="f-label">Saisir l'adresse manuellement *</label>
            <input v-model="selectedAddress" class="f-input" placeholder="Ex : Rue Mohammed V, Agadir 80000"
                   @input="contract.form.companyAdresse = selectedAddress" />
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div v-else-if="step === 2" class="space-y-4">
      <div class="flex gap-2">
        <button type="button" class="flex-1 py-2.5 rounded-xl text-sm font-medium transition-all"
                :style="clientMode === 'select'
                  ? 'background:rgba(200,169,110,0.15);border:2px solid #c8a96e;color:#c8a96e'
                  : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'"
                @click="clientMode = 'select'; selectedClient = null; selectedClientId = null">
          Choisir un client existant
        </button>
        <button type="button" class="flex-1 py-2.5 rounded-xl text-sm font-medium transition-all"
                :style="clientMode === 'create'
                  ? 'background:rgba(200,169,110,0.15);border:2px solid #c8a96e;color:#c8a96e'
                  : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'"
                @click="switchToCreate">
          + Nouveau client
        </button>
      </div>

      <div v-if="clientMode === 'select'" class="space-y-4">
        <input v-model="clientSearchQuery" class="f-input" placeholder="Rechercher par raison sociale..." />
        <div class="space-y-2 max-h-64 overflow-y-auto">
          <div v-for="client in filteredClients" :key="client.id"
               class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
               :style="selectedClientId === client.id
                 ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e'
                 : 'background:var(--app-surface-2);border:2px solid var(--app-border)'"
               @click="selectClient(client)">
            <p class="font-semibold text-sm" style="color:var(--app-text)">{{ client.raison_sociale }}</p>
          </div>
        </div>
      </div>

      <div v-else-if="clientMode === 'create'" class="card p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2"><label class="f-label">Raison sociale *</label><input v-model="newClientForm.raison_sociale" class="f-input" required /></div>
          <div><label class="f-label">Nom du gérant *</label><input v-model="newClientForm.gerantNom" class="f-input" required /></div>
          <div><label class="f-label">Email *</label><input v-model="newClientForm.email" type="email" class="f-input" required /></div>
          <div><label class="f-label">Mot de passe *</label><input v-model="newClientForm.password" type="password" class="f-input" required /></div>
        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div v-else-if="step === 3" class="space-y-5">
      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Durée et montants</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div><label class="f-label">Date de début</label>
            <input :value="contract.form.dateDebut" type="date" class="f-input" @change="contract.setDateDebut(($event.target as HTMLInputElement).value)" /></div>
          <div><label class="f-label">Date de fin</label>
            <input :value="contract.form.dateFin" type="date" class="f-input" @change="contract.form.dateFin = ($event.target as HTMLInputElement).value; recalcMonths()" /></div>
          <div><label class="f-label">Redevance mensuelle (DH)</label>
            <input :value="contract.form.redevanceMensuelle" type="number" min="0" step="0.01" class="f-input" @input="contract.setMonthly(Number(($event.target as HTMLInputElement).value))" /></div>
        </div>
      </div>
    </div>

    <!-- STEP 4 -->
    <div v-else-if="step === 4" class="space-y-4">
      <div class="card p-6 text-center space-y-4">
        <h2 class="font-serif text-xl" style="color:var(--app-text)">Contrat enregistré</h2>
        <p class="text-sm" style="color:var(--app-text-muted)">Contrat #{{ contratId }}</p>
        <button class="btn btn-gold btn-md" @click="openSavedPreview">Aperçu du contrat</button>
      </div>
    </div>

    <!-- Navigation actions -->
    <div v-if="step < 4" class="flex justify-between items-center pt-4">
      <button type="button" class="btn btn-outline btn-md" :disabled="step === 1" @click="prevStep">Précédent</button>
      <button v-if="step < 3" type="button" class="btn btn-gold btn-md" @click="nextStep">Suivant</button>
      <button v-else type="button" class="btn btn-gold btn-md" :disabled="saving" @click="saveDraft">
        {{ saving ? 'Enregistrement...' : 'Enregistrer le contrat' }}
      </button>
    </div>

    <!-- Modals -->
    <TemplatePickerModal v-if="showTemplatePicker" :templates="templates" @close="showTemplatePicker = false" @select="onTemplatePicked" />
    <ContratPreviewModal ref="pdfPreview" />

  </div>
</template>