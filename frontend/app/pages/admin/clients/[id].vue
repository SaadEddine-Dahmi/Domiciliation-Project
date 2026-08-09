<script setup lang="ts">
// pages/admin/clients/[id].vue
//
// Client detail page. Sections:
//   1. Company + représentant summary, with an inline edit modal
//      auto-filled from the loaded client.
//   2. Contracts linked to this client, with live preview via the
//      shared ContratPreviewModal component (openLive).
//   3. Documents belonging to this client — list, upload, preview,
//      download, delete — scoped to this entreprise_id.

import { useClientsStore } from '~/stores/clients'
import ContratPreviewModal from '~/components/ContratPreviewModal.vue'

definePageMeta({
  layout: 'dashboard',
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const clientsStore = useClientsStore()
const { success, error: toastError } = useToast()

const clientId = computed(() => Number(route.params.id))

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return { Accept: 'application/json' }
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return { Accept: 'application/json' }
    const parsed = JSON.parse(raw)
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (parsed?.token) headers.Authorization = `Bearer ${parsed.token}`
    return headers
  } catch {
    return { Accept: 'application/json' }
  }
}

function withToken(url: string): string {
  if (!import.meta.client) return url
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return url
    const parsed = JSON.parse(raw)
    const token = parsed?.token
    if (!token) return url
    const sep = url.includes('?') ? '&' : '?'
    return `${url}${sep}token=${encodeURIComponent(token)}`
  } catch {
    return url
  }
}

// ── State ─────────────────────────────────────────────────
const client = ref<any>(null)
const documents = ref<any[]>([])
const docTypes = ref<any[]>([])
const contrats = ref<any[]>([])
const loading = ref(true)

const previewUrl = ref<string | null>(null)
const isPreviewOpen = ref(false)
const isPdf = ref(false)

// Shared contract preview component reference
const pdfPreview = ref()

function isImage(name: string): boolean {
  if (!name) return false
  return /\.(jpg|jpeg|png|webp|gif)$/i.test(name)
}

function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

// ── Fetchers ──────────────────────────────────────────────
async function loadAllData() {
  if (!clientId.value) return
  loading.value = true
  try {
    await Promise.all([fetchClient(), fetchDocuments(), fetchDocTypes(), fetchContrats()])
  } finally {
    loading.value = false
  }
}

async function fetchClient() {
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/clients/${clientId.value}`,
      { headers: authHeaders() }
    )
    client.value = res.data
  } catch {
    toastError?.('Client introuvable')
    router.push('/admin/clients')
  }
}

async function fetchDocuments() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/documents?entreprise_id=${clientId.value}`,
      { headers: authHeaders() }
    )
    documents.value = res.data ?? []
  } catch {
    documents.value = []
  }
}

async function fetchDocTypes() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/document-types`,
      { headers: authHeaders() }
    )
    docTypes.value = res.data ?? []
  } catch {
    docTypes.value = []
  }
}

async function fetchContrats() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/contrats?entreprise_id=${clientId.value}`,
      { headers: authHeaders() }
    )
    contrats.value = res.data ?? []
  } catch {
    contrats.value = []
  }
}

watch(() => route.params.id, () => loadAllData())
onMounted(() => loadAllData())

onBeforeUnmount(() => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
})

// ── Edit client modal ───────────────────────────────────────
const showEditModal = ref(false)
const savingEdit = ref(false)
const editServerError = ref('')

const editForm = reactive({
  raison_sociale: '',
  gerant_nom: '',
  gerant_prenom: '',
  gerant_email: '',
  gerant_telephone: '',
  gerant_date_naissance: '',
  gerant_adresse: '',
  gerant_cin: '',
})

const statutColor: Record<string, string> = {
  actif: '#22c55e',
  inactif: '#ef4444',
  suspendu: '#f59e0b',
}

function openEditModal(): void {
  if (!client.value) {
    toastError?.('Client non chargé, réessayez.')
    return
  }
  editServerError.value = ''

  const rep = client.value.representant ?? {}

  editForm.raison_sociale        = client.value.raison_sociale ?? ''
  editForm.gerant_nom            = rep.nom ?? ''
  editForm.gerant_prenom         = rep.prenom ?? ''
  editForm.gerant_email          = rep.email ?? ''
  editForm.gerant_telephone      = rep.telephone ?? ''
  editForm.gerant_date_naissance = rep.date_naissance ?? ''
  editForm.gerant_adresse        = rep.adresse ?? ''
  editForm.gerant_cin            = rep.cin ?? ''

  showEditModal.value = true
}

async function submitEdit(): Promise<void> {
  if (!client.value) return
  savingEdit.value = true
  editServerError.value = ''
  try {
    await clientsStore.update(client.value.id, {
      raison_sociale: editForm.raison_sociale,
    })

    const repPayload = {
      nom: editForm.gerant_nom,
      prenom: editForm.gerant_prenom,
      cin: editForm.gerant_cin,
      date_naissance: editForm.gerant_date_naissance || undefined,
      adresse: editForm.gerant_adresse || undefined,
      telephone: editForm.gerant_telephone || undefined,
      email: editForm.gerant_email || undefined,
    }

    if (client.value.representant) {
      await clientsStore.updateRepresentant(client.value.id, repPayload)
    } else {
      await clientsStore.createRepresentant(client.value.id, repPayload)
    }

    success('Client mis à jour')
    showEditModal.value = false
    await fetchClient()
  } catch (e: any) {
    editServerError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    toastError?.(editServerError.value)
  } finally {
    savingEdit.value = false
  }
}

// ── Documents ───────────────────────────────────────────────
const showUpload = ref(false)
const uploading = ref(false)
const uploadError = ref('')

const uploadForm = reactive({
  document_type_id: '',
  date_expiration: '',
  file: null as File | null,
})

function openUploadModal(): void {
  Object.assign(uploadForm, { document_type_id: '', date_expiration: '', file: null })
  uploadError.value = ''
  showUpload.value = true
}

function onFileChange(e: Event): void {
  uploadForm.file = (e.target as HTMLInputElement).files?.[0] ?? null
}

async function submitUpload(): Promise<void> {
  if (!uploadForm.file || !uploadForm.document_type_id) {
    uploadError.value = 'Sélectionnez un type et un fichier'
    return
  }

  uploading.value = true
  uploadError.value = ''
  try {
    const fd = new FormData()
    fd.append('entreprise_id', String(clientId.value))
    fd.append('document_type_id', uploadForm.document_type_id)
    fd.append('file', uploadForm.file)
    if (uploadForm.date_expiration) fd.append('date_expiration', uploadForm.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method: 'POST',
      headers: authHeaders(),
      body: fd,
    })

    success('Document importé')
    showUpload.value = false
    await fetchDocuments()
  } catch (e: any) {
    uploadError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? "Erreur lors de l'import"
    toastError?.(uploadError.value)
  } finally {
    uploading.value = false
  }
}

async function deleteDoc(id: number): Promise<void> {
  if (!confirm('Supprimer ce document ?')) return
  try {
    await $fetch(`${getApiBase()}/api/documents/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    documents.value = documents.value.filter(d => d.id !== id)
    success('Document supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

async function downloadDoc(doc: any) {
  try {
    const baseUrl = doc?.download_url || `${getApiBase()}/api/documents/${doc.id}/download`
    const url = withToken(baseUrl)

    const res = await fetch(url, { method: 'GET' })
    if (!res.ok) throw new Error()

    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = doc.name || `document-${doc.id}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(blobUrl)
  } catch {
    toastError?.('Erreur de téléchargement')
  }
}

async function previewDoc(doc: any) {
  try {
    const baseUrl = doc?.preview_url || `${getApiBase()}/api/documents/${doc.id}/preview`
    const url = withToken(baseUrl)

    const res = await fetch(url, { method: 'GET' })
    if (!res.ok) throw new Error()

    const blob = await res.blob()
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    isPdf.value = doc?.is_pdf === true || contentType.includes('application/pdf')

    previewUrl.value = URL.createObjectURL(blob)
    isPreviewOpen.value = true
  } catch {
    toastError?.('Session expirée ou non autorisée')
  }
}

function closePreview() {
  isPreviewOpen.value = false
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

// ── Contracts display ────────────────────────────────────────
const contratStatutLabel: Record<string, string> = {
  draft: 'Brouillon',
  active: 'Actif',
  expired: 'Expiré',
  terminated: 'Résilié',
}

const contratStatutColor: Record<string, string> = {
  draft: 'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
  expired: 'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

/**
 * Opens the shared ContratPreviewModal using openLive() — renders the
 * contract from in-memory data (no backend HTML/PDF request), exactly
 * like the wizard's live preview.
 */
function openContratPreview(c: any): void {
  if (!pdfPreview.value || typeof pdfPreview.value.openLive !== 'function') return

  pdfPreview.value.openLive({
    titreContrat: c.titre_contrat,
    instruction_no: c.instruction_no,
    duree_mois: c.duree_mois,
    date_debut: c.date_debut,
    date_fin: c.date_fin,
    date_signature: c.date_signature,
    redevanceMensuelle: c.prix_mensuel,
    redevanceAnnuelle: c.prix_total,
    mode_paiement: c.mode_paiement,
    caution: c.caution,
    ville_signature: c.ville_signature,

    societe: c.entreprise?.raison_sociale ?? client.value?.raison_sociale,
    forme_juridique: c.entreprise?.forme_juridique ?? client.value?.forme_juridique,
    ville_client: c.entreprise?.ville ?? client.value?.ville,
    gerantNom: c.entreprise?.representant?.nom_complet || client.value?.representant?.nom_complet,
    gerantCIN: c.entreprise?.representant?.cin ?? client.value?.representant?.cin,
    tel: c.entreprise?.representant?.telephone ?? client.value?.representant?.telephone,
    email: c.entreprise?.representant?.email ?? client.value?.representant?.email,
    adressePerso: c.entreprise?.representant?.adresse ?? client.value?.representant?.adresse,

    articles: c.articles || [],
  }, c.titre_contrat ?? `Contrat #${c.id}`)
}
</script>

<template>
  <div class="space-y-6 animate-fade-up">
    <button
      class="flex items-center gap-2 text-sm nav-inactive transition-colors"
      @click="router.push('/admin/clients')"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M19 12H5M12 5l-7 7 7 7" />
      </svg>
      Retour aux clients
    </button>

    <div v-if="loading" class="card p-6 animate-pulse space-y-3">
      <div class="h-6 w-1/3 rounded" style="background: var(--app-border)" />
      <div class="h-4 w-1/4 rounded" style="background: var(--app-border)" />
    </div>

    <template v-else-if="client">
      <!-- ── Header card ─────────────────────────────────── -->
      <div class="card p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
          <div class="flex items-center gap-4">
            <div
              class="w-14 h-14 rounded-2xl flex items-center justify-center font-bold text-lg shrink-0"
              style="background: rgba(200,169,110,0.15); color: #c8a96e"
            >
              {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
            </div>
            <div>
              <h1 class="font-serif text-2xl" style="color: var(--app-text)">
                {{ client.raison_sociale }}
              </h1>
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span v-if="client.forme_juridique" class="text-sm" style="color: var(--app-text-muted)">
                  {{ client.forme_juridique }}
                </span>
                <span
                  v-if="client.statut"
                  class="text-xs px-2 py-0.5 rounded-full font-semibold"
                  :style="`color: ${statutColor[client.statut] ?? '#94a3b8'}; background: ${statutColor[client.statut] ?? '#94a3b8'}18`"
                >{{ client.statut }}</span>
              </div>
            </div>
          </div>

          <button class="btn btn-gold btn-sm" @click="openEditModal">
            ✎ Modifier
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 pt-5"
             style="border-top: 1px solid var(--app-border-2)">
          <div v-if="client.adresse">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">Adresse</p>
            <p class="text-sm" style="color: var(--app-text)">{{ client.adresse }}</p>
          </div>
          <div v-if="client.ville">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">Ville</p>
            <p class="text-sm" style="color: var(--app-text)">{{ client.ville }}</p>
          </div>
          <div v-if="client.capital">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">Capital</p>
            <p class="text-sm" style="color: var(--app-text)">{{ Number(client.capital).toLocaleString('fr-MA') }} DH</p>
          </div>
        </div>
      </div>

      <!-- ── Client information block ──────────────── -->
      <div class="card p-6">
        <p class="text-xs uppercase tracking-widest font-bold mb-4" style="color:#c8a96e">
          Informations du client
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div>
            <p class="text-xs font-bold mb-2" style="color: var(--app-text-faint)">Représentant légal</p>
            <div v-if="client.representant" class="space-y-2 text-sm">
              <p style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Nom :</span>
                {{ client.representant.prenom }} {{ client.representant.nom }}
              </p>
              <p v-if="client.representant.cin" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">CIN / Passeport :</span> {{ client.representant.cin }}
              </p>
              <p v-if="client.representant.telephone" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Téléphone :</span> {{ client.representant.telephone }}
              </p>
              <p v-if="client.representant.email" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Email :</span> {{ client.representant.email }}
              </p>
              <p v-if="client.representant.nationalite" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Nationalité :</span> {{ client.representant.nationalite }}
              </p>
              <p v-if="client.representant.date_naissance" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Date de naissance :</span> {{ fmt(client.representant.date_naissance) }}
              </p>
              <p v-if="client.representant.adresse" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Adresse :</span> {{ client.representant.adresse }}
              </p>
            </div>
            <p v-else class="text-sm" style="color: var(--app-text-faint)">
              Aucun représentant enregistré.
            </p>
          </div>

          <div>
            <p class="text-xs font-bold mb-2" style="color: var(--app-text-faint)">Compte accès portail</p>
            <div v-if="client.client_user" class="space-y-2 text-sm">
              <p style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Nom :</span>
                {{ client.client_user.nom }} {{ client.client_user.prenom }}
              </p>
              <p v-if="client.client_user.email" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Email :</span> {{ client.client_user.email }}
              </p>
              <p v-if="client.client_user.telephone" style="color: var(--app-text)">
                <span style="color: var(--app-text-faint)">Téléphone :</span> {{ client.client_user.telephone }}
              </p>
            </div>
            <p v-else class="text-sm" style="color: var(--app-text-faint)">
              Aucun compte portail lié à ce client.
            </p>
          </div>
        </div>
      </div>

      <!-- ── Contracts section ────────────────────────────── -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-serif text-xl" style="color: var(--app-text)">Contrats</h2>
          <NuxtLink to="/admin/contrat?new=1" class="btn btn-outline btn-sm">+ Nouveau contrat</NuxtLink>
        </div>

        <div v-if="contrats.length" class="space-y-2">
          <div v-for="c in contrats" :key="c.id" class="card p-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-sm" style="color: var(--app-text)">{{ c.titre_contrat ?? `Contrat #${c.id}` }}</p>
              <span class="text-xs px-2 py-0.5 rounded-full font-medium mt-1 inline-block"
                    :class="contratStatutColor[c.statut] ?? 'text-app-text/40 bg-white/5'">
                {{ contratStatutLabel[c.statut] ?? c.statut }}
              </span>
            </div>
            <button class="btn btn-outline btn-sm shrink-0" @click="openContratPreview(c)">
              Voir
            </button>
          </div>
        </div>
        <div v-else class="card p-8 text-center" style="color:var(--app-text-faint)">
          Aucun contrat pour ce client.
        </div>
      </div>

      <!-- ── Documents section ────────────────────────────── -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-serif text-xl" style="color: var(--app-text)">Documents</h2>
          <button class="btn btn-gold btn-md" @click="openUploadModal">+ Importer</button>
        </div>

        <div v-if="documents.length" class="space-y-2">
          <div v-for="doc in documents" :key="doc.id" class="card p-4 flex items-center gap-4 flex-wrap">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 overflow-hidden" style="background: rgba(200,169,110,0.1)">
              <svg v-if="!isImage(doc.name)" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c8a96e" stroke-width="1.8">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
              </svg>
              <div v-else class="w-full h-full flex items-center justify-center text-[10px] text-gold font-bold">IMG</div>
            </div>

            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm" style="color: var(--app-text)">{{ doc.name }}</p>
              <p class="text-xs" style="color: var(--app-text-faint)">
                {{ doc.document_type?.name ?? 'Type inconnu' }} · Importé le {{ fmt(doc.created_at) }}
                <span v-if="doc.date_expiration"> · Expire le {{ fmt(doc.date_expiration) }}</span>
              </p>
            </div>

            <div class="flex items-center gap-2">
              <button @click="previewDoc(doc)" class="btn btn-outline btn-sm">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button @click="downloadDoc(doc)" class="btn btn-outline btn-sm">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
              </button>
              <button class="btn btn-danger btn-sm" @click="deleteDoc(doc.id)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
        <div v-else class="card p-8 text-center" style="color:var(--app-text-faint)">
          Aucun document importé pour ce client.
        </div>
      </div>
    </template>

    <!-- Document preview modal (images/PDF) -->
    <Teleport to="body">
      <div v-if="isPreviewOpen" class="fixed inset-0 z-[300] flex flex-col p-4 md:p-8" style="background: rgba(0,0,0,0.9)">
        <div class="flex justify-between items-center mb-4 text-white">
          <h3 class="text-lg font-serif">Aperçu du document</h3>
          <button @click="closePreview" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white">✕</button>
        </div>
        <div class="flex-1 bg-white rounded-xl overflow-hidden relative shadow-2xl">
          <iframe v-if="isPdf" :src="previewUrl || undefined" class="w-full h-full" frameborder="0" />
          <img v-else :src="previewUrl || undefined" class="max-w-full max-h-full object-contain mx-auto my-auto" />
        </div>
      </div>
    </Teleport>

    <!-- Document upload modal -->
    <Teleport to="body">
      <div v-if="showUpload" class="fixed inset-0 z-[200] flex items-center justify-center p-4" style="background: rgba(0,0,0,0.75)" @click.self="showUpload = false">
        <div class="card w-full max-w-md flex flex-col" @click.stop>
          <div class="px-6 pt-6 pb-4 flex justify-between items-center" style="border-bottom: 1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Importer un document</h2>
            <button @click="showUpload = false" class="nav-inactive">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="f-label">Type de document *</label>
              <select v-model="uploadForm.document_type_id" class="f-input">
                <option value="">-- Sélectionner --</option>
                <option v-for="t in docTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="f-label">Date d'expiration (optionnel)</label>
              <input v-model="uploadForm.date_expiration" class="f-input" type="date" />
            </div>
            <div>
              <label class="f-label">Fichier *</label>
              <input type="file" class="f-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="onFileChange" />
              <p v-if="uploadForm.file" class="text-xs mt-1 text-green-400">
                ✓ {{ uploadForm.file.name }} ({{ (uploadForm.file.size / 1024 / 1024).toFixed(2) }} Mo)
              </p>
            </div>
            <p v-if="uploadError" class="text-red-400 text-sm">{{ uploadError }}</p>
            <div class="flex gap-3 justify-end pt-1">
              <button class="btn btn-outline btn-md" @click="showUpload = false">Annuler</button>
              <button class="btn btn-gold btn-md" :disabled="uploading || !uploadForm.file" @click="submitUpload">
                {{ uploading ? 'Envoi...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Edit client modal ─────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.75)"
        @click.self="showEditModal = false"
      >
        <div class="card w-full max-w-lg max-h-[90vh] flex flex-col" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom: 1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Modifier le client</h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive" @click="showEditModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-6 py-5">
            <form class="space-y-4" @submit.prevent="submitEdit">
              <div>
                <label class="f-label">Nom de la société *</label>
                <input v-model="editForm.raison_sociale" class="f-input" required />
              </div>

              <div class="pt-1">
                <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
                  Représentant légal
                </p>
                <p class="text-xs mt-0.5" style="color: var(--app-text-faint)">
                  Informations telles qu'elles apparaissent sur son CIN ou Passeport
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="f-label">Nom *</label>
                  <input v-model="editForm.gerant_nom" class="f-input" required />
                </div>
                <div>
                  <label class="f-label">Prénom *</label>
                  <input v-model="editForm.gerant_prenom" class="f-input" required />
                </div>
                <div>
                  <label class="f-label">Email</label>
                  <input v-model="editForm.gerant_email" class="f-input" type="email" />
                </div>
                <div>
                  <label class="f-label">Téléphone</label>
                  <input v-model="editForm.gerant_telephone" class="f-input" />
                </div>
                <div>
                  <label class="f-label">Date de naissance</label>
                  <input v-model="editForm.gerant_date_naissance" class="f-input" type="date" />
                </div>
                <div>
                  <label class="f-label">CIN / Passeport *</label>
                  <input v-model="editForm.gerant_cin" class="f-input" required />
                </div>
                <div class="sm:col-span-2">
                  <label class="f-label">
                    Adresse de résidence
                    <span class="text-[10px] ml-1 font-normal" style="color: var(--app-text-faint)">
                      telle qu'inscrite sur le CIN ou Passeport
                    </span>
                  </label>
                  <input v-model="editForm.gerant_adresse" class="f-input" />
                </div>
              </div>

              <p v-if="editServerError" class="text-red-400 text-sm">{{ editServerError }}</p>

              <div class="flex gap-3 justify-end pt-1">
                <button type="button" class="btn btn-outline btn-md" @click="showEditModal = false">Annuler</button>
                <button type="submit" class="btn btn-gold btn-md" :disabled="savingEdit">
                  {{ savingEdit ? 'Enregistrement...' : 'Sauvegarder' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Shared contract preview component -->
    <ContratPreviewModal ref="pdfPreview" />
  </div>
</template>