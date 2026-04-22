<!-- app/pages/admin/documents.vue -->
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
  } catch { return {} }
}

// ── State ─────────────────────────────────────────────────
const documents   = ref<any[]>([])
const clients     = ref<any[]>([])
const docTypes    = ref<any[]>([])
const loading     = ref(true)
const showModal   = ref(false)
const uploading   = ref(false)
const filterClient = ref('')
const search      = ref('')

// ── Upload form ───────────────────────────────────────────
const form = reactive({
  entreprise_id:    '',
  document_type_id: '',
  date_expiration:  '',
  file:             null as File | null,
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

    const res = await $fetch<{ success: boolean; data: any[] }>(
      url, { headers: authHeaders() }
    )
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
      `${getApiBase()}/api/clients`, { headers: authHeaders() }
    )
    clients.value = res.data ?? []
  } catch {}
}

async function fetchDocTypes() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/document-types`, { headers: authHeaders() }
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
    fd.append('entreprise_id',    form.entreprise_id)
    fd.append('document_type_id', form.document_type_id)
    fd.append('file',             form.file)
    if (form.date_expiration) fd.append('date_expiration', form.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method:  'POST',
      headers: authHeaders(), // do NOT set Content-Type — browser sets it with boundary
      body:    fd,
    })

    success('Document importé avec succès')
    showModal.value = false

    // Reset form
    Object.assign(form, { entreprise_id: '', document_type_id: '', date_expiration: '', file: null })
    const fileInput = document.getElementById('file-input') as HTMLInputElement
    if (fileInput) fileInput.value = ''

    // FIX: reload documents list immediately after upload
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

// ── Delete ─────────────────────────────────────────────────
async function deleteDoc(id: number) {
  if (!confirm('Supprimer ce document ?')) return
  try {
    await $fetch(`${getApiBase()}/api/documents/${id}`, {
      method: 'DELETE', headers: authHeaders(),
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

// ── Format date ────────────────────────────────────────────
function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function expiryClass(d: string | null): string {
  if (!d) return ''
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0)   return 'text-red-400'
  if (days < 30)  return 'text-yellow-400'
  return 'text-green-400'
}

// ── Watch filter ───────────────────────────────────────────
watch(filterClient, fetchDocuments)

onMounted(() => {
  fetchDocuments()
  fetchClients()
  fetchDocTypes()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Documents <em class="italic" style="color:#c8a96e">clients</em>
        </h1>
        <p class="text-sm mt-1" style="color: var(--app-text-muted)">
          {{ documents.length }} document(s) importé(s)
        </p>
      </div>
      <button class="btn btn-gold btn-md" @click="showModal = true">
        + Importer un document
      </button>
    </div>

    <!-- Filters row -->
    <div class="flex flex-col sm:flex-row gap-3">
      <!-- Search -->
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
             width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round"
             style="color: var(--app-text-faint)">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input v-model="search" class="f-input pl-9" placeholder="Rechercher un document..." />
      </div>

      <!-- Filter by client -->
      <select v-model="filterClient" class="f-input sm:w-64">
        <option value="">Tous les clients</option>
        <option v-for="c in clients" :key="c.id" :value="c.id">
          {{ c.raison_sociale }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 4" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-1/2 rounded mb-2" style="background: var(--app-border)"/>
        <div class="h-3 w-1/4 rounded" style="background: var(--app-border)"/>
      </div>
    </div>

    <!-- Document list -->
    <div v-else-if="filtered.length" class="rounded-2xl overflow-hidden"
         style="background: var(--app-surface); border: 1px solid var(--app-border-2)">

      <!-- Table header -->
      <div class="hidden sm:grid grid-cols-[2fr_1.5fr_1fr_1fr_auto] gap-4 px-5 py-3 text-[11px] uppercase tracking-widest font-bold"
           style="color: var(--app-text-faint); border-bottom: 1px solid var(--app-border-2)">
        <span>Document</span>
        <span>Client</span>
        <span>Date import</span>
        <span>Expiration</span>
        <span></span>
      </div>

      <div v-for="(doc, i) in filtered" :key="doc.id">
        <div
          class="grid grid-cols-1 sm:grid-cols-[2fr_1.5fr_1fr_1fr_auto] gap-3 sm:gap-4 px-5 py-4 items-center"
          :class="i < filtered.length - 1 ? 'border-b' : ''"
          :style="i < filtered.length - 1 ? 'border-color: var(--app-border-2)' : ''"
        >
          <!-- Name + type -->
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                 style="background: rgba(200,169,110,0.12)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="#c8a96e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>
            <div class="min-w-0">
              <p class="font-medium text-sm truncate" style="color: var(--app-text)">{{ doc.name }}</p>
              <p class="text-xs truncate" style="color: var(--app-text-faint)">
                {{ doc.document_type?.name ?? 'Type inconnu' }}
              </p>
            </div>
          </div>

          <!-- Client -->
          <p class="text-sm truncate" style="color: var(--app-text-muted)">
            {{ doc.entreprise?.raison_sociale ?? '—' }}
          </p>

          <!-- Import date -->
          <p class="text-sm" style="color: var(--app-text-muted)">
            {{ fmt(doc.created_at) }}
          </p>

          <!-- Expiry -->
          <p class="text-sm font-medium" :class="expiryClass(doc.date_expiration)">
            {{ doc.date_expiration ? fmt(doc.date_expiration) : '—' }}
          </p>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <a
              :href="doc.download_url"
              target="_blank"
              class="btn btn-outline btn-sm"
              title="Télécharger"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
            </a>
            <button class="btn btn-danger btn-sm" @click="deleteDoc(doc.id)" title="Supprimer">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6M14 11v6"/>
                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-12 text-center" style="color: var(--app-text-faint)">
      <svg class="mx-auto mb-4 opacity-30" width="40" height="40" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
      <p class="font-medium mb-1">Aucun document trouvé</p>
      <p class="text-sm">{{ search || filterClient ? 'Modifiez vos filtres' : 'Importez votre premier document' }}</p>
      <button v-if="!search && !filterClient" class="btn btn-gold btn-md mt-4" @click="showModal = true">
        + Importer un document
      </button>
    </div>


    <!-- ── Upload modal ── -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.75)"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-md flex flex-col" @click.stop>

          <!-- Header -->
          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom: 1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Importer un document</h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive"
                    @click="showModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4">

            <div>
              <label class="f-label">Entreprise cliente *</label>
              <select v-model="form.entreprise_id" class="f-input" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">
                  {{ c.raison_sociale }}
                </option>
              </select>
            </div>

            <div>
              <label class="f-label">Type de document *</label>
              <select v-model="form.document_type_id" class="f-input" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="t in docTypes" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
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
              <p v-if="form.file" class="text-xs mt-1" style="color: #22c55e">
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

  </div>
</template>