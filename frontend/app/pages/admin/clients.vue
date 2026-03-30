<!-- ============================================================
  pages/admin/clients.vue
  Gestion des clients (entreprises) et représentants
  FIXES :
  - Mot de passe mis à jour via PUT /api/clients/{id}/password
    séparément de la mise à jour des infos entreprise
  - Validation côté front avant envoi
============================================================ -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems, loading } = storeToRefs(clientsStore)
const { success, error: toastError } = useToast()

// ── State modaux ──────────────────────────────────────────
const showModal    = ref(false)
const showRepModal = ref(false)
const modalMode    = ref<'create' | 'edit'>('create')
const editId       = ref<number | null>(null)
const saving       = ref(false)
const savingRep    = ref(false)
const search       = ref('')

// ── Représentants ─────────────────────────────────────────
const representants    = ref<any[]>([])
const loadingReps      = ref(false)
const selectedClientId = ref<number | null>(null)
const repModalMode     = ref<'create' | 'edit'>('create')
const editRepId        = ref<number | null>(null)

// ── Formulaire entreprise + compte client ─────────────────
const form = reactive({
  raison_sociale: '', forme_juridique: '', adresse: '',
  ville: '', pays: 'Maroc', capital: undefined as number | undefined,
  date_creation: '', statut: 'actif',
  client_nom: '', client_prenom: '', client_email: '',
  client_password: '', client_telephone: '',
})

// ── Formulaire représentant ───────────────────────────────
const repForm = reactive({
  nom: '', prenom: '', cin: '', nationalite: 'Marocaine',
  date_naissance: '', adresse: '', telephone: '', email: '',
})

const serverError = ref('')

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

// ── Recherche filtrée ─────────────────────────────────────
const filtered = computed(() => {
  if (!search.value.trim()) return clientItems.value
  const q = search.value.toLowerCase()
  return clientItems.value.filter(c =>
    c.raison_sociale?.toLowerCase().includes(q) ||
    c.ville?.toLowerCase().includes(q) ||
    c.client_user?.email?.toLowerCase().includes(q)
  )
})

// ── Ouvrir modal création ─────────────────────────────────
function openCreate() {
  modalMode.value = 'create'
  editId.value    = null
  serverError.value = ''
  Object.assign(form, {
    raison_sociale: '', forme_juridique: '', adresse: '',
    ville: '', pays: 'Maroc', capital: undefined,
    date_creation: '', statut: 'actif',
    client_nom: '', client_prenom: '', client_email: '',
    client_password: '', client_telephone: '',
  })
  showModal.value = true
}

// ── Ouvrir modal édition ──────────────────────────────────
function openEdit(client: any) {
  modalMode.value   = 'edit'
  editId.value      = client.id
  serverError.value = ''
  Object.assign(form, {
    raison_sociale:   client.raison_sociale   ?? '',
    forme_juridique:  client.forme_juridique  ?? '',
    adresse:          client.adresse          ?? '',
    ville:            client.ville            ?? '',
    pays:             client.pays             ?? 'Maroc',
    capital:          client.capital          ?? undefined,
    date_creation:    client.date_creation    ?? '',
    statut:           client.statut           ?? 'actif',
    client_nom:       client.client_user?.nom       ?? '',
    client_prenom:    client.client_user?.prenom    ?? '',
    client_email:     client.client_user?.email     ?? '',
    client_password:  '',   // toujours vide à l'ouverture
    client_telephone: client.client_user?.telephone ?? '',
  })
  showModal.value = true
}

// ── Soumettre création / modification ─────────────────────
async function submitEntreprise(): Promise<void> {
  serverError.value = ''
  saving.value      = true
  try {
    if (modalMode.value === 'create') {
      // Création : entreprise + compte client en une fois
      await clientsStore.create({ ...form })
      success('Client créé avec succès')

    } else if (editId.value) {
      // 1. Mise à jour des infos entreprise + user (sans mot de passe)
      await clientsStore.update(editId.value, {
        raison_sociale:  form.raison_sociale,
        forme_juridique: form.forme_juridique,
        adresse:         form.adresse,
        ville:           form.ville,
        pays:            form.pays,
        capital:         form.capital,
        date_creation:   form.date_creation,
        statut:          form.statut,
        client_user: {
          nom:       form.client_nom,
          prenom:    form.client_prenom,
          email:     form.client_email,
          telephone: form.client_telephone,
        },
      })

      // 2. Si un nouveau mot de passe est saisi → endpoint dédié
      if (form.client_password.trim().length >= 8) {
        await $fetch(
          `${getApiBase()}/api/clients/${editId.value}/password`,
          {
            method:  'PUT',
            headers: authHeaders(),
            body: {
              password:              form.client_password,
              password_confirmation: form.client_password,
            },
          }
        )
        success('Client et mot de passe mis à jour')
      } else {
        success('Client mis à jour')
      }
    }

    showModal.value = false
  } catch (e: any) {
    serverError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    toastError?.(serverError.value)
  } finally {
    saving.value = false
  }
}

// ── Représentants : ouvrir modal ──────────────────────────
async function openReps(clientId: number): Promise<void> {
  selectedClientId.value = clientId
  loadingReps.value      = true
  showRepModal.value     = true
  try {
    const res = await $fetch<{ success: boolean; data: any[] }>(
      `${getApiBase()}/api/entreprises/${clientId}/representants`,
      { headers: authHeaders() }
    )
    representants.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement représentants')
  } finally {
    loadingReps.value = false
  }
}

// ── Représentants : reset form ────────────────────────────
function openAddRep(): void {
  repModalMode.value = 'create'
  editRepId.value    = null
  Object.assign(repForm, {
    nom: '', prenom: '', cin: '', nationalite: 'Marocaine',
    date_naissance: '', adresse: '', telephone: '', email: '',
  })
}

// ── Représentants : édition ───────────────────────────────
function openEditRep(rep: any): void {
  repModalMode.value = 'edit'
  editRepId.value    = rep.id
  Object.assign(repForm, {
    nom:            rep.nom            ?? '',
    prenom:         rep.prenom         ?? '',
    cin:            rep.cin            ?? '',
    nationalite:    rep.nationalite    ?? 'Marocaine',
    date_naissance: rep.date_naissance ?? '',
    adresse:        rep.adresse        ?? '',
    telephone:      rep.telephone      ?? '',
    email:          rep.email          ?? '',
  })
}

// ── Représentants : soumettre ─────────────────────────────
async function submitRep(): Promise<void> {
  if (!selectedClientId.value) return
  savingRep.value = true
  try {
    if (repModalMode.value === 'create') {
      const res = await $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/entreprises/${selectedClientId.value}/representants`,
        { method: 'POST', headers: authHeaders(), body: { ...repForm } }
      )
      representants.value.push(res.data)
      await clientsStore.fetchAll()
      success('Représentant ajouté')
    } else if (editRepId.value) {
      const res = await $fetch<{ success: boolean; data: any }>(
        `${getApiBase()}/api/entreprises/${selectedClientId.value}/representants/${editRepId.value}`,
        { method: 'PUT', headers: authHeaders(), body: { ...repForm } }
      )
      const idx = representants.value.findIndex(r => r.id === editRepId.value)
      if (idx !== -1) representants.value[idx] = res.data
      success('Représentant mis à jour')
    }
    openAddRep()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur sauvegarde représentant')
  } finally {
    savingRep.value = false
  }
}

// ── Représentants : suppression ───────────────────────────
async function deleteRep(repId: number): Promise<void> {
  if (!selectedClientId.value || !confirm('Supprimer ce représentant ?')) return
  try {
    await $fetch(
      `${getApiBase()}/api/entreprises/${selectedClientId.value}/representants/${repId}`,
      { method: 'DELETE', headers: authHeaders() }
    )
    representants.value = representants.value.filter(r => r.id !== repId)
    success('Représentant supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

// ── Couleurs statuts ──────────────────────────────────────
const statutColor: Record<string, string> = {
  actif:    'text-green-400 bg-green-400/10',
  inactif:  'text-red-400 bg-red-400/10',
  suspendu: 'text-yellow-400 bg-yellow-400/10',
}

onMounted(() => clientsStore.fetchAll())
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">Clients <em class="text-gold italic">&amp; Entreprises</em></h1>
        <p class="text-app-text/50 text-sm mt-1">{{ clientItems.length }} entreprise(s) enregistrée(s)</p>
      </div>
      <button class="btn btn-gold btn-md" @click="openCreate">+ Nouveau client</button>
    </div>

    <!-- Recherche -->
    <div class="card p-3">
      <input v-model="search" class="f-input" placeholder="Rechercher par raison sociale, ville, email..." />
    </div>

    <!-- Chargement -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Liste clients -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="client in filtered" :key="client.id"
        class="card p-4 flex items-center justify-between gap-4 flex-wrap"
      >
        <div class="flex items-center gap-4">
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
            style="background:rgba(200,169,110,0.15);color:#c8a96e"
          >
            {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
          </div>
          <div>
            <p class="font-semibold">{{ client.raison_sociale }}</p>
            <p class="text-xs text-app-text/50">
              {{ client.forme_juridique ?? '' }}
              <span v-if="client.ville"> · {{ client.ville }}</span>
            </p>
            <p v-if="client.client_user" class="text-xs text-app-text/40 mt-0.5">
              {{ client.client_user.nom }} {{ client.client_user.prenom }}
              · {{ client.client_user.email }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <span
            v-if="client.statut"
            class="text-xs px-2 py-0.5 rounded-full font-medium"
            :class="statutColor[client.statut] ?? 'text-app-text/50 bg-white/5'"
          >{{ client.statut }}</span>
          <button class="btn btn-outline btn-sm" @click="openReps(client.id)">👤 Représentants</button>
          <button class="btn btn-outline btn-sm" @click="openEdit(client)">Modifier</button>
        </div>
      </div>
    </div>

    <!-- État vide -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">🏢</p>
      <p>Aucun client trouvé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="openCreate">Créer le premier client</button>
    </div>

    <!-- ════ Modal Créer / Modifier Entreprise ════════════ -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showModal = false"
    >
      <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">
            {{ modalMode === 'create' ? 'Nouveau client' : 'Modifier le client' }}
          </h2>
          <button class="text-app-text/40 hover:text-white text-xl" @click="showModal = false">✕</button>
        </div>

        <form class="space-y-4" @submit.prevent="submitEntreprise">

          <!-- Section Entreprise -->
          <p class="text-xs uppercase text-gold tracking-widest font-bold">Entreprise</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
              <label class="f-label">Raison sociale *</label>
              <input v-model="form.raison_sociale" class="f-input" required placeholder="BRONX IMMOBILIER SARL" />
            </div>
            <div>
              <label class="f-label">Forme juridique</label>
              <input v-model="form.forme_juridique" class="f-input" placeholder="SARL, SA..." />
            </div>
            <div>
              <label class="f-label">Statut</label>
              <select v-model="form.statut" class="f-input">
                <option value="actif">Actif</option>
                <option value="inactif">Inactif</option>
                <option value="suspendu">Suspendu</option>
              </select>
            </div>
            <div>
              <label class="f-label">Adresse</label>
              <input v-model="form.adresse" class="f-input" placeholder="123 Rue Hassan II" />
            </div>
            <div>
              <label class="f-label">Ville</label>
              <input v-model="form.ville" class="f-input" placeholder="Casablanca" />
            </div>
            <div>
              <label class="f-label">Pays</label>
              <input v-model="form.pays" class="f-input" placeholder="Maroc" />
            </div>
            <div>
              <label class="f-label">Capital (DH)</label>
              <input v-model.number="form.capital" class="f-input" type="number" placeholder="100000" />
            </div>
            <div>
              <label class="f-label">Date de création</label>
              <input v-model="form.date_creation" class="f-input" type="date" />
            </div>
          </div>

          <!-- Section Compte client -->
          <p class="text-xs uppercase text-gold tracking-widest font-bold pt-2">Compte accès portail client</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="f-label">Nom *</label>
              <input v-model="form.client_nom" class="f-input" :required="modalMode === 'create'" placeholder="El Jadiani" />
            </div>
            <div>
              <label class="f-label">Prénom</label>
              <input v-model="form.client_prenom" class="f-input" placeholder="Youssef" />
            </div>
            <div>
              <label class="f-label">Email *</label>
              <input v-model="form.client_email" class="f-input" type="email" :required="modalMode === 'create'" placeholder="client@exemple.ma" />
            </div>
            <div>
              <label class="f-label">Téléphone</label>
              <input v-model="form.client_telephone" class="f-input" placeholder="+212 6XX XXX XXX" />
            </div>
            <div class="md:col-span-2">
              <label class="f-label">
                {{ modalMode === 'create' ? 'Mot de passe *' : 'Nouveau mot de passe (min. 8 car.) — laisser vide pour ne pas changer' }}
              </label>
              <input
                v-model="form.client_password"
                class="f-input"
                type="password"
                :required="modalMode === 'create'"
                placeholder="Min. 8 caractères"
              />
              <!-- Indicateur visuel en mode édition -->
              <p
                v-if="modalMode === 'edit' && form.client_password.length > 0 && form.client_password.length < 8"
                class="text-xs text-yellow-400 mt-1"
              >
                ⚠ Minimum 8 caractères pour changer le mot de passe
              </p>
              <p
                v-else-if="modalMode === 'edit' && form.client_password.length >= 8"
                class="text-xs text-green-400 mt-1"
              >
                ✓ Le mot de passe sera mis à jour
              </p>
            </div>
          </div>

          <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

          <div class="flex gap-3 justify-end pt-2">
            <button type="button" class="btn btn-outline btn-md" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-gold btn-md" :disabled="saving">
              {{ saving ? 'Enregistrement...' : modalMode === 'create' ? 'Créer le client' : 'Sauvegarder' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ════ Modal Représentants ══════════════════════════ -->
    <div
      v-if="showRepModal"
      class="fixed inset-0 z-[110] bg-black/70 flex items-center justify-center p-4"
      @click.self="showRepModal = false"
    >
      <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">Représentants</h2>
          <button class="text-app-text/40 hover:text-white text-xl" @click="showRepModal = false">✕</button>
        </div>

        <!-- Liste -->
        <div v-if="loadingReps" class="text-center py-6 text-app-text/40">Chargement...</div>
        <div v-else-if="representants.length" class="space-y-2">
          <div
            v-for="r in representants" :key="r.id"
            class="rounded-xl border border-white/10 p-3 flex items-center justify-between gap-3"
          >
            <div>
              <p class="font-semibold text-sm">{{ r.nom }} {{ r.prenom }}</p>
              <p class="text-xs text-app-text/50">
                CIN: {{ r.cin }} · {{ r.telephone ?? '-' }} · {{ r.email ?? '-' }}
              </p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button class="btn btn-outline btn-sm" @click="openEditRep(r)">Éditer</button>
              <button class="btn btn-danger btn-sm" @click="deleteRep(r.id)">✕</button>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-app-text/40 text-center py-4">Aucun représentant.</p>

        <!-- Formulaire ajout / édition -->
        <div class="border-t border-white/10 pt-4 space-y-3">
          <p class="text-xs uppercase text-gold tracking-widest font-bold">
            {{ repModalMode === 'create' ? 'Ajouter un représentant' : 'Modifier le représentant' }}
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label class="f-label">Nom *</label><input v-model="repForm.nom" class="f-input" required placeholder="Dahmi" /></div>
            <div><label class="f-label">Prénom</label><input v-model="repForm.prenom" class="f-input" placeholder="Saad" /></div>
            <div><label class="f-label">CIN *</label><input v-model="repForm.cin" class="f-input" required placeholder="BE123456" /></div>
            <div><label class="f-label">Nationalité</label><input v-model="repForm.nationalite" class="f-input" placeholder="Marocaine" /></div>
            <div><label class="f-label">Date de naissance</label><input v-model="repForm.date_naissance" class="f-input" type="date" /></div>
            <div><label class="f-label">Téléphone</label><input v-model="repForm.telephone" class="f-input" placeholder="+212 6XX" /></div>
            <div><label class="f-label">Email</label><input v-model="repForm.email" class="f-input" type="email" /></div>
            <div><label class="f-label">Adresse</label><input v-model="repForm.adresse" class="f-input" /></div>
          </div>
          <div class="flex gap-2 justify-end">
            <button v-if="repModalMode === 'edit'" class="btn btn-outline btn-sm" @click="openAddRep">Annuler</button>
            <button
              class="btn btn-gold btn-md"
              :disabled="savingRep || !repForm.nom || !repForm.cin"
              @click="submitRep"
            >
              {{ savingRep ? 'Enregistrement...' : repModalMode === 'create' ? '+ Ajouter' : 'Sauvegarder' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>