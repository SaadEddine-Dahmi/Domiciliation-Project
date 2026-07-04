<script setup lang="ts">
/**
 * pages/admin/contrat.vue
 *
 *
 * Step 1 — Domiciliataire profile (autofilled) + address chip selector
 *           + dynamic contract title input with live PDF preview
 *           ✅ NEW: also shows email and telephone from profile
 * Step 2 — Client: pick existing or create inline
 *           ✅ NEW: date de naissance shown in new-client form and synced to store
 *           ✅ NEW: date de naissance shown in selected-client summary card
 * Step 3 — Articles chip library + drag-reorder + financial fields
 *           (dates, redevance, signature city and date)
 * Step 4 — Confirmation + PDF preview + download
 *           ✅ NEW: "Renouveler le contrat" button calls POST /api/contrats/{id}/renew
 *
 * ── Article chip reactivity ────────────────────────────────────────────────
 * selectedArticleIds is ref<string[]> (NOT ref<Set<string>>).
 * Vue 3 tracks .push() and array reassignment but NOT Set.add() / Set.delete().
 * Using a Set caused chips to appear stuck after every toggle.
 *
 * ── Contract title ─────────────────────────────────────────────────────────
 * contract.form.titreContrat is typed freely in step 1.
 * Sent as-is to the backend. Backend applies 'Contrat de Domiciliation'
 * only when the field arrives null or empty.
 */

import { useContractStore } from '~/stores/contrat'
import { useClientsStore  } from '~/stores/clients'
import { useArticlesStore } from '~/stores/articles'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const contract      = useContractStore()
const clientsStore  = useClientsStore()
const articlesStore = useArticlesStore()
const { success, error: toastError } = useToast()

// ── Auth helpers ──────────────────────────────────────────────────────────────

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
        return JSON.parse(localStorage.getItem('app_auth') ?? '{}')?.token ?? ''
    } catch { return '' }
}

// ── Wizard state ──────────────────────────────────────────────────────────────

const step       = ref(1)
const totalSteps = 4
const saving     = ref(false)
const contratId  = ref<number | null>(null)

// ── Step 1: profile + address selector ───────────────────────────────────────

const profile       = ref<any>({})
const profileLoaded = ref(false)
const addresses     = ref<{ label: string; value: string }[]>([])
const selectedAddress = ref('')

/**
 * Normalise the addresses array returned by the profile API into a flat
 * [{label, value}] structure regardless of the shape the backend sends.
 */
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

/**
 * Load the domiciliataire profile and autofill wizard step 1 fields.
 * Also loads the address list for the chip selector.
 * email and telephone are now passed to fillFromProfile() (audit fix).
 */
async function loadProfile(): Promise<void> {
    try {
        const res = await $fetch<{ success: boolean; data: any }>(
            `${getApiBase()}/api/profile`,
            { headers: authHeaders() }
        )
        profile.value = res.data ?? {}

        // fillFromProfile now also sets companyEmail and companyTelephone.
        contract.fillFromProfile(res.data ?? {})

        const raw = res.data?.adresses
                 ?? res.data?.addresses
                 ?? res.data?.adresse
                 ?? res.data?.address
                 ?? null
        addresses.value = normaliseAddresses(raw)

        // Profile is considered "loaded" when the minimum required fields exist.
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

// ── Step 2: client selection / creation ──────────────────────────────────────

const clientMode        = ref<'select' | 'create'>('select')
const selectedClientId  = ref<number | null>(null)
const selectedClient    = ref<any>(null)
const clientSearchQuery = ref('')

const newClientForm = reactive({
    raison_sociale:  '',
    forme_juridique: '',
    gerantNom:       '',
    gerantCIN:       '',
    dateNaissance:   '',   // ✅ date de naissance du gérant
    adressePerso:    '',
    tel:             '',
    email:           '',
    password:        '',
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

/**
 * Select an existing client and copy their representant fields into the store.
 */
function selectClient(client: any): void {
    selectedClientId.value = client.id
    selectedClient.value   = client
    contract.fillFromClient(client)
    clientSearchQuery.value = ''
}

function switchToCreate(): void {
    selectedClientId.value = null
    selectedClient.value   = null
    clientMode.value       = 'create'
    Object.assign(newClientForm, {
        raison_sociale: '', forme_juridique: '',
        gerantNom: '', gerantCIN: '', dateNaissance: '',
        adressePerso: '', tel: '', email: '', password: '',
    })
}

// Keep the store form in sync as the new-client fields are typed.
watch(() => newClientForm.raison_sociale, v => { contract.form.societe       = v })
watch(() => newClientForm.gerantNom,      v => { contract.form.gerantNom     = v })
watch(() => newClientForm.gerantCIN,      v => { contract.form.gerantCIN     = v })
watch(() => newClientForm.tel,            v => { contract.form.tel           = v })
watch(() => newClientForm.email,          v => { contract.form.email         = v })
watch(() => newClientForm.adressePerso,   v => { contract.form.adressePerso  = v })
watch(() => newClientForm.dateNaissance,  v => { contract.form.dateNaissance = v })  // ✅ NEW

// ── Step 3: article selection ─────────────────────────────────────────────────

/**
 * SOURCE OF TRUTH for which articles are currently selected.
 *
 * WHY ref<string[]> and NOT ref<Set<string>>:
 *   Vue 3 tracks reactivity on .value reassignment and on array mutations
 *   (.push, .filter). Set.add() / Set.delete() mutate the Set in-place
 *   without reassigning .value, so Vue never schedules a re-render.
 *   Chip :style bindings would never update after a toggle — appearing stuck.
 *
 * All IDs are stored as String(). Safe for integer PKs ("19") and future UUIDs.
 */
const selectedArticleIds = ref<string[]>([])

/**
 * Parallel array of shallow-copied article objects for drag-and-drop.
 * Each entry has added fields: ordre (1-based position), _expanded (editor open).
 * Shallow copies prevent edits here from mutating articlesStore.items.
 */
const orderedArticles = ref<any[]>([])

/**
 * Toggle an article in or out of the selection.
 *
 * ADD: push String(id) → .value mutation triggers Vue reactivity.
 * REMOVE: reassign filtered arrays → .value reassignment triggers Vue reactivity.
 * Re-numbers ordre after remove to close gaps.
 * Never mutates the original article object in the store.
 */
function toggleArticle(article: any): void {
    const id = String(article.id)

    if (selectedArticleIds.value.includes(id)) {
        selectedArticleIds.value = selectedArticleIds.value.filter(x => x !== id)
        orderedArticles.value    = orderedArticles.value
            .filter(a => String(a.id) !== id)
            .map((a, i) => ({ ...a, ordre: i + 1 }))
    } else {
        const newOrdre = orderedArticles.value.length + 1  // compute BEFORE push
        selectedArticleIds.value.push(id)
        orderedArticles.value.push({
            ...article,
            ordre:     newOrdre,
            _expanded: false,
        })
    }
}

function resetArticleBody(article: any): void {
    const original = articlesStore.items.find(a => String(a.id) === String(article.id))
    if (original) article.body = original.body
}

// ── Drag-and-drop reordering ──────────────────────────────────────────────────

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
}

function onDragEnd(): void { dragIndex.value = null }

// ── Wizard navigation ─────────────────────────────────────────────────────────

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
        toastError?.(
            step.value === 1
                ? 'Veuillez sélectionner une adresse de domiciliation'
                : 'Veuillez remplir les champs obligatoires'
        )
        return
    }

    if (step.value === 2 && clientMode.value === 'create') {
    saving.value = true
    try {
        // Step 2a — Create the Entreprise + portal User account.
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

        // Step 2b — Create the Representant (gérant) for this new client.
        // This is the critical step that was missing before:
        //   Without it, $entreprise->representant is null in the PDF render,
        //   so gerant_nom, gerant_cin, tel, email, date_naissance are all empty.
        // We do this silently — a failure here shows a warning but does NOT
        // block the wizard. The representant can be added later from the
        // Clients management page → Représentant modal.
        if (
            newClientForm.gerantNom ||
            newClientForm.gerantCIN ||
            newClientForm.dateNaissance ||
            newClientForm.adressePerso
        ) {
            try {
                await clientsStore.createRepresentant(newClient.id, {
                    nom:             newClientForm.gerantNom   || 'Non renseigné',
                    cin:             newClientForm.gerantCIN   || 'Non renseigné',
                    date_naissance:  newClientForm.dateNaissance || undefined,
                    adresse:         newClientForm.adressePerso  || undefined,
                    telephone:       newClientForm.tel            || undefined,
                    email:           newClientForm.email          || undefined,
                })
                // Reload the client from the store to get the fresh representant.
                const updatedClient = clientsStore.items.find(e => e.id === newClient.id)
                if (updatedClient) {
                    selectedClient.value = updatedClient
                    // Re-populate the contract store with the representant data.
                    contract.fillFromClient(updatedClient)
                }
            } catch (repErr: any) {
                // Non-blocking: show a warning but continue to step 3.
                toastError?.('Représentant non créé — ' + (repErr?.data?.message ?? 'erreur réseau'))
            }
        }

        clientMode.value = 'select'
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

function prevStep(): void {
    if (step.value > 1) step.value--
}

// ── Save draft ────────────────────────────────────────────────────────────────

/**
 * POST /api/contrats
 *
 * Saves all wizard data as a draft and advances to step 4.
 * date_debut / date_fin are sent as null when empty — NOT as empty string.
 * Laravel's 'nullable|date' validator accepts null but rejects ''.
 */
async function saveDraft(): Promise<void> {
    if (!selectedClientId.value) {
        toastError?.('Aucun client sélectionné')
        return
    }

    saving.value = true
    try {
        const body = {
            entreprise_id:   selectedClientId.value,
            titre_contrat:   contract.form.titreContrat   || null,
            date_debut:      contract.form.dateDebut      || null,
            date_fin:        contract.form.dateFin        || null,
            duree_mois:      contract.form.months         || null,
            prix_mensuel:    contract.monthlyTotal        || null,
            prix_total:      contract.grandTotal          || null,
            caution:         contract.form.caution        || null,
            mode_paiement:   contract.form.mode_paiement  || null,
            ville_signature: contract.form.ville_signature || null,
            date_signature:  contract.form.date_signature  || null,
            instruction_no:  contract.form.instruction_no  || null,
            statut:          'draft',
            articles: orderedArticles.value.map(a => ({
                id:    String(a.id),
                ordre: a.ordre,
            })),
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

// ── Contract renewal ──────────────────────────────────────────────────────────

const renewing = ref(false)

/**
 * POST /api/contrats/{id}/renew
 *
 * Clones the current contract into a new draft with dates shifted forward.
 * Navigates to the contracts list after renewal so the domiciliataire can
 * open the new draft in the wizard to adjust dates if needed.
 */
async function renewContract(): Promise<void> {
    if (!contratId.value) return
    renewing.value = true
    try {
        const res = await $fetch<{ success: boolean; data: any }>(
            `${getApiBase()}/api/contrats/${contratId.value}/renew`,
            { method: 'POST', headers: authHeaders() }
        )
        success(`Contrat renouvelé — nouveau brouillon #${res.data.id} créé`)
        await navigateTo('/admin/contrats')
    } catch (e: any) {
        toastError?.(e?.data?.message ?? 'Erreur lors du renouvellement')
    } finally {
        renewing.value = false
    }
}

// ── PDF stream ────────────────────────────────────────────────────────────────

const pdfStreamUrl = ref('')
const showPreview  = ref(false)
const pdfLoading   = ref(false)

function buildStreamUrl(mode: 'preview' | 'download'): string {
    const token = encodeURIComponent(getToken())
    return `${getApiBase()}/api/contrats/${contratId.value}/pdf/stream?token=${token}&mode=${mode}`
}

function preparePdf(): void {
    if (!contratId.value) return
    pdfStreamUrl.value = buildStreamUrl('preview')
    success('PDF prêt — cliquez sur Aperçu ou Télécharger')
}

function openPreview():  void { pdfLoading.value = true; showPreview.value = true }
function closePreview(): void { showPreview.value = false }

// ── Date auto-calculation ─────────────────────────────────────────────────────

watch(() => contract.form.dateDebut, recalcMonths)
watch(() => contract.form.dateFin,   recalcMonths)

function recalcMonths(): void {
    if (!contract.form.dateDebut || !contract.form.dateFin) return
    const start  = new Date(contract.form.dateDebut)
    const end    = new Date(contract.form.dateFin)
    const months = (end.getFullYear() - start.getFullYear()) * 12
                 + (end.getMonth() - start.getMonth())
    if (!isNaN(months) && months > 0) {
        contract.form.months = months
        contract.syncFromMonths()
    }
}

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([
        loadProfile(),
        clientsStore.fetchAll(),
        articlesStore.fetchAll(),
    ])
})
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl mx-auto">

    <!-- Page header -->
    <div>
      <h1 class="font-serif text-2xl" style="color:var(--app-text)">
        Nouveau <em class="italic" style="color:#c8a96e">Contrat</em>
      </h1>
      <p class="text-sm mt-1" style="color:var(--app-text-muted)">
        Étape {{ step }} sur {{ totalSteps }}
      </p>
    </div>

    <!-- Progress bar -->
    <div class="flex items-center gap-2">
      <div
        v-for="s in totalSteps" :key="s"
        class="h-1.5 flex-1 rounded-full transition-all duration-300"
        :style="`background: ${s <= step ? '#c8a96e' : 'var(--app-border)'}`"
      />
    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         STEP 1 — Domiciliataire profile + address + contract title
    ══════════════════════════════════════════════════════════════════════ -->
    <div v-if="step === 1" class="space-y-4">

      <!-- Profile status banner -->
      <div
        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm"
        :style="profileLoaded
          ? 'background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#22c55e'
          : 'background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);color:#f59e0b'"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <template v-if="profileLoaded">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </template>
          <template v-else>
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </template>
        </svg>
        <span>
          {{ profileLoaded ? 'Profil chargé automatiquement' : 'Profil incomplet —' }}
          <NuxtLink to="/admin/profile" class="underline ml-1" style="opacity:0.8">
            {{ profileLoaded ? 'Modifier →' : 'Compléter votre profil →' }}
          </NuxtLink>
        </span>
      </div>

      <!-- Domiciliataire read-only summary — now includes email and telephone -->
      <div class="card p-5">
        <p class="text-xs uppercase tracking-widest font-bold mb-4" style="color:#c8a96e">
          Domiciliataire (depuis votre profil)
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Société</p>
            <p class="font-medium" style="color:var(--app-text)">{{ contract.form.companyName || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Représentant</p>
            <p class="font-medium" style="color:var(--app-text)">{{ contract.form.companyRepresentant || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">CIN</p>
            <p style="color:var(--app-text)">{{ contract.form.companyCIN || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">RC</p>
            <p style="color:var(--app-text)">{{ contract.form.companyRC || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">IF</p>
            <p style="color:var(--app-text)">{{ contract.form.companyIF || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">TP</p>
            <p style="color:var(--app-text)">{{ contract.form.companyTP || '—' }}</p>
          </div>
          <!-- ✅ NEW: email and telephone now shown in step 1 summary -->
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Email</p>
            <p class="truncate" style="color:var(--app-text)">{{ contract.form.companyEmail || '—' }}</p>
          </div>
          <div>
            <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Téléphone</p>
            <p style="color:var(--app-text)">{{ contract.form.companyTelephone || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Dynamic contract title -->
      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
          Titre du contrat
        </p>
        <div>
          <label class="f-label">Intitulé affiché sur le document PDF</label>
          <input
            v-model="contract.form.titreContrat"
            class="f-input"
            placeholder="Contrat de Domiciliation"
            maxlength="255"
          />
          <p class="text-[10px] mt-1" style="color:var(--app-text-faint)">
            Ce texte apparaît centré en tête du PDF. Laissez vide pour utiliser
            le titre par défaut « Contrat de Domiciliation ».
          </p>
        </div>
        <!-- Live preview -->
        <div
          v-if="contract.form.titreContrat.trim()"
          class="rounded-xl px-4 py-3 text-sm text-center font-semibold tracking-wide"
          style="background:rgba(200,169,110,0.08);border:1px solid rgba(200,169,110,0.2);color:#c8a96e"
        >
          Aperçu : {{ contract.form.titreContrat }}
        </div>
        <div>
          <label class="f-label">
            Numéro d'instruction
            <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">(optionnel)</span>
          </label>
          <input v-model="contract.form.instruction_no" class="f-input" placeholder="INS-2026-001" />
        </div>
      </div>

      <!-- Address chip selector -->
      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Adresse de domiciliation *
          </p>
          <NuxtLink to="/admin/profile" class="text-[10px] underline"
                    style="color:var(--app-text-faint)" target="_blank">
            Gérer les adresses →
          </NuxtLink>
        </div>

        <div v-if="addresses.length > 0" class="space-y-3">
          <p class="text-xs" style="color:var(--app-text-faint)">
            Sélectionnez l'adresse qui apparaîtra sur ce contrat :
          </p>
          <div class="flex flex-col gap-2">
            <button
              v-for="addr in addresses" :key="addr.value"
              type="button"
              class="w-full text-left rounded-xl px-4 py-3 transition-all text-sm"
              :style="selectedAddress === addr.value
                ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e;color:var(--app-text)'
                : 'background:var(--app-surface-2);border:2px solid var(--app-border);color:var(--app-text-muted)'"
              @click="pickAddress(addr)"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-semibold text-xs uppercase tracking-wide mb-0.5"
                     :style="selectedAddress === addr.value ? 'color:#c8a96e' : 'color:var(--app-text-faint)'">
                    {{ addr.label }}
                  </p>
                  <p class="truncate" style="color:var(--app-text)">{{ addr.value }}</p>
                </div>
                <div class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center transition-all"
                     :style="selectedAddress === addr.value ? 'background:#c8a96e' : 'background:var(--app-border)'">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                       stroke="white" stroke-width="3" stroke-linecap="round">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                </div>
              </div>
            </button>
          </div>
          <div v-if="selectedAddress"
               class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs"
               style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.15);color:#22c55e">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            {{ selectedAddress }}
          </div>
        </div>

        <!-- Fallback: manual input when profile has no addresses -->
        <div v-else class="space-y-3">
          <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm"
               style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);color:#f59e0b">
            <svg class="shrink-0 mt-0.5" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span>
              Aucune adresse enregistrée dans votre profil.
              <NuxtLink to="/admin/profile" class="underline ml-1" target="_blank">
                Ajouter des adresses →
              </NuxtLink>
            </span>
          </div>
          <div>
            <label class="f-label">Saisir l'adresse manuellement *</label>
            <input v-model="selectedAddress" class="f-input"
                   placeholder="Ex : Rue Mohammed V, Résidence Atlas, 80000"
                   @input="contract.form.companyAdresse = selectedAddress" />
          </div>
        </div>
      </div>

    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         STEP 2 — Client selection / creation
    ══════════════════════════════════════════════════════════════════════ -->
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

      <!-- Existing client mode -->
      <div v-if="clientMode === 'select'" class="space-y-4">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
               width="14" height="14" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round"
               style="color:var(--app-text-faint)">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
          </svg>
          <input v-model="clientSearchQuery" class="f-input pl-9"
                 placeholder="Rechercher par raison sociale ou email..." />
        </div>

        <div class="space-y-2 max-h-64 overflow-y-auto">
          <div v-for="client in filteredClients" :key="client.id"
               class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
               :style="selectedClientId === client.id
                 ? 'background:rgba(200,169,110,0.12);border:2px solid #c8a96e'
                 : 'background:var(--app-surface-2);border:2px solid var(--app-border)'"
               @click="selectClient(client)">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
                 style="background:rgba(200,169,110,0.15);color:#c8a96e">
              {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-sm truncate" style="color:var(--app-text)">
                {{ client.raison_sociale }}
              </p>
              <p class="text-xs truncate" style="color:var(--app-text-muted)">
                {{ client.client_user?.email ?? '' }}
                <span v-if="client.ville"> · {{ client.ville }}</span>
              </p>
            </div>
            <div v-if="selectedClientId === client.id" class="shrink-0">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="#c8a96e" stroke-width="2.5" stroke-linecap="round">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
            </div>
          </div>
          <div v-if="filteredClients.length === 0 && clientSearchQuery"
               class="text-center py-6 rounded-xl"
               style="background:var(--app-surface-2);border:2px dashed var(--app-border);color:var(--app-text-faint)">
            <p class="text-sm mb-2">Aucun client trouvé pour "{{ clientSearchQuery }}"</p>
            <button type="button" class="btn btn-gold btn-sm" @click="switchToCreate">
              + Créer ce client maintenant
            </button>
          </div>
        </div>

        <!-- Selected client summary card — now shows dateNaissance -->
        <div v-if="selectedClient" class="card p-5 space-y-3">
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#22c55e">
            ✓ Client sélectionné
          </p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div>
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Société</p>
              <p class="font-medium" style="color:var(--app-text)">{{ selectedClient.raison_sociale }}</p>
            </div>
            <div v-if="selectedClient.forme_juridique">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Forme juridique</p>
              <p style="color:var(--app-text)">{{ selectedClient.forme_juridique }}</p>
            </div>
            <div v-if="selectedClient.ville">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Ville</p>
              <p style="color:var(--app-text)">{{ selectedClient.ville }}</p>
            </div>
            <div v-if="contract.form.gerantNom">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Gérant</p>
              <p style="color:var(--app-text)">{{ contract.form.gerantNom }}</p>
            </div>
            <div v-if="contract.form.gerantCIN">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">CIN / Passeport</p>
              <p style="color:var(--app-text)">{{ contract.form.gerantCIN }}</p>
            </div>
            <div v-if="contract.form.tel">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Téléphone</p>
              <p style="color:var(--app-text)">{{ contract.form.tel }}</p>
            </div>
            <div v-if="contract.form.email">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Email</p>
              <p class="truncate" style="color:var(--app-text)">{{ contract.form.email }}</p>
            </div>
            <div v-if="contract.form.adressePerso">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Adresse personnelle</p>
              <p style="color:var(--app-text)">{{ contract.form.adressePerso }}</p>
            </div>
            <!-- ✅ NEW: date de naissance now shown in existing-client summary -->
            <div v-if="contract.form.dateNaissance">
              <p class="text-xs mb-0.5" style="color:var(--app-text-faint)">Date de naissance</p>
              <p style="color:var(--app-text)">{{ contract.form.dateNaissance }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- New client creation form — includes date de naissance -->
      <div v-else-if="clientMode === 'create'" class="card p-5 space-y-4">
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
          Informations du nouveau client
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="f-label">Raison sociale *</label>
            <input v-model="newClientForm.raison_sociale" class="f-input" required
                   placeholder="ATLAS IMPORT EXPORT SARL" />
          </div>
          <div>
            <label class="f-label">Forme juridique</label>
            <input v-model="newClientForm.forme_juridique" class="f-input" placeholder="SARL, SA..." />
          </div>
          <div>
            <label class="f-label">Nom du gérant *</label>
            <input v-model="newClientForm.gerantNom" class="f-input" required placeholder="Nom complet" />
          </div>
          <div>
            <label class="f-label">CIN / Passeport</label>
            <input v-model="newClientForm.gerantCIN" class="f-input" placeholder="BJ422176" />
          </div>
          <!-- ✅ date de naissance — required by certain contract clause tokens -->
          <div>
            <label class="f-label">Date de naissance du gérant</label>
            <input v-model="newClientForm.dateNaissance" type="date" class="f-input" />
          </div>
          <div>
            <label class="f-label">Téléphone</label>
            <input v-model="newClientForm.tel" class="f-input" placeholder="+212 6XX XXX XXX" />
          </div>
          <div>
            <label class="f-label">Email (accès portail) *</label>
            <input v-model="newClientForm.email" type="email" class="f-input" required
                   placeholder="client@exemple.ma" />
          </div>
          <div>
            <label class="f-label">Mot de passe portail *</label>
            <input v-model="newClientForm.password" type="password" class="f-input" required
                   placeholder="Min. 8 caractères" />
          </div>
          <div class="sm:col-span-2">
            <label class="f-label">Adresse personnelle du gérant</label>
            <input v-model="newClientForm.adressePerso" class="f-input"
                   placeholder="Adresse de résidence du gérant" />
          </div>
        </div>
        <p class="text-xs" style="color:var(--app-text-faint)">
          Un compte portail sera créé avec cet email et ce mot de passe.
        </p>
      </div>

    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         STEP 3 — Articles + financial fields
    ══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="step === 3" class="space-y-5">

      <!-- Article chip library -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
          <div>
            <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
              Bibliothèque d'articles
            </p>
            <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">
              Cliquez pour ajouter au contrat
            </p>
          </div>
          <NuxtLink to="/admin/articles" class="text-xs underline"
                    style="color:var(--app-text-faint)" target="_blank">
            Gérer les articles →
          </NuxtLink>
        </div>

        <div class="flex flex-wrap gap-2">
          <!--
            CRITICAL: :style reads selectedArticleIds.includes(String(article.id))
            — NEVER any flag from the article object itself.
            articlesStore.items is shared; a flag on the object would affect
            all chip renders simultaneously.
          -->
          <button
            v-for="article in articlesStore.items"
            :key="String(article.id)"
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
            :style="selectedArticleIds.includes(String(article.id))
              ? 'background:#c8a96e;color:#111;border-color:#c8a96e'
              : 'background:var(--app-surface-2);border-color:var(--app-border);color:var(--app-text-muted)'"
            @click="toggleArticle(article)"
          >
            {{ selectedArticleIds.includes(String(article.id)) ? '✓ ' : '+ ' }}{{ article.title }}
          </button>

          <p v-if="articlesStore.items.length === 0" class="text-sm" style="color:var(--app-text-faint)">
            Aucun article dans la bibliothèque.
            <NuxtLink to="/admin/articles" class="underline">Créer des articles →</NuxtLink>
          </p>
        </div>
      </div>

      <!-- Selected articles: drag-to-reorder + inline body editor -->
      <div v-if="orderedArticles.length > 0" class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
              Articles sélectionnés — {{ orderedArticles.length }} article(s)
            </p>
            <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">
              Glissez pour réordonner · ∨ pour modifier le texte
            </p>
          </div>
        </div>

        <div class="space-y-3">
          <div
            v-for="(article, index) in orderedArticles"
            :key="String(article.id)"
            draggable="true"
            class="rounded-xl transition-all"
            :style="`border: 2px solid ${dragIndex === index ? '#c8a96e' : 'var(--app-border)'};
                     opacity: ${dragIndex === index ? 0.45 : 1};
                     background: var(--app-surface-2);`"
            @dragstart="onDragStart(index, $event)"
            @dragover="onDragOver(index, $event)"
            @drop="onDrop(index)"
            @dragend="onDragEnd"
          >
            <div class="flex items-center gap-3 px-4 py-3">
              <svg class="shrink-0 cursor-grab active:cursor-grabbing" width="16" height="16"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                   stroke-linecap="round" style="color:var(--app-text-faint)">
                <circle cx="9" cy="5"  r="1"/><circle cx="15" cy="5"  r="1"/>
                <circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/>
                <circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/>
              </svg>
              <span class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0"
                    style="background:rgba(200,169,110,0.2);color:#c8a96e">
                {{ article.ordre }}
              </span>
              <input
                v-model="article.title"
                class="flex-1 min-w-0 bg-transparent border-none outline-none font-semibold text-sm"
                style="color:var(--app-text)"
                :placeholder="`Titre de l'article ${article.ordre}`"
                @click.stop
              />
              <button type="button"
                      class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
                      @click.stop="article._expanded = !article._expanded">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                     :style="`transform:rotate(${article._expanded ? 180 : 0}deg);transition:transform 0.2s`">
                  <path d="M6 9l6 6 6-6"/>
                </svg>
              </button>
              <button type="button"
                      class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center"
                      style="color:#ef4444"
                      @click.stop="toggleArticle(article)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                  <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <Transition name="expand-body">
              <div v-if="article._expanded" class="px-4 pb-4 pt-1"
                   style="border-top:1px solid var(--app-border-2)"
                   @dragstart.stop @dragover.stop>
                <label class="f-label mb-2">Corps de l'article</label>
                <textarea v-model="article.body" class="f-input resize-none" rows="6"
                          :placeholder="`Texte de l'article ${article.ordre}...`"
                          @click.stop @mousedown.stop />
                <div class="flex items-center justify-between mt-2">
                  <p class="text-[10px]" style="color:var(--app-text-faint)">
                    {{ article.body?.length ?? 0 }} caractères
                  </p>
                  <button type="button" class="text-[11px] underline"
                          style="color:var(--app-text-faint)"
                          @click="resetArticleBody(article)">
                    Réinitialiser depuis la bibliothèque
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <!-- PDF order summary -->
        <div class="mt-4 rounded-xl p-4"
             style="background:var(--app-surface);border:1px solid var(--app-border)">
          <p class="text-[10px] uppercase tracking-widest font-bold mb-2"
             style="color:var(--app-text-faint)">
            Ordre dans le PDF
          </p>
          <div class="flex flex-wrap gap-2">
            <span v-for="a in orderedArticles" :key="String(a.id)"
                  class="text-xs px-2 py-1 rounded-lg font-medium"
                  style="background:rgba(200,169,110,0.1);color:#c8a96e;border:1px solid rgba(200,169,110,0.2)">
              Art. {{ a.ordre }} — {{ a.title }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="rounded-xl p-8 text-center"
           style="background:var(--app-surface-2);border:2px dashed var(--app-border);color:var(--app-text-faint)">
        <svg class="mx-auto mb-3 opacity-40" width="32" height="32" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        <p class="font-medium mb-1">Aucun article sélectionné</p>
        <p class="text-sm">Le PDF sera généré sans clauses contractuelles.</p>
      </div>

      <!-- Financial fields -->
      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
          Durée et montants
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="f-label">Date de début</label>
            <input :value="contract.form.dateDebut" type="date" class="f-input"
                   @change="contract.setDateDebut(($event.target as HTMLInputElement).value)" />
          </div>
          <div>
            <label class="f-label">
              Date de fin
              <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">(calculée automatiquement)</span>
            </label>
            <input :value="contract.form.dateFin" type="date" class="f-input"
                   @change="contract.form.dateFin = ($event.target as HTMLInputElement).value; recalcMonths()" />
          </div>
          <div>
            <label class="f-label">Durée (mois)</label>
            <input :value="contract.form.months" type="number" min="1" class="f-input"
                   @input="contract.setMonths(Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="f-label">Redevance mensuelle (DH)</label>
            <input :value="contract.form.redevanceMensuelle" type="number" min="0" step="0.01" class="f-input"
                   @input="contract.setMonthly(Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="f-label">
              Redevance annuelle (DH)
              <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">(auto)</span>
            </label>
            <input :value="contract.form.redevanceAnnuelle" type="number" min="0" step="0.01" class="f-input"
                   @input="contract.setAnnual(Number(($event.target as HTMLInputElement).value))" />
          </div>
          <div>
            <label class="f-label">Mode de paiement</label>
            <select v-model="contract.form.mode_paiement" class="f-input">
              <option value="">--</option>
              <option>Virement</option>
              <option>Espèces</option>
              <option>Chèque</option>
              <option>Carte bancaire</option>
            </select>
          </div>
          <div>
            <label class="f-label">Caution (DH)</label>
            <input v-model="contract.form.caution" type="number" min="0" class="f-input" />
          </div>
          <div>
            <label class="f-label">Ville de signature</label>
            <input v-model="contract.form.ville_signature" class="f-input" placeholder="Agadir" />
          </div>
          <div>
            <label class="f-label">Date de signature</label>
            <input v-model="contract.form.date_signature" type="date" class="f-input" />
          </div>
        </div>
      </div>

    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         STEP 4 — Confirmation + PDF preview + renewal
    ══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="step === 4" class="space-y-4">
      <div class="card p-6 text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto"
             style="background:rgba(34,197,94,0.1)">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none"
               stroke="#22c55e" stroke-width="2" stroke-linecap="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <div>
          <h2 class="font-serif text-xl" style="color:var(--app-text)">Contrat enregistré</h2>
          <p class="text-sm mt-1" style="color:var(--app-text-muted)">
            Contrat #{{ contratId }} —
            <em style="color:#c8a96e">
              {{ contract.form.titreContrat || 'Contrat de Domiciliation' }}
            </em>
            — statut : brouillon
          </p>
        </div>

        <!-- PDF actions -->
        <button class="btn btn-gold btn-lg w-full sm:w-auto" @click="preparePdf">
          Préparer le PDF
        </button>
        <div v-if="pdfStreamUrl" class="flex flex-col sm:flex-row gap-3 justify-center">
          <button class="btn btn-outline btn-md" @click="openPreview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            Aperçu PDF
          </button>
          <a :href="buildStreamUrl('download')" target="_blank" class="btn btn-gold btn-md">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Télécharger PDF
          </a>
        </div>

        <!-- Navigation and renewal -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
          <NuxtLink to="/admin/contrats" class="btn btn-outline btn-md">
            Voir tous les contrats →
          </NuxtLink>
          <!-- ✅ NEW: Renew contract button -->
          <button
            class="btn btn-outline btn-md"
            :disabled="renewing"
            @click="renewContract"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
            </svg>
            {{ renewing ? 'Renouvellement...' : 'Renouveler le contrat' }}
          </button>
        </div>
      </div>
    </div>


    <!-- Navigation buttons (steps 1–3) -->
    <div v-if="step < 4" class="flex justify-between gap-3 pt-2">
      <button v-if="step > 1" type="button" class="btn btn-outline btn-md" @click="prevStep">
        ← Retour
      </button>
      <div v-else />
      <button v-if="step === 3" type="button" class="btn btn-gold btn-md ml-auto"
              :disabled="saving" @click="saveDraft">
        {{ saving ? 'Enregistrement...' : 'Enregistrer le contrat →' }}
      </button>
      <button v-else type="button" class="btn btn-gold btn-md ml-auto"
              :disabled="saving" @click="nextStep">
        {{ saving ? 'Patientez...' : 'Suivant →' }}
      </button>
    </div>


    <!-- PDF fullscreen preview modal -->
    <ClientOnly>
      <Teleport to="body">
        <div v-if="showPreview" class="fixed inset-0 z-300 flex flex-col"
             style="background:rgba(0,0,0,0.92)">
          <div class="flex items-center justify-between px-5 py-3 shrink-0"
               style="background:rgba(0,0,0,0.6);border-bottom:1px solid rgba(255,255,255,0.1)">
            <span class="font-medium text-white">
              Contrat #{{ contratId }} — {{ contract.form.titreContrat || 'Contrat de Domiciliation' }}
            </span>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                    style="background:rgba(255,255,255,0.1)" @click="closePreview">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="flex-1 relative">
            <div v-if="pdfLoading" class="absolute inset-0 flex items-center justify-center"
                 style="background:rgba(0,0,0,0.5);z-index:1">
              <div class="text-center text-white">
                <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto mb-3"/>
                <p class="text-sm">Chargement du PDF...</p>
              </div>
            </div>
            <!-- iframe loads from the public stream route outside auth:sanctum -->
            <iframe :src="pdfStreamUrl" class="w-full h-full"
                    style="border:none;display:block" @load="pdfLoading = false" />
          </div>
        </div>
      </Teleport>
    </ClientOnly>

  </div>
</template> 
