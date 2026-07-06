<!-- pages/admin/contrats.vue

  Contract list page. Displays every contract belonging to the
  authenticated domiciliataire and exposes the state-machine actions:
    - Generate PDF                 (draft)
    - Activate (upload signed PDF) (draft -> active)
    - Terminate                    (active -> terminated)
    - Renew                        (active or expired -> new draft)

  KNOWN LIMITATION (not addressed here, tracked separately): the wizard
  at pages/admin/contrat.vue does not currently read route.query.id on
  mount to load an existing contract — it always starts blank. This
  affects the pre-existing "Éditer" button on draft contracts too, not
  just this feature. Renewal therefore reloads the list rather than
  redirecting into the wizard, so the new draft is visible with its own
  actions immediately.
-->
<script setup lang="ts">
import { contratService } from '~/services/contrat.service'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()
const router = useRouter()

// ── Auth helpers ──────────────────────────────────────────────────────────

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── State ─────────────────────────────────────────────────────────────────

const items        = ref<any[]>([])
const loading      = ref(true)
const q            = ref('')
const activating   = ref<number | null>(null)
const showActivate = ref(false)
const activateId   = ref<number | null>(null)
const signedFile   = ref<File | null>(null)

// ── Load contracts ──────────────────────────────────────────────────────

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/contrats`,
      { headers: authHeaders() }
    )
    items.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!q.value.trim()) return items.value
  const term = q.value.toLowerCase()
  return items.value.filter(c =>
    c.entreprise?.raison_sociale?.toLowerCase().includes(term) ||
    c.statut?.toLowerCase().includes(term)
  )
})

// ── Download PDF (generates first if not yet saved) ─────────────────────

async function downloadPdf(contrat: any): Promise<void> {
  if (!contrat.pdf_path) {
    const res = await $fetch<{ success: boolean; data: { url: string } }>(
      `${getApiBase()}/api/contrats/${contrat.id}/pdf`,
      { method: 'POST', headers: authHeaders() }
    )
    contrat.pdf_path = res.data.url
  }
  const url  = `${getApiBase()}/storage/${contrat.pdf_path}`
  const resp = await fetch(url)
  const blob = await resp.blob()
  const a    = document.createElement('a')
  a.href     = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }))
  a.download = `contrat_${contrat.id}.pdf`
  document.body.appendChild(a); a.click(); document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(a.href), 3000)
}

// ── Activate (upload signed PDF) ─────────────────────────────────────────

function openActivate(id: number): void {
  activateId.value   = id
  signedFile.value   = null
  showActivate.value = true
}

function onSignedFileChange(e: Event): void {
  const input = e.target as HTMLInputElement
  signedFile.value = input.files?.[0] ?? null
}

async function submitActivate(): Promise<void> {
  if (!activateId.value || !signedFile.value) {
    toastError?.('Sélectionnez le PDF signé')
    return
  }
  activating.value = activateId.value
  try {
    const fd = new FormData()
    fd.append('signed_pdf', signedFile.value)
    await fetch(`${getApiBase()}/api/contrats/${activateId.value}/activate`, {
      method: 'POST',
      headers: authHeaders(),
      body: fd,
    })
    success('Contrat activé ✓')
    showActivate.value = false
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur activation')
  } finally {
    activating.value = null
  }
}

// ── Terminate ─────────────────────────────────────────────────────────────

async function terminate(id: number): Promise<void> {
  if (!confirm('Résilier ce contrat ? Cette action est irréversible.')) return
  try {
    await $fetch(`${getApiBase()}/api/contrats/${id}/terminate`, {
      method: 'POST',
      headers: authHeaders(),
    })
    success('Contrat résilié')
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur résiliation')
  }
}

// ── Renewal ───────────────────────────────────────────────────────────────
//
// isRenewable() mirrors the backend rule (Contrat::isRenewable()): only
// active/expired contracts that have not already been renewed. index()
// eager-loads renewed_to for exactly this reason. This client-side check
// is a UX convenience — the server enforces the real rule.

function isRenewable(c: any): boolean {
  return ['active', 'expired'].includes(c.statut) && !c.renewed_to
}

const showRenewModal = ref(false)
const renewSource    = ref<any>(null)
const renewing       = ref(false)

const renewForm = reactive({
  titre_contrat:  '',
  date_debut:     '',
  duree_mois:     12,
  prix_mensuel:   0,
  prix_total:     0,
  carry_articles: true,
})

/**
 * Open the renewal modal and pre-fill it with continuity defaults —
 * mirroring what the backend would compute automatically. The person can
 * review and adjust everything, including the contract title, before
 * submitting.
 */
function openRenew(contrat: any): void {
  renewSource.value = contrat

  const base = contrat.date_fin ? new Date(contrat.date_fin) : new Date()
  base.setDate(base.getDate() + 1)

  Object.assign(renewForm, {
    titre_contrat:  contrat.titre_contrat ?? '',
    date_debut:     base.toISOString().split('T')[0],
    duree_mois:     contrat.duree_mois ?? 12,
    prix_mensuel:   Number(contrat.prix_mensuel ?? 0),
    prix_total:     Number(contrat.prix_total ?? 0),
    carry_articles: true,
  })

  showRenewModal.value = true
}

async function submitRenew(): Promise<void> {
  if (!renewSource.value) return
  renewing.value = true
  try {
    await contratService.renew(String(renewSource.value.id), { ...renewForm })
    success('Contrat renouvelé — nouveau brouillon créé ✓')
    showRenewModal.value = false
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors du renouvellement')
  } finally {
    renewing.value = false
  }
}

// ── Status labels ─────────────────────────────────────────────────────────

const statutColor: Record<string, string> = {
  draft:      'text-yellow-400 bg-yellow-400/10',
  active:     'text-green-400 bg-green-400/10',
  expired:    'text-red-400 bg-red-400/10',
  terminated: 'text-gray-400 bg-gray-400/10',
}

const statutLabel: Record<string, string> = {
  draft:      'Brouillon',
  active:     'Actif',
  expired:    'Expiré',
  terminated: 'Résilié',
}

onMounted(load)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Contrats <em class="text-gold italic">de domiciliation</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ items.length }} contrat(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="router.push('/admin/contrat?new=1')">
        + Nouveau contrat
      </button>
    </div>

    <!-- Search -->
    <div class="card p-3">
      <input v-model="q" class="f-input" placeholder="Rechercher par entreprise, statut..." />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- List -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="c in filtered" :key="c.id"
        class="card p-4 flex items-center justify-between flex-wrap gap-3"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-semibold">{{ c.entreprise?.raison_sociale ?? `Contrat #${c.id}` }}</p>
            <span
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ statutLabel[c.statut] ?? c.statut }}
            </span>
            <span
              v-if="c.renewed_from_id"
              class="text-xs px-2 py-0.5 rounded-full font-medium text-blue-400 bg-blue-400/10"
              title="Ce contrat est le résultat d'un renouvellement"
            >
              ↻ Renouvellement
            </span>
          </div>
          <p class="text-xs text-app-text/50 mt-1">
            {{ c.date_debut ?? '-' }} → {{ c.date_fin ?? '-' }}
            <span v-if="c.prix_total" class="ml-2 text-gold font-medium">{{ c.prix_total }} DH</span>
          </p>
        </div>

        <!-- Actions per status -->
        <div class="flex gap-2 flex-wrap">

          <!-- Draft: generate PDF + activate -->
          <template v-if="c.statut === 'draft'">
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button class="btn btn-gold btn-sm" @click="openActivate(c.id)">
              ✓ Activer (PDF signé)
            </button>
            <button class="btn btn-outline btn-sm" @click="router.push(`/admin/contrat?id=${c.id}`)">
              Éditer
            </button>
          </template>

          <!-- Active: download + renew + terminate -->
          <template v-else-if="c.statut === 'active'">
            <button class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button v-if="isRenewable(c)" class="btn btn-gold btn-sm" @click="openRenew(c)">
              ↻ Renouveler
            </button>
            <button class="btn btn-danger btn-sm" @click="terminate(c.id)">Résilier</button>
          </template>

          <!-- Expired: download (if any) + renew -->
          <template v-else-if="c.statut === 'expired'">
            <button v-if="c.pdf_path" class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <button v-if="isRenewable(c)" class="btn btn-gold btn-sm" @click="openRenew(c)">
              ↻ Renouveler
            </button>
          </template>

          <!-- Terminated: read-only -->
          <template v-else>
            <button v-if="c.pdf_path" class="btn btn-outline btn-sm" @click="downloadPdf(c)">⬇ PDF</button>
            <span class="text-xs text-app-text/40">Lecture seule</span>
          </template>

        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>Aucun contrat trouvé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="router.push('/admin/contrat?new=1')">
        Créer le premier contrat
      </button>
    </div>

    <!-- Modal: Activation (upload signed PDF) -->
    <div
      v-if="showActivate"
      class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
      @click.self="showActivate = false"
    >
      <div class="card w-full max-w-md p-6 space-y-4">
        <h2 class="font-serif text-xl">Activer le contrat</h2>
        <p class="text-sm text-app-text/50">
          Importez le PDF du contrat légalisé (signé par les deux parties).
          Le statut passera automatiquement à <b class="text-green-400">Actif</b>.
        </p>

        <div>
          <label class="f-label">PDF signé/légalisé *</label>
          <input class="f-input" type="file" accept=".pdf" @change="onSignedFileChange" />
          <p v-if="signedFile" class="text-xs text-green-400 mt-1">✓ {{ signedFile.name }}</p>
        </div>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showActivate = false">Annuler</button>
          <button
            class="btn btn-gold btn-md"
            :disabled="!signedFile || !!activating"
            @click="submitActivate"
          >
            {{ activating ? 'Activation...' : 'Activer le contrat' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Renewal -->
    <div
      v-if="showRenewModal"
      class="fixed inset-0 z-100 bg-black/70 flex items-center justify-center p-4"
      @click.self="showRenewModal = false"
    >
      <div class="card w-full max-w-md p-6 space-y-4">
        <h2 class="font-serif text-xl">Renouveler le contrat</h2>
        <p class="text-sm text-app-text/50">
          {{ renewSource?.entreprise?.raison_sociale }} — un nouveau contrat en brouillon
          sera créé, lié à celui-ci. Le contrat source n'est jamais modifié.
        </p>

        <!-- Contract title is fully dynamic — the person can choose a
             different title for the renewed document than the source. -->
        <div>
          <label class="f-label">Titre du nouveau contrat</label>
          <input v-model="renewForm.titre_contrat" class="f-input" placeholder="Contrat de Domiciliation" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="f-label">Date de début</label>
            <input v-model="renewForm.date_debut" type="date" class="f-input" />
          </div>
          <div>
            <label class="f-label">Durée (mois)</label>
            <input v-model.number="renewForm.duree_mois" type="number" min="1" class="f-input" />
          </div>
          <div>
            <label class="f-label">Redevance mensuelle (DH)</label>
            <input v-model.number="renewForm.prix_mensuel" type="number" min="0" step="0.01" class="f-input" />
          </div>
          <div>
            <label class="f-label">Total (DH)</label>
            <input v-model.number="renewForm.prix_total" type="number" min="0" step="0.01" class="f-input" />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm cursor-pointer">
          <input v-model="renewForm.carry_articles" type="checkbox" class="rounded" />
          Reprendre les mêmes articles/clauses
        </label>

        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showRenewModal = false">Annuler</button>
          <button class="btn btn-gold btn-md" :disabled="renewing" @click="submitRenew">
            {{ renewing ? 'Création...' : 'Créer le renouvellement' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>