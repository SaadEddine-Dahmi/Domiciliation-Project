<!-- app/pages/admin/clients.vue -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'
import { representantService } from '~/services/representant.service'
import type { Representant } from '~/types/entreprise'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems, loading } = storeToRefs(clientsStore)
const { success, error: toastError }  = useToast()

// ── State modaux ──────────────────────────────────────────
const showModal    = ref(false)
const showRepModal = ref(false)
const modalMode    = ref<'create' | 'edit'>('create')
const editId       = ref<number | null>(null)
const saving       = ref(false)
const savingRep    = ref(false)
const search       = ref('')
const serverError  = ref('')

// ── Representant (single per entreprise) ─────────────────
const selectedClientId  = ref<number | null>(null)
const representant      = ref<Representant | null>(null)
const loadingRep        = ref(false)
const repMode           = ref<'view' | 'edit' | 'create'>('view')

// ── Formulaire entreprise ─────────────────────────────────
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

// ── Recherche ─────────────────────────────────────────────
const filtered = computed(() => {
  if (!search.value.trim()) return clientItems.value
  const q = search.value.toLowerCase()
  return clientItems.value.filter(c =>
    c.raison_sociale?.toLowerCase().includes(q) ||
    c.ville?.toLowerCase().includes(q) ||
    c.client_user?.email?.toLowerCase().includes(q) ||
    c.client_user?.nom?.toLowerCase().includes(q)
  )
})

// ── Modal entreprise : ouvrir création ───────────────────
function openCreate() {
  modalMode.value   = 'create'
  editId.value      = null
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

// ── Modal entreprise : ouvrir édition ────────────────────
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
    client_password:  '',
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
      await clientsStore.create({ ...form })
      success('Client créé avec succès')
    } else if (editId.value) {
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
      if (form.client_password.trim().length >= 8) {
        await clientsStore.updatePassword(editId.value, form.client_password, form.client_password)
        success('Client et mot de passe mis à jour')
      } else {
        success('Client mis à jour')
      }
    }
    showModal.value = false
    await clientsStore.fetchAll()
  } catch (e: any) {
    serverError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    toastError?.(serverError.value)
  } finally {
    saving.value = false
  }
}

// ── Representant modal ────────────────────────────────────
async function openRepModal(clientId: number): Promise<void> {
  selectedClientId.value = clientId
  loadingRep.value       = true
  representant.value     = null
  repMode.value          = 'view'
  showRepModal.value     = true
  try {
    const res = await representantService.get(clientId)
    representant.value = res.data ?? null
    if (!representant.value) openRepCreate()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement représentant')
  } finally {
    loadingRep.value = false
  }
}

function openRepCreate(): void {
  repMode.value = 'create'
  Object.assign(repForm, {
    nom: '', prenom: '', cin: '', nationalite: 'Marocaine',
    date_naissance: '', adresse: '', telephone: '', email: '',
  })
}

function openRepEdit(): void {
  if (!representant.value) return
  repMode.value = 'edit'
  Object.assign(repForm, {
    nom:            representant.value.nom            ?? '',
    prenom:         representant.value.prenom         ?? '',
    cin:            representant.value.cin            ?? '',
    nationalite:    representant.value.nationalite    ?? 'Marocaine',
    date_naissance: representant.value.date_naissance ?? '',
    adresse:        representant.value.adresse        ?? '',
    telephone:      representant.value.telephone      ?? '',
    email:          representant.value.email          ?? '',
  })
}

async function submitRep(): Promise<void> {
  if (!selectedClientId.value) return
  savingRep.value = true
  try {
    if (repMode.value === 'create') {
      const res = await representantService.create(selectedClientId.value, { ...repForm })
      representant.value = res.data
      success('Représentant ajouté')
    } else {
      const res = await representantService.update(selectedClientId.value, { ...repForm })
      representant.value = res.data
      success('Représentant mis à jour')
    }
    repMode.value = 'view'
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur sauvegarde représentant')
  } finally {
    savingRep.value = false
  }
}

async function deleteRep(): Promise<void> {
  if (!selectedClientId.value || !confirm('Supprimer le représentant ?')) return
  try {
    await representantService.remove(selectedClientId.value)
    representant.value = null
    repMode.value      = 'create'
    success('Représentant supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

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
        <h1 class="font-serif text-2xl">
          Clients <em class="italic" style="color:#c8a96e">&amp; Entreprises</em>
        </h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">
          {{ clientItems.length }} entreprise(s) enregistrée(s)
        </p>
      </div>
      <button class="btn btn-gold btn-md" @click="openCreate">
        + Nouveau client
      </button>
    </div>

    <!-- Search -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
           width="15" height="15" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" stroke-linecap="round"
           style="color:var(--app-text-faint)">
        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
      </svg>
      <input
        v-model="search"
        class="f-input pl-9"
        placeholder="Rechercher par raison sociale, ville, email..."
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12" style="color:var(--app-text-faint)">
      Chargement...
    </div>

    <!-- Client list -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="client in filtered" :key="client.id"
        class="card p-4 flex items-center justify-between gap-4 flex-wrap"
      >
        <!-- Left: avatar + info — clicking goes to detail page -->
        <NuxtLink
          :to="`/admin/clients/${client.id}`"
          class="flex items-center gap-4 min-w-0 flex-1 group"
        >
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 transition-transform group-hover:scale-105"
            style="background:rgba(200,169,110,0.15);color:#c8a96e"
          >
            {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="font-semibold truncate group-hover:underline" style="text-underline-offset:3px">
              {{ client.raison_sociale }}
            </p>
            <p class="text-xs truncate" style="color:var(--app-text-muted)">
              {{ client.forme_juridique ?? '' }}
              <span v-if="client.ville"> · {{ client.ville }}</span>
            </p>
            <p v-if="client.client_user" class="text-xs mt-0.5 truncate" style="color:var(--app-text-faint)">
              {{ client.client_user.nom }} {{ client.client_user.prenom }}
              · {{ client.client_user.email }}
            </p>
          </div>
        </NuxtLink>

        <!-- Right: actions -->
        <div class="flex items-center gap-2 flex-wrap shrink-0">
          <span
            v-if="client.statut"
            class="text-xs px-2 py-0.5 rounded-full font-medium"
            :class="statutColor[client.statut] ?? 'bg-white/5'"
          >{{ client.statut }}</span>

          <!-- Voir détail — navigates to /admin/clients/{id} -->
          <NuxtLink
            :to="`/admin/clients/${client.id}`"
            class="btn btn-gold btn-sm"
          >
            Voir détail
          </NuxtLink>

          <button class="btn btn-outline btn-sm" @click="openRepModal(client.id)">
            Représentant
          </button>

          <button class="btn btn-outline btn-sm" @click.prevent="openEdit(client)">
            Modifier
          </button>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else class="card p-10 text-center" style="color:var(--app-text-faint)">
      <p class="text-3xl mb-3">🏢</p>
      <p>{{ search ? `Aucun résultat pour "${search}"` : 'Aucun client trouvé.' }}</p>
      <button v-if="!search" class="btn btn-gold btn-md mt-4" @click="openCreate">
        Créer le premier client
      </button>
    </div>


    <!-- ════ Modal Créer / Modifier ════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.75)"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-2xl max-h-[90vh] flex flex-col" @click.stop>

          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom:1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">
              {{ modalMode === 'create' ? 'Nouveau client' : 'Modifier le client' }}
            </h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive"
                    @click="showModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-6 py-5">
            <form class="space-y-5" @submit.prevent="submitEntreprise">

              <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Entreprise</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
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

              <p class="text-xs uppercase tracking-widest font-bold pt-2" style="color:#c8a96e">
                Compte accès portail client
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                <div class="sm:col-span-2">
                  <label class="f-label">
                    {{ modalMode === 'create' ? 'Mot de passe *' : 'Nouveau mot de passe (laisser vide pour ne pas changer)' }}
                  </label>
                  <input
                    v-model="form.client_password"
                    class="f-input"
                    type="password"
                    :required="modalMode === 'create'"
                    placeholder="Min. 8 caractères"
                  />
                  <p v-if="modalMode === 'edit' && form.client_password.length > 0 && form.client_password.length < 8"
                     class="text-xs text-yellow-400 mt-1">
                    ⚠ Minimum 8 caractères
                  </p>
                  <p v-else-if="modalMode === 'edit' && form.client_password.length >= 8"
                     class="text-xs text-green-400 mt-1">
                    ✓ Le mot de passe sera mis à jour
                  </p>
                </div>
              </div>

              <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

              <div class="flex gap-3 justify-end pt-1">
                <button type="button" class="btn btn-outline btn-md" @click="showModal = false">Annuler</button>
                <button type="submit" class="btn btn-gold btn-md" :disabled="saving">
                  {{ saving ? 'Enregistrement...' : modalMode === 'create' ? 'Créer le client' : 'Sauvegarder' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>


    <!-- ════ Modal Représentant ═════════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showRepModal"
        class="fixed inset-0 z-210 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.75)"
        @click.self="showRepModal = false"
      >
        <div class="card w-full max-w-xl max-h-[90vh] flex flex-col" @click.stop>

          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom:1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">Représentant légal</h2>
            <button class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive"
                    @click="showRepModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
            <div v-if="loadingRep" class="text-center py-8" style="color:var(--app-text-faint)">
              Chargement...
            </div>

            <div v-else-if="representant && repMode === 'view'" class="space-y-4">
              <div class="rounded-xl p-4 space-y-2"
                   style="background:var(--app-surface-2);border:1px solid var(--app-border)">
                <p class="font-semibold text-lg">{{ representant.prenom }} {{ representant.nom }}</p>
                <div class="grid grid-cols-2 gap-2 text-sm" style="color:var(--app-text-muted)">
                  <p><span class="font-medium" style="color:var(--app-text)">CIN :</span> {{ representant.cin }}</p>
                  <p v-if="representant.nationalite">
                    <span class="font-medium" style="color:var(--app-text)">Nationalité :</span> {{ representant.nationalite }}
                  </p>
                  <p v-if="representant.date_naissance">
                    <span class="font-medium" style="color:var(--app-text)">Naissance :</span> {{ representant.date_naissance }}
                  </p>
                  <p v-if="representant.telephone">
                    <span class="font-medium" style="color:var(--app-text)">Tél :</span> {{ representant.telephone }}
                  </p>
                  <p v-if="representant.email" class="col-span-2">
                    <span class="font-medium" style="color:var(--app-text)">Email :</span> {{ representant.email }}
                  </p>
                  <p v-if="representant.adresse" class="col-span-2">
                    <span class="font-medium" style="color:var(--app-text)">Adresse :</span> {{ representant.adresse }}
                  </p>
                </div>
              </div>
              <div class="flex gap-2 justify-end">
                <button class="btn btn-danger btn-sm" @click="deleteRep">Supprimer</button>
                <button class="btn btn-gold btn-md" @click="openRepEdit">Modifier</button>
              </div>
            </div>

            <div v-else-if="repMode === 'create' || repMode === 'edit'" class="space-y-4">
              <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
                {{ repMode === 'create' ? 'Ajouter le représentant' : 'Modifier le représentant' }}
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="f-label">Nom *</label>
                  <input v-model="repForm.nom" class="f-input" required placeholder="El Jadiani" />
                </div>
                <div>
                  <label class="f-label">Prénom</label>
                  <input v-model="repForm.prenom" class="f-input" placeholder="Youssef" />
                </div>
                <div>
                  <label class="f-label">CIN *</label>
                  <input v-model="repForm.cin" class="f-input" required placeholder="BJ422176" />
                </div>
                <div>
                  <label class="f-label">Nationalité</label>
                  <input v-model="repForm.nationalite" class="f-input" placeholder="Marocaine" />
                </div>
                <div>
                  <label class="f-label">Date de naissance</label>
                  <input v-model="repForm.date_naissance" class="f-input" type="date" />
                </div>
                <div>
                  <label class="f-label">Téléphone</label>
                  <input v-model="repForm.telephone" class="f-input" placeholder="+212 6XX XXX XXX" />
                </div>
                <div>
                  <label class="f-label">Email</label>
                  <input v-model="repForm.email" class="f-input" type="email" />
                </div>
                <div>
                  <label class="f-label">Adresse</label>
                  <input v-model="repForm.adresse" class="f-input" />
                </div>
              </div>
              <div class="flex gap-2 justify-end">
                <button v-if="repMode === 'edit'" class="btn btn-outline btn-sm" @click="repMode = 'view'">
                  Annuler
                </button>
                <button
                  class="btn btn-gold btn-md"
                  :disabled="savingRep || !repForm.nom || !repForm.cin"
                  @click="submitRep"
                >
                  {{ savingRep ? 'Enregistrement...' : repMode === 'create' ? '+ Ajouter' : 'Sauvegarder' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>