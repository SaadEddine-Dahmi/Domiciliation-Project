<!-- pages/admin/contrat.vue (liste)
  Contracts list with advanced search, a compact filter dropdown
  (status + city + price range + single-field sort with direction),
  formatted dates, and pagination.

  SORT REDESIGN (fixes both reported bugs):
    Previously sort was a multi-select chip list where keys like
    "recent" (date_debut desc) and "signature_old" (date_signature asc)
    could be active together — but those represent CONTRADICTORY
    directions (one wants newest-first, the other wants oldest-first),
    which produces a meaningless combined order. It also caused the
    alphabetical option to never actually apply: "recent" stayed first
    in priority order by default, so it decided the order for every
    pair of contracts with different dates (nearly all of them),
    leaving "alpha_asc" to only break ties that almost never occurred.

    Fix: sort is now ONE field (sortField) + ONE direction (sortDir).
    Only one field can be active, only one direction can be active —
    contradictions are structurally impossible, and the chosen field
    is always guaranteed to be the actual primary sort.

  FILTER UI REDESIGN:
    The filter panel is no longer a full-width block pushing the page
    content down. It's a small floating card anchored under the
    "Filtres" button (position: absolute inside a position: relative
    wrapper), closes on outside click, and stays compact regardless of
    how many filters are added.
-->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { success, error: toastError } = useToast()
const router = useRouter()
const route  = useRoute()

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

// ── State ─────────────────────────────────────────────────
const items          = ref<any[]>([])
const loading        = ref(true)
const q              = ref('')
const activating     = ref<number | null>(null)
const showActivate   = ref(false)
const activateId     = ref<number | null>(null)
const signedFile     = ref<File | null>(null)
const downloadingId  = ref<number | null>(null)

// ── Preview state ─────────────────────────────────────────
const previewContrat  = ref<any>(null)
const showPreview     = ref(false)
const previewLoading  = ref(false)
const previewBlobUrl  = ref<string | null>(null)
const previewingId    = ref<number | null>(null)

// ── Filter dropdown state ────────────────────────────────────
const showFilters  = ref(false)
const filterBtnRef = ref<HTMLElement | null>(null)
const filterPanelRef = ref<HTMLElement | null>(null)

function onClickOutside(e: MouseEvent): void {
  if (!showFilters.value) return
  const target = e.target as Node
  if (filterPanelRef.value?.contains(target)) return
  if (filterBtnRef.value?.contains(target)) return
  showFilters.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside)
})

type StatutFilter = 'all' | 'draft' | 'active' | 'expired' | 'terminated'
type SortField = 'date_debut' | 'date_signature' | 'entreprise' | 'prix_total' | 'date_fin'
type SortDir   = 'asc' | 'desc'

const statutFilter = ref<StatutFilter>('all')
const villeFilter  = ref<string>('')
const prixMin      = ref<string>('')
const prixMax      = ref<string>('')

/** Exactly one active field, exactly one active direction — no contradictions possible */
const sortField = ref<SortField>('date_debut')
const sortDir   = ref<SortDir>('desc')

const statutFilterOptions: { value: StatutFilter; label: string }[] = [
  { value: 'all',        label: 'Tous' },
  { value: 'draft',      label: 'Brouillon' },
  { value: 'active',     label: 'Activé' },
  { value: 'expired',    label: 'Expiré' },
  { value: 'terminated', label: 'Résilié' },
]

/** Each field defines its own direction labels — "récent/ancien" reads better than "asc/desc" for dates */
const sortFieldOptions: { value: SortField; label: string; ascLabel: string; descLabel: string }[] = [
  { value: 'date_debut',     label: 'Date de début',     ascLabel: 'Plus anciens',        descLabel: 'Plus récents' },
  { value: 'date_fin',       label: "Date d'expiration", ascLabel: 'Plus proche',          descLabel: 'Plus lointaine' },
  { value: 'date_signature', label: 'Date de signature', ascLabel: 'Signature ancienne',   descLabel: 'Signature récente' },
  { value: 'entreprise',     label: 'Entreprise',        ascLabel: 'A → Z',                descLabel: 'Z → A' },
  { value: 'prix_total',     label: 'Montant',           ascLabel: 'Prix croissant',       descLabel: 'Prix décroissant' },
]

const currentFieldOption = computed(() =>
  sortFieldOptions.find(o => o.value === sortField.value) ?? sortFieldOptions[0]
)

/** List of distinct cities present in the data — feeds the "Ville" filter dropdown */
const villeOptions = computed(() => {
  const set = new Set<string>()
  items.value.forEach(c => {
    if (c.entreprise?.ville) set.add(c.entreprise.ville)
  })
  return Array.from(set).sort((a, b) => a.localeCompare(b, 'fr'))
})

const activeFilterCount = computed(() => {
  let n = 0
  if (statutFilter.value !== 'all') n++
  if (villeFilter.value) n++
  if (prixMin.value) n++
  if (prixMax.value) n++
  if (sortField.value !== 'date_debut' || sortDir.value !== 'desc') n++
  return n
})

function resetFilters(): void {
  statutFilter.value = 'all'
  villeFilter.value  = ''
  prixMin.value      = ''
  prixMax.value      = ''
  sortField.value    = 'date_debut'
  sortDir.value      = 'desc'
}

// ── Pagination ────────────────────────────────────────────
const currentPage = ref(1)
const perPage     = 10

// ── Chargement ────────────────────────────────────────────
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

// Pre-fill the status filter from a `?statut=` query param (dashboard stat card links)
onMounted(() => {
  const statutParam = route.query.statut
  if (typeof statutParam === 'string' && ['draft', 'active', 'expired', 'terminated'].includes(statutParam)) {
    statutFilter.value = statutParam as StatutFilter
  }
  load()
})

// Reset to page 1 whenever search or filters change
watch(
  [q, statutFilter, villeFilter, prixMin, prixMax, sortField, sortDir],
  () => { currentPage.value = 1 }
)

// ── Advanced search ───────────────────────────────────────
function buildSearchHaystack(c: any): string {
  const rep = c.entreprise?.representant

  return [
    c.titre_contrat,
    c.instruction_no,
    c.entreprise?.raison_sociale,
    c.entreprise?.forme_juridique,
    c.entreprise?.ville,
    c.entreprise?.pays,
    c.entreprise?.adresse,
    rep?.nom,
    rep?.prenom,
    rep?.cin,
    rep?.telephone,
    rep?.email,
    rep?.adresse,
    rep?.nationalite,
    statutLabel[c.statut] ?? c.statut,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
}

const searched = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return items.value
  return items.value.filter(c => buildSearchHaystack(c).includes(term))
})

// ── Status / ville / prix filters ─────────────────────────
const advancedFiltered = computed(() => {
  let list = searched.value

  if (statutFilter.value !== 'all') {
    list = list.filter(c => c.statut === statutFilter.value)
  }
  if (villeFilter.value) {
    list = list.filter(c => c.entreprise?.ville === villeFilter.value)
  }
  if (prixMin.value) {
    const min = Number(prixMin.value)
    list = list.filter(c => Number(c.prix_total ?? 0) >= min)
  }
  if (prixMax.value) {
    const max = Number(prixMax.value)
    list = list.filter(c => Number(c.prix_total ?? 0) <= max)
  }

  return list
})

// ── Sorting — single field + single direction ─────────────
function safeDate(value: string | null | undefined): number {
  if (!value) return 0
  const t = new Date(value).getTime()
  return isNaN(t) ? 0 : t
}

function entrepriseName(c: any): string {
  return (c.entreprise?.raison_sociale ?? c.titre_contrat ?? '').toLowerCase()
}

function compareByField(field: SortField, a: any, b: any): number {
  switch (field) {
    case 'date_debut':     return safeDate(a.date_debut) - safeDate(b.date_debut)
    case 'date_fin':       return safeDate(a.date_fin) - safeDate(b.date_fin)
    case 'date_signature': return safeDate(a.date_signature) - safeDate(b.date_signature)
    case 'entreprise':     return entrepriseName(a).localeCompare(entrepriseName(b), 'fr')
    case 'prix_total':     return Number(a.prix_total ?? 0) - Number(b.prix_total ?? 0)
    default:                return 0
  }
}

const sorted = computed(() => {
  const arr = [...advancedFiltered.value]
  const dirMultiplier = sortDir.value === 'asc' ? 1 : -1

  return arr.sort((a, b) => compareByField(sortField.value, a, b) * dirMultiplier)
})

const filtered = sorted // final list after search + filters + sort

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filtered.value.length / perPage))
)

const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const visiblePages = computed(() => {
  const total = totalPages.value
  const curr  = currentPage.value
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)

  let start = Math.max(1, curr - 2)
  let end   = Math.min(total, start + 4)
  start     = Math.max(1, end - 4)

  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

function goToPage(p: number): void {
  currentPage.value = Math.min(Math.max(1, p), totalPages.value)
}

// ── Date formatting ───────────────────────────────────────
function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const d = new Date(value)
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// ── Shared PDF blob fetch ────────────────────────────────────
async function fetchPdfBlob(contratId: number): Promise<Blob> {
  const url  = `${getApiBase()}/api/contrats/${contratId}/pdf/stream`
  const resp = await fetch(url, { headers: authHeaders() })

  if (resp.status === 401) {
    throw new Error('Session expirée — veuillez vous reconnecter.')
  }
  if (!resp.ok) {
    throw new Error(`HTTP ${resp.status}`)
  }

  const contentType = resp.headers.get('content-type') ?? ''
  if (!contentType.includes('application/pdf')) {
    const text = await resp.text()
    console.error('[fetchPdfBlob] unexpected response body:', text.slice(0, 300))
    throw new Error('Réponse inattendue du serveur (pas un PDF)')
  }

  return await resp.blob()
}

// ── Télécharger le PDF ──────────────────────────────────────
async function downloadPdf(contrat: any): Promise<void> {
  downloadingId.value = contrat.id
  try {
    const blob    = await fetchPdfBlob(contrat.id)
    const blobUrl = URL.createObjectURL(blob)
    const a       = document.createElement('a')
    a.href        = blobUrl
    a.download    = `contrat_${contrat.id}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
  } catch (e: any) {
    console.error('[downloadPdf] failed:', e)
    toastError?.(e?.data?.message ?? e?.message ?? 'Erreur lors du téléchargement du PDF')
  } finally {
    downloadingId.value = null
  }
}

// ── Aperçu du PDF ────────────────────────────────────────────
async function previewPdf(contrat: any): Promise<void> {
  previewingId.value   = contrat.id
  previewLoading.value = true
  previewContrat.value = contrat
  showPreview.value    = true
  if (import.meta.client) document.body.style.overflow = 'hidden'

  try {
    const blob = await fetchPdfBlob(contrat.id)
    if (previewBlobUrl.value) URL.revokeObjectURL(previewBlobUrl.value)
    previewBlobUrl.value = URL.createObjectURL(blob)
  } catch (e: any) {
    console.error('[previewPdf] failed:', e)
    toastError?.(e?.data?.message ?? e?.message ?? "Erreur lors de l'aperçu du PDF")
    closePreview()
  } finally {
    previewLoading.value = false
    previewingId.value   = null
  }
}

function closePreview(): void {
  showPreview.value    = false
  previewContrat.value = null
  if (previewBlobUrl.value) {
    URL.revokeObjectURL(previewBlobUrl.value)
    previewBlobUrl.value = null
  }
  if (import.meta.client) document.body.style.overflow = ''
}

function downloadFromPreview(): void {
  if (!previewBlobUrl.value || !previewContrat.value) return
  const a    = document.createElement('a')
  a.href     = previewBlobUrl.value
  a.download = `contrat_${previewContrat.value.id}.pdf`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

// ── Activer (upload PDF signé) ─────────────────────────────
function openActivate(id: number): void {
  activateId.value   = id
  signedFile.value   = null
  showActivate.value = true
  if (import.meta.client) document.body.style.overflow = 'hidden'
}

function closeActivate(): void {
  showActivate.value = false
  if (import.meta.client) document.body.style.overflow = ''
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
    closeActivate()
    await load()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur activation')
  } finally {
    activating.value = null
  }
}

// ── Résilier ──────────────────────────────────────────────
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

// ── Couleurs / labels statuts ──────────────────────────────
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

onBeforeUnmount(() => {
  if (import.meta.client) document.body.style.overflow = ''
  if (previewBlobUrl.value) URL.revokeObjectURL(previewBlobUrl.value)
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Contrats <em class="text-gold italic">de domiciliation</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ filtered.length }} contrat(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="router.push('/admin/contrat?new=1')">
        + Nouveau contrat
      </button>
    </div>

    <!-- Recherche + bouton filtre (dropdown card, not full-width block) -->
    <div class="flex gap-2">
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
             width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round"
             style="color:var(--app-text-faint)">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input
          v-model="q"
          class="f-input pl-9"
          placeholder="Rechercher par entreprise, représentant, CIN, adresse, téléphone, email..."
        />
      </div>

      <div class="relative shrink-0">
        <button
          ref="filterBtnRef"
          type="button"
          class="btn btn-outline btn-md relative"
          @click="showFilters = !showFilters"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
          </svg>
          Filtres
          <span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
        </button>

        <!-- Floating dropdown card — anchored under the button, not a full-width block -->
        <Transition name="filter-pop">
          <div v-if="showFilters" ref="filterPanelRef" class="filter-dropdown">

            <div class="filter-section">
              <label class="filter-section__label">Statut</label>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="opt in statutFilterOptions" :key="opt.value"
                  type="button"
                  class="filter-chip"
                  :class="{ 'filter-chip--active': statutFilter === opt.value }"
                  @click="statutFilter = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <div v-if="villeOptions.length" class="filter-section">
              <label class="filter-section__label">Ville</label>
              <select v-model="villeFilter" class="f-input filter-select">
                <option value="">Toutes les villes</option>
                <option v-for="v in villeOptions" :key="v" :value="v">{{ v }}</option>
              </select>
            </div>

            <div class="filter-section">
              <label class="filter-section__label">Montant (DH)</label>
              <div class="flex gap-2">
                <input v-model="prixMin" type="number" min="0" class="f-input filter-select" placeholder="Min" />
                <input v-model="prixMax" type="number" min="0" class="f-input filter-select" placeholder="Max" />
              </div>
            </div>

            <div class="filter-section">
              <label class="filter-section__label">Trier par</label>
              <div class="flex flex-wrap gap-1.5 mb-2">
                <button
                  v-for="opt in sortFieldOptions" :key="opt.value"
                  type="button"
                  class="filter-chip"
                  :class="{ 'filter-chip--active': sortField === opt.value }"
                  @click="sortField = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>

              <!-- Direction — a single binary toggle, tied to whichever field is selected above -->
              <div class="direction-toggle">
                <button
                  type="button"
                  class="direction-toggle__btn"
                  :class="{ 'direction-toggle__btn--active': sortDir === 'asc' }"
                  @click="sortDir = 'asc'"
                >
                  {{ currentFieldOption.ascLabel }}
                </button>
                <button
                  type="button"
                  class="direction-toggle__btn"
                  :class="{ 'direction-toggle__btn--active': sortDir === 'desc' }"
                  @click="sortDir = 'desc'"
                >
                  {{ currentFieldOption.descLabel }}
                </button>
              </div>
            </div>

            <div class="flex justify-between items-center pt-1">
              <button type="button" class="text-xs underline" style="color:var(--app-text-faint)" @click="resetFilters">
                Réinitialiser
              </button>
              <button type="button" class="btn btn-gold btn-sm" @click="showFilters = false">
                Appliquer
              </button>
            </div>

          </div>
        </Transition>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card p-10 text-center text-app-text/40">Chargement...</div>

    <!-- ══════ Data table ═══════════════════════════════════════════════ -->
    <div v-else-if="filtered.length" class="table-wrap">
      <div class="contrats-table">

        <!-- Header row -->
        <div class="contrats-table__row contrats-table__row--head">
          <div class="contrats-table__cell contrats-table__cell--name">Nom du contrat</div>
          <div class="contrats-table__cell contrats-table__cell--period">Période de validité</div>
          <div class="contrats-table__cell contrats-table__cell--status">Statut</div>
          <div class="contrats-table__cell contrats-table__cell--actions">Actions</div>
        </div>

        <!-- Data rows -->
        <div
          v-for="c in paginated" :key="c.id"
          class="contrats-table__row"
        >
          <!-- Nom du contrat -->
          <div class="contrats-table__cell contrats-table__cell--name">
            <p class="font-semibold truncate">
              {{ c.titre_contrat || c.entreprise?.raison_sociale || `Contrat #${c.id}` }}
            </p>
            <p v-if="c.entreprise?.raison_sociale && c.titre_contrat" class="text-xs mt-0.5 truncate" style="color:var(--app-text-faint)">
              {{ c.entreprise.raison_sociale }}
            </p>
          </div>

          <!-- Période de validité — formatted, not raw ISO -->
          <div class="contrats-table__cell contrats-table__cell--period">
            <span class="tabular-nums">{{ formatDate(c.date_debut) }}</span>
            <span style="color:var(--app-text-faint)"> – </span>
            <span class="tabular-nums">{{ formatDate(c.date_fin) }}</span>
          </div>

          <!-- Statut -->
          <div class="contrats-table__cell contrats-table__cell--status">
            <span
              class="text-xs px-2.5 py-1 rounded-full font-semibold inline-block"
              :class="statutColor[c.statut] ?? 'text-app-text/40 bg-white/5'"
            >
              {{ statutLabel[c.statut] ?? c.statut }}
            </span>
          </div>

          <!-- Actions -->
          <div class="contrats-table__cell contrats-table__cell--actions">
            <div class="flex gap-1.5 flex-wrap justify-end">
              <button
                class="btn btn-outline btn-sm"
                :disabled="previewingId === c.id"
                @click="previewPdf(c)"
                title="Aperçu"
              >
                {{ previewingId === c.id ? '...' : '👁 Aperçu' }}
              </button>

              <button
                v-if="(c.statut !== 'expired' && c.statut !== 'terminated') || c.pdf_path"
                class="btn btn-outline btn-sm"
                :disabled="downloadingId === c.id"
                @click="downloadPdf(c)"
                title="Télécharger le PDF"
              >
                {{ downloadingId === c.id ? '...' : '⬇ PDF' }}
              </button>

              <button
                v-if="c.statut === 'draft'"
                class="btn btn-gold btn-sm"
                @click="openActivate(c.id)"
                title="Activer avec le PDF signé"
              >
                ✓ Activer
              </button>

              <button
                v-if="c.statut === 'draft'"
                class="btn btn-outline btn-sm"
                @click="router.push(`/admin/contrat?id=${c.id}`)"
                title="Éditer"
              >
                ✎ Éditer
              </button>

              <button
                v-if="c.statut === 'active'"
                class="btn btn-danger btn-sm"
                @click="terminate(c.id)"
                title="Résilier"
              >
                Résilier
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!loading && filtered.length && totalPages > 1" class="flex items-center justify-center gap-1.5">
      <button
        class="pagination-btn"
        :disabled="currentPage === 1"
        @click="goToPage(currentPage - 1)"
      >‹</button>

      <button
        v-if="visiblePages[0] > 1"
        class="pagination-btn"
        @click="goToPage(1)"
      >1</button>
      <span v-if="visiblePages[0] > 2" class="pagination-ellipsis">…</span>

      <button
        v-for="p in visiblePages" :key="p"
        class="pagination-btn"
        :class="{ 'pagination-btn--active': p === currentPage }"
        @click="goToPage(p)"
      >{{ p }}</button>

      <span v-if="visiblePages[visiblePages.length - 1] < totalPages - 1" class="pagination-ellipsis">…</span>
      <button
        v-if="visiblePages[visiblePages.length - 1] < totalPages"
        class="pagination-btn"
        @click="goToPage(totalPages)"
      >{{ totalPages }}</button>

      <button
        class="pagination-btn"
        :disabled="currentPage === totalPages"
        @click="goToPage(currentPage + 1)"
      >›</button>
    </div>

    <!-- Vide -->
    <div v-if="!loading && !filtered.length" class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>{{ q || activeFilterCount > 0 ? 'Aucun contrat ne correspond à votre recherche.' : 'Aucun contrat trouvé.' }}</p>
      <button v-if="!q && activeFilterCount === 0" class="btn btn-gold btn-md mt-4" @click="router.push('/admin/contrat?new=1')">
        Créer le premier contrat
      </button>
      <button v-else class="btn btn-outline btn-md mt-4" @click="q = ''; resetFilters()">
        Réinitialiser la recherche et les filtres
      </button>
    </div>

    <!-- ══════ Modal Activation (upload PDF signé) ══════════════════════ -->
    <Teleport to="body">
      <div v-if="showActivate" class="modal-overlay" @click.self="closeActivate">
        <div class="card modal-panel w-full max-w-md p-6 space-y-4">
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
            <button class="btn btn-outline btn-md" @click="closeActivate">Annuler</button>
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
    </Teleport>

    <!-- ══════ Modal Aperçu PDF (fullscreen preview) ═════════════════════ -->
    <Teleport to="body">
      <div v-if="showPreview" class="preview-overlay">
        <div class="preview-topbar">
          <div class="preview-topbar__info">
            <span class="font-medium">
              {{ previewContrat?.titre_contrat || previewContrat?.entreprise?.raison_sociale || `Contrat #${previewContrat?.id}` }}
            </span>
            <span
              v-if="previewContrat"
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="statutColor[previewContrat.statut] ?? 'text-white/40 bg-white/5'"
            >
              {{ statutLabel[previewContrat.statut] ?? previewContrat.statut }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button
              class="btn btn-gold btn-sm"
              :disabled="previewLoading || !previewBlobUrl"
              @click="downloadFromPreview"
            >
              ⬇ Télécharger
            </button>
            <button class="preview-close-btn" @click="closePreview" aria-label="Fermer l'aperçu">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="preview-body">
          <div v-if="previewLoading" class="preview-loading">
            <div class="preview-spinner" />
            <p class="text-sm mt-3">Chargement du PDF...</p>
          </div>
          <iframe
            v-else-if="previewBlobUrl"
            :src="previewBlobUrl"
            class="preview-iframe"
            title="Aperçu du contrat PDF"
          />
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
/* ── Filter dropdown card ─────────────────────────────────────────────── */
.filter-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--gold);
  color: #111;
  font-size: 0.62rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}

.filter-dropdown {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 200;
  width: 300px;
  max-width: 90vw;
  max-height: 75vh;
  overflow-y: auto;
  background: var(--app-surface);
  border: 1px solid var(--app-border-2);
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.filter-section {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.filter-section__label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--app-text-faint);
}

.filter-select {
  font-size: 0.82rem;
  padding: 0.45rem 0.6rem;
}

.filter-chip {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 0.26rem 0.55rem;
  border-radius: 999px;
  border: 1px solid var(--app-border);
  color: var(--app-text-muted);
  background: var(--app-surface-2);
  transition: all 0.15s ease;
  line-height: 1.2;
}
.filter-chip:hover {
  border-color: rgba(200, 169, 110, 0.35);
  color: var(--app-text);
}
.filter-chip--active {
  background: linear-gradient(135deg, var(--gold), var(--gold-2));
  color: #111;
  border-color: transparent;
}

.direction-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.4rem;
}
.direction-toggle__btn {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.4rem 0.5rem;
  border-radius: 10px;
  border: 1px solid var(--app-border);
  background: var(--app-surface-2);
  color: var(--app-text-muted);
  transition: all 0.15s ease;
  text-align: center;
}
.direction-toggle__btn:hover {
  border-color: rgba(200, 169, 110, 0.35);
  color: var(--app-text);
}
.direction-toggle__btn--active {
  background: linear-gradient(135deg, var(--gold), var(--gold-2));
  color: #111;
  border-color: transparent;
}

.filter-pop-enter-active,
.filter-pop-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.filter-pop-enter-from,
.filter-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ── Data table ────────────────────────────────────────────────────────── */
.contrats-table {
  background: var(--app-surface);
  border: 1px solid var(--app-border-2);
  border-radius: 16px;
  overflow: hidden;
  min-width: 720px;
}

.contrats-table__row {
  display: grid;
  grid-template-columns: 2fr 1.4fr 0.9fr 2fr;
  align-items: center;
  gap: 1rem;
  padding: 0.9rem 1.25rem;
  border-bottom: 1px solid var(--app-border-2);
}
.contrats-table__row:last-child {
  border-bottom: none;
}
.contrats-table__row:not(.contrats-table__row--head):hover {
  background: var(--app-surface-2);
}

.contrats-table__row--head {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--app-text-faint);
  font-weight: 700;
  background: var(--app-surface-2);
}

.contrats-table__cell {
  min-width: 0;
}
.contrats-table__cell--period {
  font-size: 0.85rem;
  color: var(--app-text-muted);
  white-space: nowrap;
}
.contrats-table__cell--actions {
  display: flex;
  justify-content: flex-end;
}

/* ── Pagination ────────────────────────────────────────────────────────── */
.pagination-btn {
  min-width: 34px;
  height: 34px;
  padding: 0 0.6rem;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--app-text-muted);
  background: var(--app-surface);
  border: 1px solid var(--app-border);
  transition: all 0.15s ease;
}
.pagination-btn:hover:not(:disabled) {
  border-color: rgba(200, 169, 110, 0.4);
  color: var(--app-text);
}
.pagination-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
.pagination-btn--active {
  background: linear-gradient(135deg, var(--gold), var(--gold-2));
  color: #111;
  border-color: transparent;
}
.pagination-ellipsis {
  color: var(--app-text-faint);
  padding: 0 0.25rem;
}

/* ── Modals ────────────────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  overflow-y: auto;
}
.modal-panel {
  max-height: 90vh;
  overflow-y: auto;
  margin: auto;
}

.preview-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  flex-direction: column;
}
.preview-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.25rem;
  background: rgba(0, 0, 0, 0.6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.preview-topbar__info {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
}
.preview-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  transition: background 0.15s ease;
}
.preview-close-btn:hover {
  background: rgba(255, 255, 255, 0.18);
}
.preview-body {
  flex: 1;
  position: relative;
}
.preview-iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
  background: #fff;
}
.preview-loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.preview-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(255, 255, 255, 0.25);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>