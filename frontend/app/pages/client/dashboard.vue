<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()

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

const loading = ref(true)
const data = ref<any>(null)
const messages = ref<any[]>([])
const loadError = ref('')
const downloadingContrat = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const [statsRes, msgsRes] = await Promise.all([
      $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/dashboard/stats`,
        { headers: authHeaders() }
      ),
      $fetch<{ success: boolean; data: any[] }>(
        `${getApiBase()}/api/messages`,
        { headers: authHeaders() }
      ),
    ])

    data.value = statsRes.data
    messages.value = (msgsRes.data ?? []).slice(0, 3)
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

async function downloadContrat(): Promise<void> {
  const url = data.value?.contrat?.pdf_url
  if (!url) return

  downloadingContrat.value = true
  try {
    // IMPORTANT: contract endpoint uses Bearer auth
    const res = await fetch(url, {
      method: 'GET',
      headers: authHeaders(),
    })

    if (!res.ok) {
      const txt = await res.text()
      try {
        const j = JSON.parse(txt)
        loadError.value = j?.message || 'Erreur téléchargement contrat'
      } catch {
        loadError.value = 'Erreur téléchargement contrat'
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
        loadError.value = j?.message || 'Erreur téléchargement contrat'
      } catch {
        loadError.value = 'Erreur téléchargement contrat (réponse non fichier)'
      }
      return
    }

    const blob = await res.blob()
    let filename = filenameFromContentDisposition(res.headers.get('content-disposition'))
    if (!filename) filename = 'mon-contrat.pdf'
    if (!filename.toLowerCase().endsWith('.pdf')) filename += '.pdf'

    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(blobUrl)
  } catch {
    loadError.value = 'Erreur téléchargement contrat'
  } finally {
    downloadingContrat.value = false
  }
}

const unreadMessages = computed(() => messages.value.filter(m => !m.is_read).length)

const statutColor: Record<string, string> = {
  draft: 'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
  expired: 'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
  actif: 'text-green-400 bg-green-400/10',
  inactif: 'text-red-400 bg-red-400/10',
}

function formatDate(d: string | null): string {
  if (!d) return '-'
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up">
    <!-- Titre -->
    <div>
      <h2 class="font-serif text-[22px]">
        Bonjour, <em class="text-gold italic">{{ auth.user?.name ?? 'Client' }}</em> 👋
      </h2>
      <p class="text-app-text/50 text-sm mt-1">Votre espace personnel de domiciliation</p>
    </div>

    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in 2" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 w-24 bg-white/10 rounded mb-3" /><div class="h-6 w-32 bg-white/10 rounded" />
      </div>
    </div>

    <!-- Contenu principal -->
    <div v-else-if="data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Mon entreprise -->
      <div class="card p-5 space-y-3">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon entreprise</p>
        <div v-if="data.entreprise" class="space-y-1">
          <p class="font-serif text-lg">{{ data.entreprise.raison_sociale }}</p>
          <p class="text-sm text-app-text/50">{{ data.entreprise.ville ?? '-' }}</p>
          <span class="inline-block text-xs px-2 py-0.5 rounded-full" :class="statutColor[data.entreprise.statut] ?? 'text-app-text/40 bg-white/5'">
            {{ data.entreprise.statut }}
          </span>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucune entreprise liée</p>
      </div>

      <!-- Mon domiciliataire -->
      <div class="card p-5 space-y-3">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon domiciliataire</p>
        <div v-if="data.domiciliataire" class="space-y-1 text-sm">
          <p class="font-semibold">{{ data.domiciliataire.nom }} {{ data.domiciliataire.prenom }}</p>
          <p class="text-app-text/50">{{ data.domiciliataire.email }}</p>
          <p v-if="data.domiciliataire.telephone" class="text-app-text/50">{{ data.domiciliataire.telephone }}</p>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucun domiciliataire lié</p>
      </div>

      <!-- Mon contrat -->
      <div class="card p-5 space-y-3">
        <p class="text-xs uppercase text-gold tracking-widest font-bold">Mon contrat</p>
        <div v-if="data.contrat" class="space-y-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statutColor[data.contrat.statut] ?? 'text-app-text/40 bg-white/5'">
              {{ data.contrat.statut?.toUpperCase() }}
            </span>
            <p class="text-xs text-app-text/50">{{ data.contrat.date_debut }} → {{ data.contrat.date_fin }}</p>
          </div>
          <p v-if="data.contrat.prix_total" class="text-sm font-semibold text-gold">{{ data.contrat.prix_total }} DH</p>
          <button
            v-if="data.contrat.pdf_url"
            class="btn btn-gold btn-sm"
            :disabled="downloadingContrat"
            @click="downloadContrat"
          >
            {{ downloadingContrat ? 'Téléchargement...' : '⬇ Télécharger le contrat PDF' }}
          </button>
          <p v-else class="text-xs text-app-text/40">PDF non disponible — contactez votre domiciliataire.</p>
        </div>
        <p v-else class="text-app-text/40 text-sm">Aucun contrat disponible</p>
      </div>

      <!-- Messages reçus -->
      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <p class="text-xs uppercase text-gold tracking-widest font-bold">
            Messages
            <span v-if="unreadMessages > 0" class="ml-1 px-1.5 py-0.5 rounded-full bg-gold/20 text-gold text-[10px]">
              {{ unreadMessages }}
            </span>
          </p>
          <NuxtLink to="/client/messages" class="text-xs text-gold underline">Voir tout</NuxtLink>
        </div>

        <div v-if="messages.length" class="space-y-2">
          <NuxtLink
            v-for="m in messages"
            :key="m.id"
            to="/client/messages"
            class="flex items-start gap-2 p-2 rounded-lg hover:bg-white/5 transition"
          >
            <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" :class="!m.is_read ? 'bg-gold' : 'bg-transparent'" />
            <div class="min-w-0">
              <p class="text-xs font-medium truncate" :class="!m.is_read ? '' : 'text-app-text/60'">
                {{ m.subject || 'Message de votre domiciliataire' }}
              </p>
              <p class="text-[10px] text-app-text/40 truncate">{{ m.message }}</p>
              <p class="text-[10px] text-app-text/30">{{ formatDate(m.created_at) }}</p>
            </div>
          </NuxtLink>
        </div>

        <div v-else class="text-center py-2">
          <p class="text-xs text-app-text/40">Aucun message reçu</p>
        </div>
      </div>
    </div>

    <!-- Accès rapides -->
    <div class="grid grid-cols-3 gap-3">
      <NuxtLink to="/client/documents" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">📁</div><div class="text-xs font-semibold">Mes Documents</div>
      </NuxtLink>
      <NuxtLink to="/client/contrat" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">📄</div><div class="text-xs font-semibold">Mon Contrat</div>
      </NuxtLink>
      <NuxtLink to="/client/messages" class="card p-4 text-center hover:border-gold/30 transition" style="border:1px solid rgba(255,255,255,0.06)">
        <div class="text-2xl mb-2">✉</div>
        <div class="text-xs font-semibold">
          Messages
          <span v-if="unreadMessages > 0" class="ml-1 px-1 rounded-full bg-gold/20 text-gold text-[10px]">{{ unreadMessages }}</span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>