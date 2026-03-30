<!-- ============================================================
  pages/admin/documents.vue
  Gestion des documents des clients (domiciliataire)
  - Upload de documents (CIN, RC, etc.) liés à une entreprise
  - Export / téléchargement
  - Suppression
============================================================ -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems } = storeToRefs(clientsStore)
const { success, error: toastError } = useToast()

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

// ── State ─────────────────────────────────────────────────
const documents      = ref<any[]>([])
const documentTypes  = ref<any[]>([])
const loading        = ref(true)
const showUpload     = ref(false)
const uploading      = ref(false)
const filterClient   = ref<number | null>(null)

const uploadForm = reactive({
  entreprise_id:    null as number | null,
  document_type_id: null as number | null,
  date_expiration:  '',
  file:             null as File | null,
})

// ── Chargement ────────────────────────────────────────────
async function loadAll(): Promise<void> {
  loading.value = true
  try {
    const [docsRes, typesRes] = await Promise.all([
      $fetch<{ success: boolean; data: any[] }>(`${getApiBase()}/api/documents`, { headers: authHeaders() }),
      $fetch<{ data: any[] }>(`${getApiBase()}/api/document-types`, { headers: authHeaders() }).catch(() => ({ data: [] })),
    ])
    documents.value     = docsRes.data ?? []
    documentTypes.value = typesRes.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

// ── Filtrage par client ────────────────────────────────────
const filtered = computed(() => {
  if (!filterClient.value) return documents.value
  return documents.value.filter(d => d.entreprise_id === filterClient.value)
})

// ── Upload ────────────────────────────────────────────────
function openUpload() {
  Object.assign(uploadForm, {
    entreprise_id: null, document_type_id: null,
    date_expiration: '', file: null,
  })
  showUpload.value = true
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  uploadForm.file = input.files?.[0] ?? null
}

async function submitUpload(): Promise<void> {
  if (!uploadForm.entreprise_id || !uploadForm.document_type_id || !uploadForm.file) {
    toastError?.('Remplissez tous les champs obligatoires')
    return
  }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('entreprise_id',    String(uploadForm.entreprise_id))
    fd.append('document_type_id', String(uploadForm.document_type_id))
    fd.append('file',             uploadForm.file)
    if (uploadForm.date_expiration) fd.append('date_expiration', uploadForm.date_expiration)

    await fetch(`${getApiBase()}/api/documents`, {
      method:  'POST',
      headers: authHeaders(),
      body:    fd,
    })
    success('Document importé avec succès')
    showUpload.value = false
    await loadAll()
  } catch (e: any) {
    toastError?.(e?.message ?? 'Erreur upload')
  } finally {
    uploading.value = false
  }
}

// ── Téléchargement ────────────────────────────────────────
async function downloadDoc(doc: any): Promise<void> {
  const url = `${getApiBase()}/storage/${doc.file_path}`
  try {
    const res  = await fetch(url, { headers: authHeaders() })
    const blob = await res.blob()
    const a    = document.createElement('a')
    a.href     = URL.createObjectURL(blob)
    a.download = doc.documentType?.name ?? 'document'
    document.body.appendChild(a); a.click(); document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(a.href), 3000)
  } catch { toastError?.('Erreur téléchargement') }
}

// ── Suppression ───────────────────────────────────────────
async function deleteDoc(id: number): Promise<void> {
  if (!confirm('Supprimer ce document ?')) return
  try {
    await $fetch(`${getApiBase()}/api/documents/${id}`, { method: 'DELETE', headers: authHeaders() })
    documents.value = documents.value.filter(d => d.id !== id)
    success('Document supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

onMounted(async () => {
  await clientsStore.fetchAll()
  await loadAll()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Documents <em class="text-gold italic">clients</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ documents.length }} document(s) importé(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="openUpload">+ Importer un document</button>
    </div>

    <!-- Filtre par client -->
    <div class="card p-3 flex gap-3 flex-wrap items-center">
      <label class="f-label mb-0 flex-shrink-0">Filtrer par client :</label>
      <select v-model="filterClient" class="f-input max-w-xs">
        <option :value="null">Tous les clients</option>
        <option v-for="c in clientItems" :key="c.id" :value="c.id">
          {{ c.raison_sociale }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Liste -->
    <div v-else-if="filtered.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="doc in filtered" :key="doc.id" class="card p-4 space-y-3">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-semibold text-sm truncate">{{ doc.documentType?.name ?? 'Document' }}</p>
            <p class="text-xs text-app-text/50 truncate">{{ doc.entreprise?.raison_sociale ?? '-' }}</p>
            <p class="text-xs text-app-text/40 mt-1">Ajouté le {{ formatDate(doc.created_at) }}</p>
            <p v-if="doc.date_expiration" class="text-xs text-yellow-400 mt-0.5">
              Expire le {{ formatDate(doc.date_expiration) }}
            </p>
          </div>
          <span class="text-2xl flex-shrink-0">📄</span>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-outline btn-sm flex-1" @click="downloadDoc(doc)">⬇ Exporter</button>
          <button class="btn btn-danger btn-sm" @click="deleteDoc(doc.id)">✕</button>
        </div>
      </div>
    </div>

    <!-- Vide -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📁</p>
      <p>Aucun document importé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="openUpload">Importer le premier document</button>
    </div>

    <!-- Modal Upload -->
    <div
      v-if="showUpload"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showUpload = false"
    >
      <div class="card w-full max-w-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">Importer un document</h2>
          <button class="text-app-text/40 hover:text-white text-xl" @click="showUpload = false">✕</button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="f-label">Entreprise cliente *</label>
            <select v-model="uploadForm.entreprise_id" class="f-input">
              <option :value="null" disabled>-- Sélectionner --</option>
              <option v-for="c in clientItems" :key="c.id" :value="c.id">
                {{ c.raison_sociale }}
              </option>
            </select>
          </div>

          <div>
            <label class="f-label">Type de document *</label>
            <select v-model="uploadForm.document_type_id" class="f-input">
              <option :value="null" disabled>-- Sélectionner --</option>
              <option v-for="t in documentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              <!-- Types courants si API pas encore disponible -->
              <option v-if="!documentTypes.length" value="1">CIN</option>
              <option v-if="!documentTypes.length" value="2">RC (Registre de Commerce)</option>
              <option v-if="!documentTypes.length" value="3">Statuts</option>
              <option v-if="!documentTypes.length" value="4">Contrat signé</option>
            </select>
          </div>

          <div>
            <label class="f-label">Date d'expiration (optionnel)</label>
            <input v-model="uploadForm.date_expiration" class="f-input" type="date" />
          </div>

          <div>
            <label class="f-label">Fichier * (PDF, JPG, PNG — max 10MB)</label>
            <input
              class="f-input"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              @change="onFileChange"
            />
            <p v-if="uploadForm.file" class="text-xs text-green-400 mt-1">
              ✓ {{ uploadForm.file.name }}
            </p>
          </div>
        </div>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showUpload = false">Annuler</button>
          <button class="btn btn-gold btn-md" :disabled="uploading" @click="submitUpload">
            {{ uploading ? 'Import...' : 'Importer' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>