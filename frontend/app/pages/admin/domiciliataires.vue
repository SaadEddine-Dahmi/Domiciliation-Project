<!-- app/pages/admin/domiciliataires.vue -->
<!-- Super admin only — manage domiciliataire accounts -->
<!-- Tabs: Active accounts | Pending approval -->
<script setup lang="ts">
import { activationService } from '~/services/activation.service'
import type { PendingUser } from '~/types/user'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()
const { success, error: toastError } = useToast()

// Redirect if not admin
onMounted(async () => {
  if (!auth.isAdmin) {
    await navigateTo('/admin/dashboard')
    return
  }
  await Promise.all([loadActive(), loadPending()])
})

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

// ── Tabs ─────────────────────────────────────────────────
const activeTab = ref<'active' | 'pending'>('active')

// ── Active domiciliataires ────────────────────────────────
const domiciliataires = ref<any[]>([])
const loadingActive   = ref(true)
const search          = ref('')

async function loadActive(): Promise<void> {
  loadingActive.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/admin/domiciliataires`,
      { headers: authHeaders() }
    )
    domiciliataires.value = res.data ?? []
  } catch (e: any) {
    console.error('Erreur chargement domiciliataires', e)
  } finally {
    loadingActive.value = false
  }
}

const filteredActive = computed(() => {
  if (!search.value.trim()) return domiciliataires.value
  const q = search.value.toLowerCase()
  return domiciliataires.value.filter(d =>
    `${d.nom} ${d.prenom}`.toLowerCase().includes(q) ||
    d.email?.toLowerCase().includes(q)
  )
})

// ── Pending domiciliataires ───────────────────────────────
const pendingUsers   = ref<PendingUser[]>([])
const loadingPending = ref(true)

async function loadPending(): Promise<void> {
  loadingPending.value = true
  try {
    const res = await activationService.getPending()
    pendingUsers.value = res.data ?? []
  } catch (e: any) {
    console.error('Erreur chargement comptes en attente', e)
  } finally {
    loadingPending.value = false
  }
}

// ── Approve modal ─────────────────────────────────────────
const showApproveModal  = ref(false)
const selectedUser      = ref<PendingUser | null>(null)
const activationDate    = ref('')
const savingApproval    = ref(false)

function openApprove(user: PendingUser): void {
  selectedUser.value   = user
  // Default: today
  activationDate.value = new Date().toISOString().split('T')[0]
  showApproveModal.value = true
}

async function submitApprove(): Promise<void> {
  if (!selectedUser.value || !activationDate.value) return
  savingApproval.value = true
  try {
    await activationService.approve(selectedUser.value.id, activationDate.value)
    success(`Compte de ${selectedUser.value.nom} approuvé.`)
    showApproveModal.value = false
    // Remove from pending list
    pendingUsers.value = pendingUsers.value.filter(u => u.id !== selectedUser.value!.id)
    // Reload active list
    await loadActive()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors de l\'approbation')
  } finally {
    savingApproval.value = false
  }
}

// ── Reject modal ──────────────────────────────────────────
const showRejectModal = ref(false)
const rejectReason    = ref('')
const savingRejection = ref(false)

function openReject(user: PendingUser): void {
  selectedUser.value  = user
  rejectReason.value  = ''
  showRejectModal.value = true
}

async function submitReject(): Promise<void> {
  if (!selectedUser.value || !rejectReason.value.trim()) return
  savingRejection.value = true
  try {
    await activationService.reject(selectedUser.value.id, rejectReason.value)
    success(`Compte de ${selectedUser.value.nom} rejeté.`)
    showRejectModal.value = false
    pendingUsers.value = pendingUsers.value.filter(u => u.id !== selectedUser.value!.id)
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors du rejet')
  } finally {
    savingRejection.value = false
  }
}
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Domiciliataires <em class="text-gold italic">enregistrés</em>
        </h1>
        <p class="text-app-text/50 text-sm mt-1">
          {{ domiciliataires.length }} actif(s) ·
          <span
            class="font-semibold"
            :class="pendingUsers.length ? 'text-yellow-400' : 'text-app-text/40'"
          >
            {{ pendingUsers.length }} en attente
          </span>
        </p>
      </div>
      <span
        class="text-xs px-3 py-1 rounded-full font-bold"
        style="background:rgba(239,68,68,0.15);color:#ef4444"
      >Super Admin</span>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 border-b border-white/10 pb-0">
      <button
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === 'active'
          ? 'border-gold text-gold'
          : 'border-transparent text-app-text/40 hover:text-white'"
        @click="activeTab = 'active'"
      >
        Actifs ({{ domiciliataires.length }})
      </button>
      <button
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors relative"
        :class="activeTab === 'pending'
          ? 'border-yellow-400 text-yellow-400'
          : 'border-transparent text-app-text/40 hover:text-white'"
        @click="activeTab = 'pending'"
      >
        En attente ({{ pendingUsers.length }})
        <!-- Badge if pending users exist -->
        <span
          v-if="pendingUsers.length && activeTab !== 'pending'"
          class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-yellow-400"
        />
      </button>
    </div>

    <!-- ── Tab: Active domiciliataires ────────────────── -->
    <div v-if="activeTab === 'active'">
      <div class="card p-3 mb-4">
        <input v-model="search" class="f-input" placeholder="Rechercher par nom, email..." />
      </div>

      <div v-if="loadingActive" class="text-center py-12 text-app-text/40">Chargement...</div>

      <div v-else-if="filteredActive.length" class="space-y-3">
        <div
          v-for="d in filteredActive" :key="d.id"
          class="card p-4"
        >
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                style="background:rgba(200,169,110,0.15);color:#c8a96e"
              >
                {{ (d.nom ?? 'D').slice(0, 2).toUpperCase() }}
              </div>
              <div>
                <p class="font-semibold">{{ d.nom }} {{ d.prenom }}</p>
                <p class="text-xs text-app-text/50">{{ d.email }}</p>
                <p v-if="d.telephone" class="text-xs text-app-text/40">{{ d.telephone }}</p>
              </div>
            </div>
            <div class="flex gap-4 text-center">
              <div>
                <p class="text-xl font-serif text-gold">{{ d.entreprises_count ?? 0 }}</p>
                <p class="text-xs text-app-text/40">Clients</p>
              </div>
              <div>
                <p class="text-xl font-serif text-gold">{{ d.contrats_count ?? 0 }}</p>
                <p class="text-xs text-app-text/40">Contrats</p>
              </div>
            </div>
          </div>
          <div v-if="d.entreprises?.length" class="mt-3 pt-3 border-t border-white/5">
            <p class="text-xs text-app-text/40 mb-2">Entreprises clientes :</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="e in d.entreprises" :key="e.id"
                class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-app-text/60"
              >{{ e.raison_sociale }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card p-10 text-center text-app-text/40">
        <p class="text-4xl mb-3">👤</p>
        <p>Aucun domiciliataire actif trouvé.</p>
      </div>
    </div>

    <!-- ── Tab: Pending approval ───────────────────────── -->
    <div v-if="activeTab === 'pending'">
      <div v-if="loadingPending" class="text-center py-12 text-app-text/40">Chargement...</div>

      <div v-else-if="pendingUsers.length" class="space-y-3">
        <div
          v-for="u in pendingUsers" :key="u.id"
          class="card p-4 border border-yellow-400/20"
        >
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm"
                style="background:rgba(250,204,21,0.15);color:#facc15"
              >
                {{ (u.nom ?? 'D').slice(0, 2).toUpperCase() }}
              </div>
              <div>
                <p class="font-semibold">{{ u.nom }} {{ u.prenom }}</p>
                <p class="text-xs text-app-text/50">{{ u.email }}</p>
                <p v-if="u.telephone" class="text-xs text-app-text/40">{{ u.telephone }}</p>
                <p class="text-xs text-yellow-400/70 mt-0.5">
                  Demande reçue le {{ new Date(u.created_at ?? '').toLocaleDateString('fr-MA') }}
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <button class="btn btn-danger btn-sm" @click="openReject(u)">
                ✕ Rejeter
              </button>
              <button class="btn btn-gold btn-sm" @click="openApprove(u)">
                ✓ Approuver
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="card p-10 text-center text-app-text/40">
        <p class="text-4xl mb-3">✅</p>
        <p>Aucun compte en attente de validation.</p>
      </div>
    </div>

    <!-- ════ Modal Approuver ══════════════════════════════ -->
    <div
      v-if="showApproveModal"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showApproveModal = false"
    >
      <div class="card w-full max-w-sm p-6 space-y-5">
        <h2 class="font-serif text-xl">Approuver le compte</h2>
        <p class="text-sm text-app-text/60">
          Compte de <strong class="text-white">{{ selectedUser?.nom }} {{ selectedUser?.prenom }}</strong>
        </p>
        <div>
          <label class="f-label">Date d'activation *</label>
          <input
            v-model="activationDate"
            class="f-input"
            type="date"
            :min="new Date().toISOString().split('T')[0]"
            required
          />
          <p class="text-xs text-app-text/40 mt-1">
            L'utilisateur pourra se connecter à partir de cette date.
          </p>
        </div>
        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showApproveModal = false">Annuler</button>
          <button
            class="btn btn-gold btn-md"
            :disabled="savingApproval || !activationDate"
            @click="submitApprove"
          >
            {{ savingApproval ? 'Enregistrement...' : '✓ Confirmer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ════ Modal Rejeter ════════════════════════════════ -->
    <div
      v-if="showRejectModal"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showRejectModal = false"
    >
      <div class="card w-full max-w-sm p-6 space-y-5">
        <h2 class="font-serif text-xl">Rejeter le compte</h2>
        <p class="text-sm text-app-text/60">
          Compte de <strong class="text-white">{{ selectedUser?.nom }} {{ selectedUser?.prenom }}</strong>
        </p>
        <div>
          <label class="f-label">Raison du rejet *</label>
          <textarea
            v-model="rejectReason"
            class="f-input min-h-[80px] resize-none"
            placeholder="Ex: Dossier incomplet, informations non vérifiables..."
            required
          />
        </div>
        <div class="flex gap-3 justify-end">
          <button class="btn btn-outline btn-md" @click="showRejectModal = false">Annuler</button>
          <button
            class="btn btn-danger btn-md"
            :disabled="savingRejection || !rejectReason.trim()"
            @click="submitReject"
          >
            {{ savingRejection ? 'Enregistrement...' : '✕ Confirmer le rejet' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>