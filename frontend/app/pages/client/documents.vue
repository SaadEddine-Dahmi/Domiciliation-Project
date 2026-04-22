<!-- Client portal: view and download own documents -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { error: toastError } = useToast()

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

const documents = ref<any[]>([])
const loading   = ref(true)
const search    = ref('')

async function fetchDocuments() {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/documents`,
      { headers: authHeaders() }
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
    d.document_type?.name?.toLowerCase().includes(q)
  )
})

function fmt(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function expiryInfo(d: string | null): { label: string; cls: string } {
  if (!d) return { label: '—', cls: '' }
  const days = Math.ceil((new Date(d).getTime() - Date.now()) / 86400000)
  if (days < 0)   return { label: `Expiré il y a ${Math.abs(days)}j`, cls: 'text-red-400' }
  if (days === 0) return { label: "Expire aujourd'hui", cls: 'text-yellow-400' }
  if (days < 30)  return { label: `Expire dans ${days} jour(s)`, cls: 'text-yellow-400' }
  return { label: fmt(d), cls: 'text-green-400' }
}

onMounted(fetchDocuments)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div>
      <h1 class="font-serif text-2xl">Mes <em class="italic" style="color:#c8a96e">Documents</em></h1>
      <p class="text-sm mt-1" style="color: var(--app-text-muted)">
        {{ documents.length }} document(s) disponible(s)
      </p>
    </div>

    <!-- Search -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
           width="15" height="15" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" stroke-linecap="round"
           style="color: var(--app-text-faint)">
        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
      </svg>
      <input v-model="search" class="f-input pl-9" placeholder="Rechercher un document..." />
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl shrink-0" style="background: var(--app-border)"/>
        <div class="flex-1 space-y-2">
          <div class="h-3 w-1/2 rounded" style="background: var(--app-border)"/>
          <div class="h-3 w-1/3 rounded" style="background: var(--app-border)"/>
        </div>
      </div>
    </div>

    <!-- Document cards -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="doc in filtered" :key="doc.id"
        class="card p-4 flex items-center gap-4 flex-wrap sm:flex-nowrap"
      >
        <!-- File icon -->
        <div
          class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
          style="background: rgba(200,169,110,0.1)"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
               stroke="#c8a96e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm" style="color: var(--app-text)">{{ doc.name }}</p>
          <div class="flex items-center gap-3 mt-0.5 flex-wrap text-xs">
            <span style="color: var(--app-text-faint)">
              Importé le {{ fmt(doc.created_at) }}
            </span>
            <span
              v-if="doc.date_expiration"
              class="font-medium"
              :class="expiryInfo(doc.date_expiration).cls"
            >
              {{ expiryInfo(doc.date_expiration).label }}
            </span>
          </div>
        </div>

        <!-- Download button -->
        <a
          :href="doc.download_url"
          target="_blank"
          class="btn btn-gold btn-sm shrink-0"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Télécharger
        </a>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-14 text-center" style="color: var(--app-text-faint)">
      <svg class="mx-auto mb-4 opacity-30" width="40" height="40" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
      </svg>
      <p class="font-medium mb-1">
        {{ search ? 'Aucun résultat' : 'Aucun document disponible' }}
      </p>
      <p class="text-sm">
        {{ search ? 'Modifiez votre recherche' : 'Vos documents apparaîtront ici une fois importés par votre domiciliataire.' }}
      </p>
    </div>

  </div>
</template>