<!-- ============================================================
  pages/client/documents.vue
  Documents du client — uniquement les siens, lecture seule
============================================================ -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

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

const documents  = ref<any[]>([])
const loading    = ref(true)
const loadError  = ref('')

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/documents`,
      { headers: authHeaders() }
    )
    documents.value = res.data ?? []
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

async function downloadDoc(doc: any): Promise<void> {
  try {
    const response = await fetch(doc.url, { headers: authHeaders() })
    const blob     = await response.blob()
    const blobUrl  = URL.createObjectURL(blob)
    const a        = document.createElement('a')
    a.href         = blobUrl
    a.download     = doc.name ?? 'document'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
  } catch {
    alert('Erreur lors du téléchargement')
  }
}

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <div>
      <h1 class="font-serif text-2xl">Mes <em class="text-gold italic">documents</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Documents mis à disposition par votre domiciliataire</p>
    </div>

    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="card p-4 animate-pulse">
        <div class="h-3 w-24 bg-white/10 rounded mb-2" />
        <div class="h-3 w-16 bg-white/10 rounded" />
      </div>
    </div>

    <!-- Liste documents -->
    <div v-else-if="documents.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="doc in documents" :key="doc.id"
        class="card p-4 space-y-3"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold text-sm">{{ doc.name }}</p>
            <p class="text-xs text-app-text/40 mt-1">
              Ajouté le {{ formatDate(doc.created_at) }}
            </p>
            <p v-if="doc.date_expiration" class="text-xs text-yellow-400 mt-0.5">
              Expire le {{ formatDate(doc.date_expiration) }}
            </p>
          </div>
          <span class="text-2xl">📄</span>
        </div>
        <button
          class="btn btn-outline btn-sm w-full"
          @click="downloadDoc(doc)"
        >
          ⬇ Télécharger
        </button>
      </div>
    </div>

    <!-- Vide -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📁</p>
      <p>Aucun document disponible pour le moment.</p>
      <p class="text-xs mt-2">Votre domiciliataire n'a pas encore partagé de documents.</p>
    </div>

  </div>
</template>