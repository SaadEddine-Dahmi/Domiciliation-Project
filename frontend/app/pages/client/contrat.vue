<!-- ============================================================
  pages/client/contrat.vue
  The client's own contract(s) — legalised status, live preview, and
  download. Previously this page read `pdf_url` off the dashboard-stats
  response, a field tied to a storage path that's no longer populated
  under the render-on-demand PDF strategy — so the download button was
  always dead, and legalised contracts were never distinguished from
  drafts. It also only ever showed the single latest contract.

  Now: fetches every contract belonging to the client's entreprise via
  contratService.list() (GET /api/contrats — client-role branch added
  server-side), and previews/downloads through contratService.streamPdfUrl(),
  which resolves to the real signed document once a contract is
  legalised, or a live render otherwise — same single source of truth
  used on the admin side.
============================================================ -->
<script setup lang="ts">
import { contratService } from '~/services/contrat.service'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { error: toastError } = useToast()
const route = useRoute()

const contrats = ref<any[]>([])
const loading = ref(true)
const loadError = ref('')

async function load(): Promise<void> {
  loading.value = true
  loadError.value = ''
  try {
    const res = await contratService.list()
    contrats.value = res.data ?? []
    openContractFromQuery()
  } catch (e: any) {
    loadError.value = e?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
}

/** True once a physically signed PDF has been uploaded — from that
 *  point on, streamPdf() serves that exact file for both preview and
 *  download instead of a live re-render. */
function isLegalised(c: any): boolean {
  return !!c.scanned_pdf_path
}

const statutColor: Record<string, string> = {
  draft: 'text-yellow-400 bg-yellow-400/10',
  active: 'text-green-400 bg-green-400/10',
  expired: 'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

const statutLabel: Record<string, string> = {
  draft: 'Brouillon',
  active: 'Actif',
  expired: 'Expiré',
  terminated: 'Résilié',
}

// ── Preview modal ──────────────────────────────────────────────
const showPreview = ref(false)
const previewUrl = ref('')
const previewTitle = ref('')
const previewLoading = ref(false)

function openPreview(c: any): void {
  previewTitle.value = c.titre_contrat ?? `Contrat #${c.id}`
  previewUrl.value = contratService.streamPdfUrl(String(c.id), 'preview')
  previewLoading.value = true
  showPreview.value = true
}

function openContractFromQuery(): void {
  const contratId = Number(route.query.contrat_id)
  if (!contratId) return
  const contrat = contrats.value.find(c => Number(c.id) === contratId)
  if (contrat) openPreview(contrat)
}

function closePreview(): void {
  showPreview.value = false
  previewUrl.value = ''
}

function downloadContrat(c: any): void {
  const url = contratService.streamPdfUrl(String(c.id), 'download')
  const a = document.createElement('a')
  a.href = url
  a.download = `contrat_${c.id}.pdf`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

watch(() => route.query.contrat_id, openContractFromQuery)

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-2xl">
    <div>
      <h1 class="font-serif text-2xl">Mon <em class="text-gold italic">contrat</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Contrat de domiciliation</p>
    </div>

    <div v-if="loadError" class="card p-4 text-red-400 text-sm">{{ loadError }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="card p-6 animate-pulse space-y-3">
      <div class="h-4 w-32 bg-white/10 rounded" />
      <div class="h-4 w-48 bg-white/10 rounded" />
      <div class="h-10 w-40 bg-white/10 rounded" />
    </div>

    <!-- Contract cards -->
    <div v-else-if="contrats.length" class="space-y-4">
      <div v-for="c in contrats" :key="c.id" class="card p-6 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
          <div>
            <p class="font-serif text-lg" style="color: var(--app-text)">
              {{ c.titre_contrat ?? `Contrat #${c.id}` }}
            </p>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
              <span
                class="text-xs px-3 py-1 rounded-full font-bold"
                :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
              >
                {{ statutLabel[c.statut] ?? c.statut?.toUpperCase() }}
              </span>
              <span
                v-if="isLegalised(c)"
                class="text-xs px-3 py-1 rounded-full font-bold text-green-400 bg-green-400/10"
                title="Un document signé a été importé pour ce contrat"
              >
                ✓ Légalisé
              </span>
            </div>
          </div>
        </div>

        <div class="space-y-2 text-sm">
          <p><span class="text-app-text/40">Date de début :</span> <b>{{ c.date_debut ?? '-' }}</b></p>
          <p><span class="text-app-text/40">Date de fin :</span> <b>{{ c.date_fin ?? '-' }}</b></p>
          <p v-if="c.prix_total">
            <span class="text-app-text/40">Montant total :</span>
            <b class="text-gold ml-1">{{ c.prix_total }} DH</b>
          </p>
        </div>

        <div class="flex gap-3 pt-2">
          <button class="btn btn-outline btn-md" @click="openPreview(c)">
            👁 Aperçu
          </button>
          <button class="btn btn-gold btn-md" @click="downloadContrat(c)">
            ⬇ Télécharger le PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Pas de contrat -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>Aucun contrat disponible pour le moment.</p>
      <p class="text-xs mt-2">Contactez votre domiciliataire pour plus d'informations.</p>
    </div>

    <!-- PDF preview modal — resolves to the real signed document once
         legalised, or a live render otherwise (streamPdf, server-side). -->
    <Teleport to="body">
      <div v-if="showPreview" class="fixed inset-0 z-[300] flex flex-col p-4 md:p-8" style="background: rgba(0,0,0,0.92)">
        <div class="flex justify-between items-center mb-4 text-white">
          <h3 class="text-lg font-serif">{{ previewTitle }}</h3>
          <button @click="closePreview" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white">✕</button>
        </div>
        <div class="flex-1 bg-white rounded-xl overflow-hidden relative shadow-2xl">
          <div v-if="previewLoading" class="absolute inset-0 flex items-center justify-center bg-black/40 z-10">
            <div class="text-center text-white">
              <div class="w-8 h-8 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto mb-3"/>
              <p class="text-sm">Chargement du contrat...</p>
            </div>
          </div>
          <iframe :src="previewUrl" class="w-full h-full" frameborder="0" @load="previewLoading = false" />
        </div>
      </div>
    </Teleport>
  </div>
</template>
