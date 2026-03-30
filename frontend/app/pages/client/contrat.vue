<!-- ============================================================
  pages/client/contrat.vue
  Contrat du client — affiche et permet de télécharger le PDF
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

const contrat    = ref<any>(null)
const loading    = ref(true)
const loadError  = ref('')
const downloading = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/dashboard/stats`,
      { headers: authHeaders() }
    )
    contrat.value = res.data?.contrat ?? null
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

async function downloadPdf(): Promise<void> {
  if (!contrat.value?.pdf_url) return
  downloading.value = true
  try {
    const response = await fetch(contrat.value.pdf_url)
    const blob     = await response.blob()
    const blobUrl  = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
    const a        = document.createElement('a')
    a.href         = blobUrl
    a.download     = `contrat-domiciliation.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
  } catch {
    alert('Erreur lors du téléchargement')
  } finally {
    downloading.value = false
  }
}

const statutColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-2xl">

    <div>
      <h1 class="font-serif text-2xl">Mon <em class="text-gold italic">contrat</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Contrat de domiciliation commerciale</p>
    </div>

    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="card p-6 animate-pulse space-y-3">
      <div class="h-4 w-32 bg-white/10 rounded" />
      <div class="h-4 w-48 bg-white/10 rounded" />
      <div class="h-10 w-40 bg-white/10 rounded" />
    </div>

    <!-- Contrat trouvé -->
    <div v-else-if="contrat" class="card p-6 space-y-4">
      <div class="flex items-center gap-3 flex-wrap">
        <span
          class="text-xs px-3 py-1 rounded-full font-bold"
          :class="statutColor[contrat.statut] ?? 'text-app-text/40 bg-white/5'"
        >
          {{ contrat.statut?.toUpperCase() }}
        </span>
        <p class="text-sm text-app-text/50">Contrat #{{ contrat.id }}</p>
      </div>

      <div class="space-y-2 text-sm">
        <p><span class="text-app-text/40">Date de début :</span> <b>{{ contrat.date_debut ?? '-' }}</b></p>
        <p><span class="text-app-text/40">Date de fin :</span> <b>{{ contrat.date_fin ?? '-' }}</b></p>
        <p v-if="contrat.prix_total">
          <span class="text-app-text/40">Montant total :</span>
          <b class="text-gold ml-1">{{ contrat.prix_total }} DH</b>
        </p>
      </div>

      <!-- PDF disponible -->
      <div v-if="contrat.pdf_url" class="pt-2">
        <button
          class="btn btn-gold btn-md"
          :disabled="downloading"
          @click="downloadPdf"
        >
          {{ downloading ? 'Téléchargement...' : '⬇ Télécharger le PDF' }}
        </button>
      </div>
      <div v-else class="rounded-xl border border-white/10 p-3 text-sm text-app-text/40">
        Le PDF de votre contrat n'est pas encore généré. Contactez votre domiciliataire.
      </div>
    </div>

    <!-- Pas de contrat -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>Aucun contrat disponible pour le moment.</p>
      <p class="text-xs mt-2">Contactez votre domiciliataire pour plus d'informations.</p>
    </div>

  </div>
</template>