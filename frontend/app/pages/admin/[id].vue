<!-- app/pages/admin/clients/[id].vue -->
<!-- Domiciliataire: full detail view of one client with their documents -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ 
  layout: 'dashboard', 
  middleware: ['auth'],
  // Merge the path here so it doesn't get overwritten
  path: '/admin/clients/:id' 
})


const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const { success, error: toastError } = useToast()

const clientId = computed(() => Number(route.params.id))

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
const client      = ref<any>(null)
const documents   = ref<any[]>([])
const docTypes    = ref<any[]>([])
const loading     = ref(true)
const showUpload  = ref(false)
const uploading   = ref(false)

const form = reactive({
  document_type_id: '',
  date_expiration:  '',
  file:             null as File | null,
})

// ── Fetch client detail ───────────────────────────────────
async function fetchClient() {
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/clients/${clientId.value}`,
      { headers: authHeaders() }
    )
    client.value = res.data
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Client introuvable')
    router.push('/admin/clients')
  }
}

// ── Fetch documents for this client ──────────────────────
async function fetchDocuments() {
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/documents?entreprise_id=${clientId.value}`,
      { headers: authHeaders() }
    )
    documents.value = res.data ?? []
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
    fd.append('entreprise_id',    String(clientId.value))
    fd.append('document_type_id', form.document_type_id)
    fd.append('file',             form.file)
    if (form.date_expiration) fd.append('date_expiration', form.date_expiration)

    await $fetch(`${getApiBase()}/api/documents`, {
      method: 'POST', headers: authHeaders(), body: fd,
    })

    success('Document importé')
    showUpload.value = false
    Object.assign(form, { document_type_id: '', date_expiration: '', file: null })
    const fi = document.getElementById('detail-file-input') as HTMLInputElement
    if (fi) fi.value = ''

    await fetchDocuments()
  } catch (e: any) {
    const msg = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? "Erreur import"
    toastError?.(msg)
  } finally {
    uploading.value = false
  }
}

// ── Delete document ────────────────────────────────────────
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

// ── Helpers ────────────────────────────────────────────────
function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function expiryClass(d: string | null): string {
  if (!d) return ''
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0)  return 'text-red-400'
  if (days < 30) return 'text-yellow-400'
  return 'text-green-400'
}

function expiryLabel(d: string | null): string {
  if (!d) return '—'
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0)   return `Expiré il y a ${Math.abs(days)}j`
  if (days === 0) return 'Expire aujourd\'hui'
  if (days < 30)  return `Expire dans ${days}j`
  return fmt(d)
}

const statutColor: Record<string, string> = {
  actif:    '#22c55e',
  inactif:  '#ef4444',
  suspendu: '#f59e0b',
}

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchClient(), fetchDocuments(), fetchDocTypes()])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6 animate-fade-up">

    <!-- Back button -->
    <button
      class="flex items-center gap-2 text-sm nav-inactive transition-colors"
      @click="router.push('/admin/clients')"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Retour aux clients
    </button>

    <!-- Loading -->
    <div v-if="loading" class="space-y-4">
      <div class="card p-6 animate-pulse">
        <div class="h-5 w-48 rounded mb-3" style="background: var(--app-border)"/>
        <div class="h-3 w-32 rounded" style="background: var(--app-border)"/>
      </div>
    </div>

    <template v-else-if="client">

      <!-- ── Client info card ── -->
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
            Modifier le client
          </button>
        </div>

        <!-- Info grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6 pt-5"
             style="border-top: 1px solid var(--app-border-2)">

          <div v-if="client.ville || client.pays">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">
              Localisation
            </p>
            <p class="text-sm" style="color: var(--app-text)">
              {{ [client.ville, client.pays].filter(Boolean).join(', ') }}
            </p>
          </div>

          <div v-if="client.adresse">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">
              Adresse
            </p>
            <p class="text-sm" style="color: var(--app-text)">{{ client.adresse }}</p>
          </div>

          <div v-if="client.capital">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">
              Capital
            </p>
            <p class="text-sm" style="color: var(--app-text)">
              {{ Number(client.capital).toLocaleString('fr-MA') }} DH
            </p>
          </div>

          <div v-if="client.date_creation">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">
              Créée le
            </p>
            <p class="text-sm" style="color: var(--app-text)">{{ fmt(client.date_creation) }}</p>
          </div>

          <div v-if="client.client_user">
            <p class="text-xs uppercase tracking-wide font-bold mb-1" style="color: var(--app-text-faint)">
              Gérant / Contact
            </p>
            <p class="text-sm font-medium" style="color: var(--app-text)">
              {{ client.client_user.nom }} {{ client.client_user.prenom }}
            </p>
            <p class="text-xs" style="color: var(--app-text-muted)">{{ client.client_user.email }}</p>
            <p v-if="client.client_user.telephone" class="text-xs" style="color: var(--app-text-muted)">
              {{ client.client_user.telephone }}
            </p>
          </div>
        </div>
      </div>

      <!-- ── Documents section ── -->
      <div>
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
          <div>
            <h2 class="font-serif text-xl" style="color: var(--app-text)">Documents</h2>
            <p class="text-sm mt-0.5" style="color: var(--app-text-muted)">
              {{ documents.length }} document(s) importé(s)
            </p>
          </div>
          <button class="btn btn-gold btn-md" @click="showUpload = true">
            + Importer un document
          </button>
        </div>

        <!-- Document list -->
        <div v-if="documents.length" class="space-y-2">
          <div
            v-for="doc in documents" :key="doc.id"
            class="card p-4 flex items-center gap-4 flex-wrap"
          >
            <!-- Icon -->
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background: rgba(200,169,110,0.1)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                   stroke="#c8a96e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm" style="color: var(--app-text)">{{ doc.name }}</p>
              <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                <p class="text-xs" style="color: var(--app-text-faint)">
                  Importé le {{ fmt(doc.created_at) }}
                </p>
                <p
                  v-if="doc.date_expiration"
                  class="text-xs font-medium"
                  :class="expiryClass(doc.date_expiration)"
                >
                  {{ expiryLabel(doc.date_expiration) }}
                </p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0">
              <a :href="doc.download_url" target="_blank" class="btn btn-outline btn-sm">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="7 10 12 15 17 10"/>
                  <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Télécharger
              </a>
              <button class="btn btn-danger btn-sm" @click="deleteDoc(doc.id)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty documents -->
        <div v-else class="card p-10 text-center" style="color: var(--app-text-faint)">
          <svg class="mx-auto mb-3 opacity-30" width="36" height="36" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <p>Aucun document pour ce client.</p>
          <button class="btn btn-gold btn-sm mt-3" @click="showUpload = true">
            + Importer le premier document
          </button>
        </div>
      </div>
    </template>


    <!-- ── Upload modal ── -->
    <Teleport to="body">
      <div
        v-if="showUpload"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.75)"
        @click.self="showUpload = false"
      >
        <div class="card w-full max-w-md flex flex-col" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom: 1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Importer un document</h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive"
                    @click="showUpload = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <!-- Client shown as read-only -->
            <div class="rounded-xl px-4 py-3 text-sm font-medium"
                 style="background: var(--app-surface-2); border: 1px solid var(--app-border); color: var(--app-text)">
              Pour : {{ client?.raison_sociale }}
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
                id="detail-file-input"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                class="f-input cursor-pointer"
                style="padding: 0.5rem 0.78rem"
                @change="onFileChange"
              />
              <p v-if="form.file" class="text-xs mt-1" style="color: #22c55e">
                ✓ {{ form.file.name }}
              </p>
            </div>

            <div class="flex gap-3 justify-end pt-1">
              <button class="btn btn-outline btn-md" @click="showUpload = false">Annuler</button>
              <button
                class="btn btn-gold btn-md"
                :disabled="uploading || !form.file || !form.document_type_id"
                @click="submitUpload"
              >
                {{ uploading ? 'Import...' : 'Importer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>