<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { error: toastError } = useToast()
const documentViewer = useDocumentViewer()

function getApiBase() {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const parsed = JSON.parse(localStorage.getItem('app_auth') ?? '{}')
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch {
    return {}
  }
}

const documents = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const downloadingId = ref<number | null>(null)
const previewingId = ref<number | null>(null)

async function fetchDocuments() {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/documents`,
      { headers: authHeaders() },
    )
    documents.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement documents')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!search.value.trim()) return documents.value
  const q = search.value.toLowerCase()
  return documents.value.filter(d =>
    d.name?.toLowerCase().includes(q) ||
    d.document_type?.name?.toLowerCase().includes(q),
  )
})

function fmt(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

function expiryInfo(d: string | null): { label: string; cls: string } {
  if (!d) return { label: '-', cls: '' }
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0) return { label: `Expire il y a ${Math.abs(days)}j`, cls: 'text-red-400' }
  if (days === 0) return { label: "Expire aujourd'hui", cls: 'text-yellow-400' }
  if (days < 30) return { label: `Expire dans ${days} jour(s)`, cls: 'text-yellow-400' }
  return { label: fmt(d), cls: 'text-green-400' }
}

async function previewDoc(doc: any): Promise<void> {
  previewingId.value = doc.id
  await documentViewer.openPreview({
    id: doc.id,
    name: doc.name,
    extension: doc.extension,
    redirectTo: '/client/documents',
  })
  previewingId.value = null
}

async function downloadDoc(doc: any): Promise<void> {
  downloadingId.value = doc.id
  await documentViewer.downloadDocument({
    id: doc.id,
    name: doc.name,
    extension: doc.extension,
    redirectTo: '/client/documents',
  })
  downloadingId.value = null
}

onMounted(fetchDocuments)
</script>

<template>
  <div class="space-y-5 animate-fade-up">
    <div>
      <h1 class="font-serif text-2xl">Mes <em class="italic" style="color:#c8a96e">Documents</em></h1>
      <p class="text-sm mt-1" style="color: var(--app-text-muted)">
        {{ documents.length }} document(s) disponible(s)
      </p>
    </div>

    <div class="relative">
      <UiIcon
        name="scan"
        class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
        style="color: var(--app-text-faint)"
        :size="15"
      />
      <input v-model="search" class="f-input pl-9" placeholder="Rechercher un document..." />
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl shrink-0" style="background: var(--app-border)" />
        <div class="flex-1 space-y-2">
          <div class="h-3 w-1/2 rounded" style="background: var(--app-border)" />
          <div class="h-3 w-1/3 rounded" style="background: var(--app-border)" />
        </div>
      </div>
    </div>

    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="doc in filtered"
        :key="doc.id"
        class="card p-4 flex items-center gap-4 flex-wrap sm:flex-nowrap"
      >
        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style="background: rgba(200,169,110,0.1)">
          <UiIcon name="contract" :size="20" style="color:#c8a96e" />
        </div>

        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm" style="color: var(--app-text)">{{ doc.name }}</p>
          <div class="flex items-center gap-3 mt-0.5 flex-wrap text-xs">
            <span style="color: var(--app-text-faint)">Importe le {{ fmt(doc.created_at) }}</span>
            <span v-if="doc.date_expiration" class="font-medium" :class="expiryInfo(doc.date_expiration).cls">
              {{ expiryInfo(doc.date_expiration).label }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button class="btn btn-outline btn-sm" :disabled="previewingId === doc.id" @click="previewDoc(doc)">
            <UiIcon name="eye" :size="13" />
            {{ previewingId === doc.id ? 'Ouverture...' : 'Apercu' }}
          </button>

          <button class="btn btn-gold btn-sm" :disabled="downloadingId === doc.id" @click="downloadDoc(doc)">
            <UiIcon name="download" :size="13" />
            {{ downloadingId === doc.id ? 'Telechargement...' : 'Telecharger' }}
          </button>
        </div>
      </div>
    </div>

    <div v-else class="card p-14 text-center" style="color: var(--app-text-faint)">
      <UiIcon name="contract" class="mx-auto mb-4 opacity-30" :size="40" />
      <p class="font-medium mb-1">
        {{ search ? 'Aucun resultat' : 'Aucun document disponible' }}
      </p>
      <p class="text-sm">
        {{ search ? 'Modifiez votre recherche' : 'Vos documents apparaitront ici une fois importes par votre domiciliataire.' }}
      </p>
    </div>
  </div>
</template>
