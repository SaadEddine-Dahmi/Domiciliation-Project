<!-- ============================================================
  pages/admin/scan.vue
  Import de documents avec :
  - Aperçu du fichier (image ou PDF)
  - Sélection du client (dropdown DB)
  - Type de document avec création inline si absent
  - Date d'expiration optionnelle
  - Upload vers l'API
============================================================ -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems } = storeToRefs(clientsStore)
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
const file              = ref<File | null>(null)
const preview           = ref<string | null>(null)
const isPdf             = ref(false)
const uploading         = ref(false)

const documentTypes     = ref<any[]>([])
const loadingTypes      = ref(false)

// Formulaire
const form = reactive({
  entreprise_id:    null as number | null,
  document_type_id: null as number | null,
  date_expiration:  '',
})

// Création nouveau type inline
const showNewType    = ref(false)
const newTypeName    = ref('')
const newTypeExpires = ref(false)
const creatingType   = ref(false)

// ── Chargement données ────────────────────────────────────
async function loadTypes(): Promise<void> {
  loadingTypes.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/document-types`,
      { headers: authHeaders() }
    )
    documentTypes.value = res.data ?? []
  } catch {} finally {
    loadingTypes.value = false
  }
}

// ── Gestion fichier ───────────────────────────────────────
function onFileChange(e: Event): void {
  const input = e.target as HTMLInputElement
  const f     = input.files?.[0]
  if (!f) return
  file.value  = f
  isPdf.value = f.type === 'application/pdf'
  preview.value = null

  if (!isPdf.value) {
    const reader = new FileReader()
    reader.onload = (ev) => { preview.value = ev.target?.result as string }
    reader.readAsDataURL(f)
  }
}

function clearFile(): void {
  file.value    = null
  preview.value = null
  isPdf.value   = false
}

// ── Créer un nouveau type de document ────────────────────
async function createNewType(): Promise<void> {
  if (!newTypeName.value.trim()) return
  creatingType.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/document-types`,
      {
        method:  'POST',
        headers: authHeaders(),
        body: {
          name:           newTypeName.value.trim(),
          has_expiration: newTypeExpires.value,
          is_required:    false,
        },
      }
    )
    documentTypes.value.push(res.data)
    form.document_type_id = res.data.id
    showNewType.value  = false
    newTypeName.value  = ''
    newTypeExpires.value = false
    success(`Type "${res.data.name}" créé`)
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur création type')
  } finally {
    creatingType.value = false
  }
}

// ── Upload ────────────────────────────────────────────────
async function importDoc(): Promise<void> {
  if (!file.value)              return toastError?.('Sélectionnez un fichier')
  if (!form.entreprise_id)      return toastError?.('Sélectionnez un client')
  if (!form.document_type_id)   return toastError?.('Sélectionnez un type de document')

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file',             file.value)
    fd.append('entreprise_id',    String(form.entreprise_id))
    fd.append('document_type_id', String(form.document_type_id))
    if (form.date_expiration) fd.append('date_expiration', form.date_expiration)

    await fetch(`${getApiBase()}/api/documents`, {
      method:  'POST',
      headers: authHeaders(),
      body:    fd,
    })

    success('Document importé et associé au client ✓')
    clearFile()
    Object.assign(form, { entreprise_id: null, document_type_id: null, date_expiration: '' })
  } catch (e: any) {
    toastError?.(e?.message ?? 'Erreur lors de l\'import')
  } finally {
    uploading.value = false
  }
}

onMounted(async () => {
  await clientsStore.fetchAll()
  await loadTypes()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <div>
      <h1 class="font-serif text-2xl">Scanner & <em class="text-gold italic">Importer</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Importez un document et associez-le à un client</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- Zone de fichier -->
      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Document</p>

        <!-- Drop zone -->
        <label
          class="block rounded-xl border-2 border-dashed border-white/20 p-8 text-center cursor-pointer hover:border-gold/40 transition"
          :class="file ? 'border-green-500/40 bg-green-500/5' : ''"
        >
          <input type="file" class="hidden" accept="image/*,.pdf" @change="onFileChange" />
          <div v-if="!file" class="space-y-2">
            <p class="text-3xl">📎</p>
            <p class="text-sm font-semibold">Cliquez ou glissez un fichier</p>
            <p class="text-xs text-app-text/40">PDF, JPG, PNG — max 10MB</p>
          </div>
          <div v-else class="space-y-2">
            <p class="text-2xl">{{ isPdf ? '📄' : '🖼' }}</p>
            <p class="text-sm font-semibold text-green-400">{{ file.name }}</p>
            <p class="text-xs text-app-text/40">{{ (file.size / 1024).toFixed(0) }} KB</p>
          </div>
        </label>

        <!-- Aperçu image -->
        <div v-if="preview" class="rounded-xl overflow-hidden border border-white/10">
          <img :src="preview" class="w-full max-h-56 object-contain bg-white" />
        </div>

        <!-- Aperçu PDF -->
        <div v-if="isPdf && file" class="rounded-xl border border-gold/20 bg-gold/5 p-3 text-center">
          <p class="text-2xl mb-1">📄</p>
          <p class="text-sm text-gold font-semibold">{{ file.name }}</p>
          <p class="text-xs text-app-text/40">Fichier PDF sélectionné</p>
        </div>

        <button v-if="file" class="btn btn-outline btn-sm w-full" @click="clearFile">
          ✕ Supprimer le fichier
        </button>
      </div>

      <!-- Formulaire association -->
      <div class="card p-5 space-y-4">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Association</p>

        <!-- Client -->
        <div>
          <label class="f-label">Entreprise cliente *</label>
          <select v-model="form.entreprise_id" class="f-input">
            <option :value="null" disabled>-- Sélectionner un client --</option>
            <option v-for="c in clientItems" :key="c.id" :value="c.id">
              {{ c.raison_sociale }}
            </option>
          </select>
        </div>

        <!-- Type de document avec création inline -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="f-label mb-0">Type de document *</label>
            <button
              class="text-xs text-gold underline"
              @click="showNewType = !showNewType"
            >
              {{ showNewType ? 'Annuler' : '+ Nouveau type' }}
            </button>
          </div>

          <select v-if="!showNewType" v-model="form.document_type_id" class="f-input">
            <option :value="null" disabled>-- Sélectionner --</option>
            <option v-for="t in documentTypes" :key="t.id" :value="t.id">
              {{ t.name }}{{ t.has_expiration ? ' (avec expiration)' : '' }}
            </option>
          </select>

          <!-- Création nouveau type inline -->
          <div v-else class="rounded-xl border border-gold/20 bg-gold/5 p-3 space-y-2">
            <p class="text-xs font-bold text-gold">Créer un nouveau type</p>
            <input
              v-model="newTypeName"
              class="f-input"
              placeholder="Ex: Attestation fiscale, Extrait RC..."
            />
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="newTypeExpires" type="checkbox" class="rounded" />
              Ce type a une date d'expiration
            </label>
            <button
              class="btn btn-gold btn-sm w-full"
              :disabled="creatingType || !newTypeName.trim()"
              @click="createNewType"
            >
              {{ creatingType ? 'Création...' : '+ Créer ce type' }}
            </button>
          </div>
        </div>

        <!-- Date d'expiration -->
        <div>
          <label class="f-label">Date d'expiration (optionnel)</label>
          <input v-model="form.date_expiration" class="f-input" type="date" />
        </div>

        <!-- Bouton import -->
        <button
          class="btn btn-gold btn-lg w-full justify-center mt-2"
          :disabled="uploading || !file || !form.entreprise_id || !form.document_type_id"
          @click="importDoc"
        >
          {{ uploading ? '⏳ Import en cours...' : '⬆ Importer et associer' }}
        </button>
      </div>
    </div>

  </div>
</template>