<!-- app/pages/admin/factures.vue -->
<!-- Lists all invoices for the authenticated domiciliataire -->
<!-- API: GET /api/factures -->
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const { error: toastError } = useToast()

// ── State ─────────────────────────────────────────────────
const factures = ref<any[]>([])
const loading  = ref(true)
const search   = ref('')

// ── Helpers ───────────────────────────────────────────────
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

// ── Fetch ─────────────────────────────────────────────────
async function fetchFactures(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/factures`,
      { headers: authHeaders() }
    )
    factures.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement factures')
  } finally {
    loading.value = false
  }
}

// ── Search ────────────────────────────────────────────────
const filtered = computed(() => {
  if (!search.value.trim()) return factures.value
  const q = search.value.toLowerCase()
  return factures.value.filter(f =>
    f.numero_facture?.toLowerCase().includes(q) ||
    f.entreprise?.raison_sociale?.toLowerCase().includes(q) ||
    f.statut?.toLowerCase().includes(q)
  )
})

// ── Helpers ───────────────────────────────────────────────
function formatDate(d: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-MA', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  })
}

// Statut badge classes
const statutClass: Record<string, string> = {
  paid:    'text-green-400 bg-green-400/10',
  unpaid:  'text-yellow-400 bg-yellow-400/10',
  overdue: 'text-red-400 bg-red-400/10',
}

function getStatutClass(statut: string): string {
  return statutClass[statut] ?? 'text-app-text/50 bg-white/5'
}

function getStatutLabel(statut: string): string {
  const labels: Record<string, string> = {
    paid:    'Payée',
    unpaid:  'En attente',
    overdue: 'En retard',
  }
  return labels[statut] ?? statut
}

// ── Stats ─────────────────────────────────────────────────
const totalAmount = computed(() =>
  factures.value.reduce((sum, f) => sum + Number(f.montant_total ?? 0), 0)
)

const paidCount = computed(() =>
  factures.value.filter(f => f.statut === 'paid').length
)

const unpaidCount = computed(() =>
  factures.value.filter(f => f.statut !== 'paid').length
)

onMounted(fetchFactures)
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Factures <em class="text-gold italic">&amp; Paiements</em>
        </h1>
        <p class="text-app-text/50 text-sm mt-1">
          {{ factures.length }} facture(s) au total
        </p>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-gold">
          {{ totalAmount.toLocaleString('fr-MA') }} DH
        </p>
        <p class="text-xs text-app-text/40 mt-1">Total facturé</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-green-400">{{ paidCount }}</p>
        <p class="text-xs text-app-text/40 mt-1">Factures payées</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-yellow-400">{{ unpaidCount }}</p>
        <p class="text-xs text-app-text/40 mt-1">En attente</p>
      </div>
    </div>

    <!-- Search -->
    <div class="card p-3">
      <input
        v-model="search"
        class="f-input"
        placeholder="Rechercher par N° facture, entreprise, statut..."
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">
      Chargement...
    </div>

    <!-- Table -->
    <div
      v-else-if="filtered.length"
      class="overflow-hidden rounded-xl border border-white/10"
    >
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-white/5 text-xs font-semibold text-app-text/50 uppercase tracking-wider">
            <th class="p-4">N° Facture</th>
            <th class="p-4">Entreprise</th>
            <th class="p-4">Contrat</th>
            <th class="p-4">Date</th>
            <th class="p-4 text-right">Montant</th>
            <th class="p-4 text-center">Statut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr
            v-for="f in filtered"
            :key="f.id"
            class="hover:bg-white/3 transition"
          >
            <td class="p-4 text-sm font-mono font-semibold text-white">
              {{ f.numero_facture ?? '—' }}
            </td>
            <td class="p-4 text-sm text-app-text/80">
              {{ f.entreprise?.raison_sociale ?? '—' }}
            </td>
            <td class="p-4 text-sm text-app-text/50">
              {{ f.contrat?.reference ?? `#${f.contrat_id}` ?? '—' }}
            </td>
            <td class="p-4 text-sm text-app-text/60">
              {{ formatDate(f.date_facture) }}
            </td>
            <td class="p-4 text-sm font-semibold text-green-400 text-right">
              {{ Number(f.montant_total ?? 0).toLocaleString('fr-MA') }} DH
            </td>
            <td class="p-4 text-center">
              <span
                class="text-xs px-2 py-0.5 rounded-full font-semibold"
                :class="getStatutClass(f.statut)"
              >
                {{ getStatutLabel(f.statut) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">🧾</p>
      <p>Aucune facture trouvée.</p>
    </div>

  </div>
</template>