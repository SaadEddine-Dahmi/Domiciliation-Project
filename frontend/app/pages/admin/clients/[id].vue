<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  layout: 'dashboard',
  middleware: ['auth'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { success, error: toastError } = useToast()

const clientId = computed(() => Number(route.params.id))

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function getRawToken(): string {
  if (!import.meta.client) return ''
  try {
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return ''
    const parsed = JSON.parse(raw)
    return parsed?.token ?? ''
  } catch {
    return ''
  }
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return { Accept: 'application/json' }

  try {
    const raw = localStorage.getItem('astfisc_auth')
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
  const token = getRawToken()
  if (!token) throw new Error('Missing token')
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

function guessExtFromMime(mime: string): string {
  if (mime.includes('pdf')) return 'pdf'
  if (mime.includes('png')) return 'png'
  if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg'
  if (mime.includes('msword')) return 'doc'
  if (mime.includes('officedocument.wordprocessingml.document')) return 'docx'
  return 'bin'
}

function filenameFromContentDisposition(cd: string | null): string {
  if (!cd) return ''
  const utf = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = cd.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || '').trim().replace(/^"|"$/g, '')
}

// ── State ─────────────────────────────────────────────────
const client = ref<any>(null)
const documents = ref<any[]>([])
const docTypes = ref<any[]>([])
const loading = ref(true)
const showUpload = ref(false)
const uploading = ref(false)

// Preview
const previewUrl = ref<string | null>(null)
const isPreviewOpen = ref(false)
const isPdf = ref(false)

const form = reactive({
  document_type_id: '',
  date_expiration: '',
  file: null as File | null,
})

// ── Helpers ───────────────────────────────────────────────
function isImage(name: string): boolean {
  if (!name) return false
  return /\.(jpg|jpeg|png|webp|gif)$/i.test(name)
}

function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

// ── Document Actions ──────────────────────────────────────
async function downloadDoc(doc: any) {
  try {
    const baseUrl = doc?.download_url || `${getApiBase()}/api/documents/${doc.id}/download`
    const url = withToken(baseUrl)

    const res = await fetch(url, { method: 'GET' })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const contentType = (res.headers.get('content-type') || '').toLowerCase()

    if (
      contentType.includes('application/json') ||
      contentType.includes('text/plain') ||
      contentType.includes('text/html')
    ) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        toastError?.(j?.message || 'Erreur de téléchargement')
      } catch {
        toastError?.('Erreur de téléchargement (réponse non fichier)')
      }
      return
    }

    const blob = await res.blob()

    let filename = filenameFromContentDisposition(res.headers.get('content-disposition'))
    if (!filename) {
      const ext = String(doc?.extension || '').toLowerCase() || guessExtFromMime(contentType)
      const base = String(doc?.name || `document-${doc.id}`).replace(/[\\/:*?"<>|]/g, '-')
      filename = base.includes('.') ? base : `${base}.${ext}`
    }

    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = filename
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
    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const contentType = (res.headers.get('content-type') || '').toLowerCase()

    if (
      contentType.includes('application/json') ||
      contentType.includes('text/plain') ||
      contentType.includes('text/html')
    ) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        toastError?.(j?.message || 'Session expirée ou non autorisée')
      } catch {
        toastError?.('Erreur aperçu (réponse non fichier)')
      }
      return
    }

    const blob = await res.blob()

    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }

    const ext = String(doc?.extension || '').toLowerCase()
    isPdf.value = doc?.is_pdf === true || ext === 'pdf' || contentType.includes('application/pdf')

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

// ── Fetchers ──────────────────────────────────────────────
async function loadAllData() {
  if (!clientId.value) return
  loading.value = true
  try {
    await Promise.all([fetchClient(), fetchDocuments(), fetchDocTypes()])
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

// ── Lifecycle & Watchers ──────────────────────────────────
watch(() => route.params.id, () => {
  loadAllData()
})

onMounted(() => {
  loadAllData()
})

onBeforeUnmount(() => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
})

// ── Upload/Delete Logic ───────────────────────────────────
function onFileChange(e: Event) {
  form.file = (e.target as HTMLInputElement).files?.[0] ?? null
}

async function submitUpload() {
  if (!form.file || !form.document_type_id) {
    toastError?.('Sélectionnez un type et un fichier')
    return
  }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('entreprise_id', String(clientId.value))
    fd.append('document_type_id', form.document_type_id)
    fd.append('file', form.file)
    if (form.date_expiration) fd.append('date_expiration', form.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method: 'POST',
      headers: authHeaders(),
      body: fd,
    })

    success('Document importé')
    showUpload.value = false
    Object.assign(form, { document_type_id: '', date_expiration: '', file: null })
    await fetchDocuments()
  } catch {
    toastError?.('Erreur import')
  } finally {
    uploading.value = false
  }
}

async function deleteDoc(id: number) {
  if (!confirm('Supprimer ce document ?')) return
  try {
    await $fetch(`${getApiBase()}/api/documents/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    documents.value = documents.value.filter(d => d.id !== id)
    success('Document supprimé')
  } catch {
    toastError?.('Erreur suppression')
  }
}

const statutColor: Record<string, string> = {
  actif: '#22c55e',
  inactif: '#ef4444',
  suspendu: '#f59e0b',
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
          <button class="btn btn-outline btn-sm" @click="router.push('/admin/clients')">
            Retour aux clients
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 pt-5"
             style="border-top: 1px solid var(--app-border-2)">
          <div v-if="client.adresse">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">Adresse</p>
            <p class="text-sm" style="color: var(--app-text)">{{ client.adresse }}</p>
          </div>
          <div v-if="client.capital">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">Capital</p>
            <p class="text-sm" style="color: var(--app-text)">{{ Number(client.capital).toLocaleString('fr-MA') }} DH</p>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-serif text-xl" style="color: var(--app-text)">Documents</h2>
          <button class="btn btn-gold btn-md" @click="showUpload = true">+ Importer</button>
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
              <p class="text-xs" style="color: var(--app-text-faint)">{{ fmt(doc.created_at) }}</p>
            </div>

            <div class="flex items-center gap-2">
              <button @click="previewDoc(doc)" class="btn btn-outline btn-sm">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                Aperçu
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
      </div>
    </template>

    <Teleport to="body">
      <div v-if="isPreviewOpen" class="fixed inset-0 z-[300] flex flex-col p-4 md:p-8" style="background: rgba(0,0,0,0.9)">
        <div class="flex justify-between items-center mb-4 text-white">
          <h3 class="text-lg font-serif">Aperçu du document</h3>
          <button @click="closePreview" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white">✕</button>
        </div>
        <div class="flex-1 bg-white rounded-xl overflow-hidden relative shadow-2xl">
          <iframe v-if="isPdf" :src="previewUrl || undefined" class="w-full h-full" frameborder="0" />
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-200">
            <img :src="previewUrl || undefined" class="max-w-full max-h-full object-contain" />
          </div>
        </div>
      </div>
    </Teleport>

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
              <select v-model="form.document_type_id" class="f-input">
                <option value="">-- Sélectionner --</option>
                <option v-for="t in docTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="f-label">Fichier *</label>
              <input type="file" class="f-input" @change="onFileChange" />
            </div>
            <div class="flex gap-3 justify-end pt-1">
              <button class="btn btn-outline btn-md" @click="showUpload = false">Annuler</button>
              <button class="btn btn-gold btn-md" :disabled="uploading || !form.file" @click="submitUpload">
                {{ uploading ? 'Envoi...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>