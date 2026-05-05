<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()
const { success, error: toastError } = useToast()

function getApiBase() {
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
  } catch {
    return {}
  }
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

function withToken(url: string): string {
  const token = getRawToken()
  if (!token) throw new Error('Missing token')
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}token=${encodeURIComponent(token)}`
}

function filenameFromContentDisposition(cd: string | null): string {
  if (!cd) return ''
  const utf = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = cd.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || '').trim().replace(/^"|"$/g, '')
}

function guessExtFromMime(mime: string): string {
  if (mime.includes('pdf')) return 'pdf'
  if (mime.includes('png')) return 'png'
  if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg'
  if (mime.includes('msword')) return 'doc'
  if (mime.includes('officedocument.wordprocessingml.document')) return 'docx'
  return 'bin'
}

function isPdfDoc(doc: any): boolean {
  const ext = String(doc?.extension || '').toLowerCase()
  const n = String(doc?.name || '').toLowerCase()
  return doc?.is_pdf === true || ext === 'pdf' || n.endsWith('.pdf')
}

// ── State ─────────────────────────────────────────────────
const documents = ref<any[]>([])
const clients = ref<any[]>([])
const docTypes = ref<any[]>([])
const loading = ref(true)
const showModal = ref(false)
const uploading = ref(false)
const filterClient = ref('')
const search = ref('')
const downloadingId = ref<number | null>(null)

// Preview modal
const showPreview = ref(false)
const previewUrl = ref<string | null>(null)
const previewIsPdf = ref(false)

const form = reactive({
  entreprise_id: '',
  document_type_id: '',
  date_expiration: '',
  file: null as File | null,
})

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  form.file = input.files?.[0] ?? null
}

// ── Fetch data ────────────────────────────────────────────
async function fetchDocuments() {
  loading.value = true
  try {
    const url = filterClient.value
      ? `${getApiBase()}/api/documents?entreprise_id=${filterClient.value}`
      : `${getApiBase()}/api/documents`

    const res = await $fetch<{ success: boolean; data: any[] }>(url, { headers: authHeaders() })
    documents.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement documents')
  } finally {
    loading.value = false
  }
}

async function fetchClients() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/clients`,
      { headers: authHeaders() }
    )
    clients.value = res.data ?? []
  } catch {}
}

async function fetchDocTypes() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/document-types`,
      { headers: authHeaders() }
    )
    docTypes.value = res.data ?? []
  } catch {}
}

// ── Upload ─────────────────────────────────────────────────
async function submitUpload() {
  if (!form.file || !form.entreprise_id || !form.document_type_id) {
    toastError?.('Veuillez remplir tous les champs obligatoires')
    return
  }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('entreprise_id', form.entreprise_id)
    fd.append('document_type_id', form.document_type_id)
    fd.append('file', form.file)
    if (form.date_expiration) fd.append('date_expiration', form.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method: 'POST',
      headers: authHeaders(),
      body: fd,
    })

    success('Document importé avec succès')
    showModal.value = false

    Object.assign(form, { entreprise_id: '', document_type_id: '', date_expiration: '', file: null })
    const fileInput = document.getElementById('file-input') as HTMLInputElement
    if (fileInput) fileInput.value = ''

    await fetchDocuments()
  } catch (e: any) {
    const msg = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? "Erreur lors de l'import"
    toastError?.(msg)
  } finally {
    uploading.value = false
  }
}

// ── Download / Preview ─────────────────────────────────────
async function downloadDoc(doc: any) {
  downloadingId.value = doc.id
  try {
    const baseUrl = doc?.download_url || `${getApiBase()}/api/documents/${doc.id}/download`
    const res = await fetch(withToken(baseUrl), { method: 'GET' })

    if (!res.ok) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        toastError?.(j?.message || 'Erreur téléchargement')
      } catch {
        toastError?.('Erreur téléchargement')
      }
      return
    }

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    if (
      contentType.includes('application/json') ||
      contentType.includes('text/plain') ||
      contentType.includes('text/html')
    ) {
      toastError?.('Réponse non fichier')
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
    toastError?.('Erreur téléchargement')
  } finally {
    downloadingId.value = null
  }
}

async function openPreview(doc: any) {
  try {
    const baseUrl = doc?.preview_url || `${getApiBase()}/api/documents/${doc.id}/preview`
    const res = await fetch(withToken(baseUrl), { method: 'GET' })
    if (!res.ok) throw new Error('preview failed')

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    if (
      contentType.includes('application/json') ||
      contentType.includes('text/plain') ||
      contentType.includes('text/html')
    ) {
      toastError?.('Aperçu indisponible')
      return
    }

    const blob = await res.blob()
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = URL.createObjectURL(blob)
    previewIsPdf.value = isPdfDoc(doc) || contentType.includes('application/pdf')
    showPreview.value = true
  } catch {
    toastError?.('Erreur aperçu')
  }
}

function closePreview() {
  showPreview.value = false
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
}

// ── Delete ─────────────────────────────────────────────────
async function deleteDoc(id: number) {
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

// ── Filtered list ──────────────────────────────────────────
const filtered = computed(() => {
  let list = documents.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(d =>
      d.name?.toLowerCase().includes(q) ||
      d.entreprise?.raison_sociale?.toLowerCase().includes(q) ||
      d.document_type?.name?.toLowerCase().includes(q)
    )
  }
  return list
})

// ── Utils ──────────────────────────────────────────────────
function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function expiryClass(d: string | null): string {
  if (!d) return 'text-(--app-text-muted)'
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0) return 'text-red-400'
  if (days < 30) return 'text-yellow-400'
  return 'text-green-400'
}

watch(filterClient, fetchDocuments)

onMounted(async () => {
  await Promise.all([fetchDocuments(), fetchClients(), fetchDocTypes()])
})

onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl text-(--app-text)">
          Documents <em class="italic text-[#c8a96e]">clients</em>
        </h1>
        <p class="text-sm mt-1 text-(--app-text-muted)">
          {{ documents.length }} document(s) importé(s)
        </p>
      </div>
      <button class="btn btn-gold btn-md" @click="showModal = true">
        + Importer un document
      </button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative group w-full sm:w-64 focus-within:sm:w-125 transition-all duration-500 ease-in-out">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-focus-within:text-amber-300 transition-colors"
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
        >
          <circle cx="11" cy="11" r="8" />
          <path d="M21 21l-4.35-4.35" />
        </svg>

        <input
          v-model="search"
          type="text"
          autocomplete="off"
          placeholder="Rechercher un document..."
          class="w-full h-11 rounded-xl pl-9 pr-3
                 bg-slate-900/70 text-slate-100! laceholder:text-slate-400!
                 border border-slate-700/70
                 outline-none
                 transition-all duration-500 ease-in-out
                 group-focus-within:border-amber-400/60
                 group-focus-within:ring-2 group-focus-within:ring-amber-400/20
                 group-focus-within:shadow-[0_0_0_3px_rgba(251,191,36,0.08)]"
        />
      </div>

      <select
        v-model="filterClient"
        class="f-input sm:w-64 text-(--app-text) bg-(--app-surface-2) border border-(--app-border-2)"
      >
        <option value="">Tous les clients</option>
        <option v-for="c in clients" :key="c.id" :value="c.id">
          {{ c.raison_sociale }}
        </option>
      </select>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-1/2 rounded mb-2 g-(--app-border)" />
        <div class="h-3 w-1/4 rounded g-(--app-border)" />
      </div>
    </div>

    <div
      v-else-if="filtered.length"
      class="rounded-2xl overflow-hidden bg-(--app-surface) border border-(--app-border-2)"
    >
      <div
        class="hidden sm:grid grid-cols-[2fr_1.5fr_2fr_auto] gap-4 px-5 py-3 text-[11px] uppercase tracking-widest font-bold text-(--app-text-faint) border-b border-(--app-border-2)"
      >
        <span>Document</span>
        <span>Client</span>
        <span>Date import / Expiration</span>
        <span></span>
      </div>

      <div v-for="(doc, i) in filtered" :key="doc.id">
        <div
          class="grid grid-cols-1 sm:grid-cols-[2fr_1.5fr_2fr_auto] gap-3 sm:gap-4 px-5 py-4 items-center"
          :class="i < filtered.length - 1 ? 'border-b border-(--app-border-2)' : ''"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 bg-[rgba(200,169,110,0.12)]">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8a96e" stroke-width="1.8">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div class="min-w-0">
              <p class="font-medium text-sm truncate text-(--app-text)">{{ doc.name }}</p>
              <p class="text-xs truncate text-(--app-text-faint)">
                {{ doc.document_type?.name ?? 'Type inconnu' }}
              </p>
            </div>
          </div>

          <p class="text-sm truncate text-(--app-text-muted)">
            {{ doc.entreprise?.raison_sociale ?? '—' }}
          </p>

          <div>
            <ul class="flex items-center gap-6 text-sm whitespace-nowrap">
              <li class="list-none text-(--app-text-muted)">
                <span class="text-(--app-text-faint) mr-1">Import:</span>
                {{ fmt(doc.created_at) }}
              </li>
              <li class="list-none font-medium" :class="expiryClass(doc.date_expiration)">
                <span class="text-(--app-text-faint) mr-1">Expiration:</span>
                {{ doc.date_expiration ? fmt(doc.date_expiration) : '—' }}
              </li>
            </ul>
          </div>

          <div class="flex items-center gap-2">
            <button class="btn btn-outline btn-sm" title="Aperçu" @click="openPreview(doc)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                
            </button>

            <button
              class="btn btn-outline btn-sm"
              title="Télécharger"
              :disabled="downloadingId === doc.id"
              @click="downloadDoc(doc)"
            >

              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </button>

            <button class="btn btn-danger btn-sm" @click="deleteDoc(doc.id)" title="Supprimer">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-12 text-center text-(--app-text-faint)">
      <p class="font-medium mb-1">Aucun document trouvé</p>
      <p class="text-sm">{{ search || filterClient ? 'Modifiez vos filtres' : 'Importez votre premier document' }}</p>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.75)"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-md flex flex-col" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0 border-b border-(--app-border-2)">
            <h2 class="font-serif text-xl">Importer un document</h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive" @click="showModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="f-label">Entreprise cliente *</label>
              <select v-model="form.entreprise_id" class="f-input" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.raison_sociale }}</option>
              </select>
            </div>

            <div>
              <label class="f-label">Type de document *</label>
              <select v-model="form.document_type_id" class="f-input" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="t in docTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>

            <div>
              <label class="f-label">Date d'expiration (optionnel)</label>
              <input v-model="form.date_expiration" type="date" class="f-input" />
            </div>

            <div>
              <label class="f-label">Fichier * (PDF, JPG, PNG — max 10 Mo)</label>
              <input
                id="file-input"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                class="f-input cursor-pointer"
                style="padding: 0.5rem 0.78rem"
                @change="onFileChange"
              />
              <p v-if="form.file" class="text-xs mt-1 text-green-400">
                ✓ {{ form.file.name }} ({{ (form.file.size / 1024 / 1024).toFixed(2) }} Mo)
              </p>
            </div>

            <div class="flex gap-3 justify-end pt-2">
              <button class="btn btn-outline btn-md" @click="showModal = false">Annuler</button>
              <button
                class="btn btn-gold btn-md"
                :disabled="uploading || !form.file || !form.entreprise_id || !form.document_type_id"
                @click="submitUpload"
              >
                {{ uploading ? 'Import en cours...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showPreview" class="fixed inset-0 z-300 flex flex-col p-4 md:p-8 bg-black/90">
        <div class="flex justify-between items-center mb-4 text-white">
          <h3 class="text-lg font-serif">Aperçu du document</h3>
          <button @click="closePreview" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">✕</button>
        </div>
        <div class="flex-1 bg-white rounded-xl overflow-hidden">
          <iframe v-if="previewIsPdf" :src="previewUrl || undefined" class="w-full h-full" frameborder="0" />
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-200">
            <img :src="previewUrl || undefined" class="max-w-full max-h-full object-contain" />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>