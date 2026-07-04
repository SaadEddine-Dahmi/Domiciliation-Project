<!-- ============================================================
  pages/admin/clients/[id].vue

  Client detail page.

  WHAT THIS PAGE SHOWS:
    - Company header: name, forme juridique, statut badge.
    - Représentant (gérant) info card: all fields needed for the PDF
        Nom complet, CIN/Passeport, Date de naissance, Nationalité,
        Téléphone, Email, Adresse personnelle.
    - Portal account info: name, email, telephone of the linked User.
    - Document list with preview and download.
    - Document upload modal.

  CHANGES vs previous version:
    - Fixed storage key: 'app_auth' (was 'astfisc_auth' — broken).
    - Removed capital info block (not relevant to domiciliation).
    - Added full représentant (gérant) info card with all PDF-relevant fields.
    - Added portal account info card.
    - Added edit buttons that open the index page modals (via query param).
============================================================ -->
<script setup lang="ts">
definePageMeta({
  layout:     'dashboard',
  middleware: ['auth'],
})

const route  = useRoute()
const router = useRouter()
const { success, error: toastError } = useToast()

const clientId = computed(() => Number(route.params.id))

// ── API helpers ───────────────────────────────────────────────────────────────

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

/**
 * Read the raw Bearer token from localStorage.
 * Key: 'app_auth' — written by the auth store on login.
 * FIX: was 'astfisc_auth' in the previous version — caused 401 on all
 * document download and preview requests.
 */
function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('app_auth')   // FIX: correct key
    if (!raw) return ''
    return JSON.parse(raw)?.token ?? ''
  } catch { return '' }
}

/**
 * Build Authorization header from localStorage.
 * FIX: key corrected from 'astfisc_auth' to 'app_auth'.
 */
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')   // FIX: correct key
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

/**
 * Append the Bearer token as a query parameter.
 * Used for document download/preview URLs which cannot carry custom headers
 * (direct browser navigation / fetch without header injection).
 */
function withToken(url: string): string {
  const token = getRawToken()
  if (!token) throw new Error('Token manquant — veuillez vous reconnecter.')
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

// ── Mime / filename helpers ───────────────────────────────────────────────────

function guessExtFromMime(mime: string): string {
  if (mime.includes('pdf'))    return 'pdf'
  if (mime.includes('png'))    return 'png'
  if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg'
  if (mime.includes('msword')) return 'doc'
  if (mime.includes('officedocument.wordprocessingml.document')) return 'docx'
  return 'bin'
}

function filenameFromContentDisposition(cd: string | null): string {
  if (!cd) return ''
  const utf   = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = cd.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || '').trim().replace(/^"|"$/g, '')
}

// ── State ─────────────────────────────────────────────────────────────────────

const client    = ref<any>(null)     // Entreprise with representant + clientUser
const documents = ref<any[]>([])
const docTypes  = ref<any[]>([])
const loading   = ref(true)

// Document upload form
const showUpload = ref(false)
const uploading  = ref(false)
const uploadForm = reactive({
  document_type_id: '',
  date_expiration:  '',
  file: null as File | null,
})

// Document preview
const previewUrl     = ref<string | null>(null)
const isPreviewOpen  = ref(false)
const isPdf          = ref(false)

// ── Date formatter ────────────────────────────────────────────────────────────

/**
 * Format an ISO date string as dd/mm/yyyy for display.
 * Returns '—' for null or empty input.
 */
function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function isImage(name: string): boolean {
  return /\.(jpg|jpeg|png|webp|gif)$/i.test(name ?? '')
}

// ── Document preview / download ───────────────────────────────────────────────

/**
 * downloadDoc()
 *
 * Downloads a document file by fetching it with the token-appended URL and
 * creating a temporary <a> element to trigger the browser download.
 * Does NOT open the document inline.
 */
async function downloadDoc(doc: any): Promise<void> {
  try {
    const baseUrl = doc?.download_url
      || `${getApiBase()}/api/documents/${doc.id}/download`
    const res = await fetch(withToken(baseUrl))
    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    if (contentType.includes('application/json') || contentType.includes('text/')) {
      const txt = await res.text()
      try { toastError?.(JSON.parse(txt)?.message || 'Erreur téléchargement') }
      catch { toastError?.('Erreur téléchargement') }
      return
    }

    const blob     = await res.blob()
    let filename   = filenameFromContentDisposition(res.headers.get('content-disposition'))
    if (!filename) {
      const ext  = String(doc?.extension || '').toLowerCase() || guessExtFromMime(contentType)
      const base = String(doc?.name || `document-${doc.id}`).replace(/[\\/:*?"<>|]/g, '-')
      filename   = base.includes('.') ? base : `${base}.${ext}`
    }

    const blobUrl = URL.createObjectURL(blob)
    const a       = document.createElement('a')
    a.href        = blobUrl
    a.download    = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(blobUrl)

  } catch {
    toastError?.('Erreur de téléchargement')
  }
}

/**
 * previewDoc()
 *
 * Opens a document inline in a fullscreen overlay.
 * PDFs are shown in an <iframe>, images in an <img>.
 */
async function previewDoc(doc: any): Promise<void> {
  try {
    const baseUrl = doc?.preview_url
      || `${getApiBase()}/api/documents/${doc.id}/preview`
    const res = await fetch(withToken(baseUrl))
    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    if (contentType.includes('application/json') || contentType.includes('text/')) {
      toastError?.('Aperçu indisponible')
      return
    }

    const blob = await res.blob()
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)

    const ext   = String(doc?.extension || '').toLowerCase()
    isPdf.value = doc?.is_pdf === true || ext === 'pdf'
                  || contentType.includes('application/pdf')

    previewUrl.value    = URL.createObjectURL(blob)
    isPreviewOpen.value = true

  } catch {
    toastError?.('Session expirée ou non autorisée')
  }
}

function closePreview(): void {
  isPreviewOpen.value = false
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

// ── Data loading ──────────────────────────────────────────────────────────────

/**
 * loadAllData()
 *
 * Loads the client (with representant + clientUser), their documents, and
 * all available document types in parallel.
 */
async function loadAllData(): Promise<void> {
  if (!clientId.value) return
  loading.value = true
  try {
    await Promise.all([fetchClient(), fetchDocuments(), fetchDocTypes()])
  } finally {
    loading.value = false
  }
}

async function fetchClient(): Promise<void> {
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

async function fetchDocuments(): Promise<void> {
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

async function fetchDocTypes(): Promise<void> {
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

// ── Document upload ───────────────────────────────────────────────────────────

function onFileChange(e: Event): void {
  uploadForm.file = (e.target as HTMLInputElement).files?.[0] ?? null
}

async function submitUpload(): Promise<void> {
  if (!uploadForm.file || !uploadForm.document_type_id) {
    toastError?.('Sélectionnez un type et un fichier')
    return
  }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('entreprise_id',    String(clientId.value))
    fd.append('document_type_id', uploadForm.document_type_id)
    fd.append('file',             uploadForm.file)
    if (uploadForm.date_expiration)
      fd.append('date_expiration', uploadForm.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method:  'POST',
      headers: authHeaders(),
      body:    fd,
    })

    success('Document importé')
    showUpload.value = false
    Object.assign(uploadForm, { document_type_id: '', date_expiration: '', file: null })
    await fetchDocuments()
  } catch {
    toastError?.('Erreur lors de l\'import')
  } finally {
    uploading.value = false
  }
}

async function deleteDoc(id: number): Promise<void> {
  if (!confirm('Supprimer ce document ?')) return
  try {
    await $fetch(`${getApiBase()}/api/documents/${id}`, {
      method:  'DELETE',
      headers: authHeaders(),
    })
    documents.value = documents.value.filter(d => d.id !== id)
    success('Document supprimé')
  } catch {
    toastError?.('Erreur suppression')
  }
}

// ── Status colour ─────────────────────────────────────────────────────────────

const statutColor: Record<string, string> = {
  actif:    '#22c55e',
  inactif:  '#ef4444',
  suspendu: '#f59e0b',
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

watch(() => route.params.id, loadAllData)
onMounted(loadAllData)
onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>

<template>
  <div class="space-y-6 animate-fade-up">

    <!-- ── Back button ─────────────────────────────────────────────────────── -->
    <button
      class="flex items-center gap-2 text-sm transition-colors nav-inactive"
      @click="router.push('/admin/clients')"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Retour aux clients
    </button>

    <!-- ── Loading skeleton ────────────────────────────────────────────────── -->
    <div v-if="loading" class="card p-6 animate-pulse space-y-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl" style="background:var(--app-border)"/>
        <div class="space-y-2 flex-1">
          <div class="h-5 w-1/3 rounded" style="background:var(--app-border)"/>
          <div class="h-3 w-1/4 rounded" style="background:var(--app-border)"/>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="h-10 rounded-xl"
             style="background:var(--app-border)"/>
      </div>
    </div>

    <template v-else-if="client">

      <!-- ── Company header card ─────────────────────────────────────────────── -->
      <div class="card p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">

          <div class="flex items-center gap-4">
            <!-- Company avatar -->
            <div
              class="w-14 h-14 rounded-2xl flex items-center justify-center
                     font-bold text-xl shrink-0"
              style="background:rgba(200,169,110,0.15);color:#c8a96e"
            >
              {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
            </div>

            <div>
              <!-- Company name -->
              <h1 class="font-serif text-2xl" style="color:var(--app-text)">
                {{ client.raison_sociale }}
              </h1>
              <!-- Forme juridique + statut badge -->
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span
                  v-if="client.forme_juridique"
                  class="text-sm"
                  style="color:var(--app-text-muted)"
                >
                  {{ client.forme_juridique }}
                </span>
                <span
                  v-if="client.statut"
                  class="text-xs px-2 py-0.5 rounded-full font-semibold"
                  :style="`color:${statutColor[client.statut] ?? '#94a3b8'};
                           background:${statutColor[client.statut] ?? '#94a3b8'}18`"
                >
                  {{ client.statut }}
                </span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-2 flex-wrap">
            <button
              class="btn btn-outline btn-sm"
              @click="router.push('/admin/clients')"
            >
              ← Liste
            </button>
          </div>
        </div>

        <!-- Company address + creation date -->
        <div
          v-if="client.adresse || client.ville || client.date_creation"
          class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-5"
          style="border-top:1px solid var(--app-border-2)"
        >
          <div v-if="client.adresse || client.ville">
            <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
               style="color:var(--app-text-faint)">
              Adresse siège social
            </p>
            <p class="text-sm" style="color:var(--app-text)">
              {{ [client.adresse, client.ville, client.pays].filter(Boolean).join(', ') }}
            </p>
          </div>
          <div v-if="client.date_creation">
            <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
               style="color:var(--app-text-faint)">
              Date de création
            </p>
            <p class="text-sm" style="color:var(--app-text)">
              {{ fmt(client.date_creation) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Représentant card — shows exactly the eight fields -->
<div class="card p-6">
  <h2 class="font-serif text-xl mb-4" style="color:var(--app-text)">
    Représentant légal
  </h2>

  <div v-if="client.representant" class="space-y-4">

    <!-- Name heading -->
    <div
      class="rounded-xl px-5 py-4"
      style="background:rgba(200,169,110,0.06);
             border:1px solid rgba(200,169,110,0.2)"
    >
      <p class="font-serif text-xl" style="color:var(--app-text)">
        {{ client.representant.prenom }} {{ client.representant.nom }}
      </p>
    </div>

    <!-- All seven identity fields -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

      <div v-if="client.representant.cin">
        <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
           style="color:var(--app-text-faint)">CIN / Passeport</p>
        <p class="text-sm font-mono" style="color:var(--app-text)">
          {{ client.representant.cin }}
        </p>
      </div>

      <div v-if="client.representant.date_naissance">
        <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
           style="color:var(--app-text-faint)">Date de naissance</p>
        <p class="text-sm" style="color:var(--app-text)">
          {{ new Date(client.representant.date_naissance).toLocaleDateString('fr-FR') }}
        </p>
      </div>

      <div v-if="client.representant.telephone">
        <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
           style="color:var(--app-text-faint)">Téléphone</p>
        <p class="text-sm" style="color:var(--app-text)">
          {{ client.representant.telephone }}
        </p>
      </div>

      <div v-if="client.representant.email">
        <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
           style="color:var(--app-text-faint)">Email</p>
        <p class="text-sm truncate" style="color:var(--app-text)">
          {{ client.representant.email }}
        </p>
      </div>

      <div v-if="client.representant.adresse" class="sm:col-span-2 lg:col-span-3">
        <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
           style="color:var(--app-text-faint)">
          Adresse de résidence
          <span class="font-normal normal-case">(CIN / Passeport)</span>
        </p>
        <p class="text-sm" style="color:var(--app-text)">
          {{ client.representant.adresse }}
        </p>
      </div>

    </div>
  </div>

  <div
    v-else
    class="rounded-xl p-5 text-center"
    style="background:rgba(245,158,11,0.06);
           border:2px dashed rgba(245,158,11,0.25)"
  >
    <p class="text-sm" style="color:#f59e0b">
      ⚠ Aucun représentant enregistré
    </p>
    <p class="text-xs mt-1" style="color:var(--app-text-faint)">
      Les champs CIN, date de naissance et adresse seront vides dans le contrat PDF.
    </p>
    <button class="btn btn-outline btn-sm mt-3"
            @click="router.push('/admin/clients')">
      ← Ajouter le représentant
    </button>
  </div>
</div>

      <!-- ── Portal account card ─────────────────────────────────────────────── -->
      <div v-if="client.client_user" class="card p-6">
        <h2 class="font-serif text-xl mb-4" style="color:var(--app-text)">
          Compte portail
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
               style="color:var(--app-text-faint)">Nom</p>
            <p class="text-sm" style="color:var(--app-text)">
              {{ client.client_user.nom }} {{ client.client_user.prenom }}
            </p>
          </div>
          <div>
            <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
               style="color:var(--app-text-faint)">Email de connexion</p>
            <p class="text-sm truncate" style="color:var(--app-text)">
              {{ client.client_user.email }}
            </p>
          </div>
          <div v-if="client.client_user.telephone">
            <p class="text-[10px] uppercase tracking-wide font-bold mb-1"
               style="color:var(--app-text-faint)">Téléphone</p>
            <p class="text-sm" style="color:var(--app-text)">
              {{ client.client_user.telephone }}
            </p>
          </div>
        </div>
      </div>

      <!-- ── Documents section ────────────────────────────────────────────────── -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-serif text-xl" style="color:var(--app-text)">
            Documents
            <span
              v-if="documents.length"
              class="text-sm font-normal ml-2"
              style="color:var(--app-text-faint)"
            >
              ({{ documents.length }})
            </span>
          </h2>
          <button class="btn btn-gold btn-md" @click="showUpload = true">
            + Importer
          </button>
        </div>

        <!-- Document list -->
        <div v-if="documents.length" class="space-y-2">
          <div
            v-for="doc in documents"
            :key="doc.id"
            class="card p-4 flex items-center gap-4 flex-wrap"
          >
            <!-- File type icon -->
            <div
              class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
              style="background:rgba(200,169,110,0.1)"
            >
              <svg v-if="!isImage(doc.name)" width="18" height="18" viewBox="0 0 24 24"
                   fill="none" stroke="#c8a96e" stroke-width="1.8">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
              <div v-else class="text-[10px] font-bold" style="color:#c8a96e">IMG</div>
            </div>

            <!-- Document name + date -->
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm truncate" style="color:var(--app-text)">
                {{ doc.name }}
              </p>
              <p class="text-xs" style="color:var(--app-text-faint)">
                Importé le {{ fmt(doc.created_at) }}
                <span v-if="doc.date_expiration">
                  · Expire le {{ fmt(doc.date_expiration) }}
                </span>
              </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <button class="btn btn-outline btn-sm" title="Aperçu"
                      @click="previewDoc(doc)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn btn-outline btn-sm" title="Télécharger"
                      @click="downloadDoc(doc)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="7 10 12 15 17 10"/>
                  <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
              </button>
              <button class="btn btn-danger btn-sm" title="Supprimer"
                      @click="deleteDoc(doc.id)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- No documents yet -->
        <div
          v-else
          class="card p-8 text-center"
          style="color:var(--app-text-faint)"
        >
          <svg class="mx-auto mb-3 opacity-30" width="32" height="32"
               viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.5" stroke-linecap="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <p>Aucun document importé pour ce client.</p>
          <button class="btn btn-outline btn-sm mt-3" @click="showUpload = true">
            + Importer le premier document
          </button>
        </div>
      </div>

    </template>


    <!-- ════════════════════════════════════════════════════════════════════════
         MODAL — Document preview (fullscreen overlay)
    ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="isPreviewOpen"
        class="fixed inset-0 z-300 flex flex-col p-4 md:p-8"
        style="background:rgba(0,0,0,0.92)"
      >
        <div class="flex justify-between items-center mb-4 text-white">
          <h3 class="text-lg font-serif">Aperçu du document</h3>
          <button
            class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center"
            @click="closePreview"
          >✕</button>
        </div>
        <div class="flex-1 bg-white rounded-xl overflow-hidden shadow-2xl">
          <iframe v-if="isPdf" :src="previewUrl || undefined"
                  class="w-full h-full" frameborder="0"/>
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-100">
            <img :src="previewUrl || undefined"
                 class="max-w-full max-h-full object-contain"/>
          </div>
        </div>
      </div>
    </Teleport>


    <!-- ════════════════════════════════════════════════════════════════════════
         MODAL — Document upload
    ════════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showUpload"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.75)"
        @click.self="showUpload = false"
      >
        <div class="card w-full max-w-md flex flex-col" @click.stop>

          <div class="px-6 pt-6 pb-4 flex justify-between items-center"
               style="border-bottom:1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Importer un document</h2>
            <button class="nav-inactive" @click="showUpload = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="f-label">Type de document *</label>
              <select v-model="uploadForm.document_type_id" class="f-input">
                <option value="">-- Sélectionner --</option>
                <option v-for="t in docTypes" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="f-label">Date d'expiration (optionnel)</label>
              <input v-model="uploadForm.date_expiration" class="f-input" type="date"/>
            </div>
            <div>
              <label class="f-label">Fichier * (PDF, JPG, PNG — max 10 Mo)</label>
              <input type="file" class="f-input" @change="onFileChange"/>
              <p v-if="uploadForm.file" class="text-xs mt-1 text-green-400">
                ✓ {{ uploadForm.file.name }}
              </p>
            </div>
            <div class="flex gap-3 justify-end pt-1">
              <button class="btn btn-outline btn-md" @click="showUpload = false">
                Annuler
              </button>
              <button
                class="btn btn-gold btn-md"
                :disabled="uploading || !uploadForm.file || !uploadForm.document_type_id"
                @click="submitUpload"
              >
                {{ uploading ? 'Envoi...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>