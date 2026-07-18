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
  } catch {
    return {}
  }
}

function filenameFromContentDisposition(cd: string | null): string {
  if (!cd) return ''
  const utf = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = cd.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || '').trim().replace(/^"|"$/g, '')
}

const contrat = ref<any>(null)
const loading = ref(true)
const loadError = ref('')
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
    // IMPORTANT: contract endpoint uses Bearer auth (not ?token query)
    const res = await fetch(contrat.value.pdf_url, {
      method: 'GET',
      headers: authHeaders(),
    })

    if (!res.ok) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        alert(j?.message || 'Erreur lors du téléchargement')
      } catch {
        alert('Erreur lors du téléchargement')
      }
      return
    }

    const contentType = (res.headers.get('content-type') || '').toLowerCase()
    if (
      contentType.includes('application/json') ||
      contentType.includes('text/plain') ||
      contentType.includes('text/html')
    ) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        alert(j?.message || 'Erreur lors du téléchargement')
      } catch {
        alert('Erreur lors du téléchargement (réponse non fichier)')
      }
      return
    }

    const blob = await res.blob()
    let filename = filenameFromContentDisposition(res.headers.get('content-disposition'))
    if (!filename) filename = 'contrat-domiciliation.pdf'
    if (!filename.toLowerCase().endsWith('.pdf')) filename += '.pdf'

    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(blobUrl)
  } catch {
    alert('Erreur lors du tél��chargement')
  } finally {
    downloading.value = false
  }
}

const statutColor: Record<string, string> = {
  draft: 'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
  expired: 'text-red-400 bg-red-400/10',
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

      <div v-if="contrat.pdf_url" class="pt-2">
        <button class="btn btn-gold btn-md" :disabled="downloading" @click="downloadPdf">
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