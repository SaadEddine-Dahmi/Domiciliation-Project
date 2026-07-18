<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const clientsStore = useClientsStore()
const { items: clientItems, loading } = storeToRefs(clientsStore)
const { success, error: toastError } = useToast()

const showModal   = ref(false)
const modalMode   = ref<'create' | 'edit'>('create')
const editId      = ref<number | null>(null)
const saving      = ref(false)
const search      = ref('')
const serverError = ref('')

/**
 * Exactly the eight fields:
 *   1. raison_sociale        — company name
 *   2. gerant_nom            — representative surname
 *   3. gerant_prenom         — representative first name
 *   4. gerant_email          — email
 *   5. gerant_telephone      — phone
 *   6. gerant_date_naissance — date of birth
 *   7. gerant_adresse        — place of residence on CIN/Passeport
 *   8. gerant_cin            — CIN or passport number
 */
const form = reactive({
  raison_sociale:        '',
  gerant_nom:            '',
  gerant_prenom:         '',
  gerant_email:          '',
  gerant_telephone:      '',
  gerant_date_naissance: '',
  gerant_adresse:        '',   // residence address as on CIN/Passeport
  gerant_cin:            '',
})

const filtered = computed(() => {
  if (!search.value.trim()) return clientItems.value
  const q = search.value.toLowerCase()
  return clientItems.value.filter(c => {
    const repName = `${c.representant?.prenom ?? ''} ${c.representant?.nom ?? ''}`.toLowerCase()
    return (
      c.raison_sociale?.toLowerCase().includes(q) ||
      repName.includes(q) ||
      c.representant?.cin?.toLowerCase().includes(q) ||
      c.representant?.email?.toLowerCase().includes(q)
    )
  })
})

function resetForm(): void {
  Object.assign(form, {
    raison_sociale: '',
    gerant_nom: '', gerant_prenom: '', gerant_email: '',
    gerant_telephone: '', gerant_date_naissance: '',
    gerant_adresse: '', gerant_cin: '',
  })
}

function openCreate(): void {
  modalMode.value   = 'create'
  editId.value      = null
  serverError.value = ''
  resetForm()
  showModal.value   = true
}

function openEdit(client: any): void {
  modalMode.value   = 'edit'
  editId.value      = client.id
  serverError.value = ''
  Object.assign(form, {
    raison_sociale:        client.raison_sociale                  ?? '',
    gerant_nom:            client.representant?.nom               ?? '',
    gerant_prenom:         client.representant?.prenom            ?? '',
    gerant_email:          client.representant?.email             ?? '',
    gerant_telephone:      client.representant?.telephone         ?? '',
    gerant_date_naissance: client.representant?.date_naissance    ?? '',
    gerant_adresse:        client.representant?.adresse           ?? '',
    gerant_cin:            client.representant?.cin               ?? '',
  })
  showModal.value = true
}

async function submitEntreprise(): Promise<void> {
  serverError.value = ''
  saving.value      = true
  try {
    if (modalMode.value === 'create') {
      // Step 1: create the entreprise
      const newClient = await clientsStore.create({
        raison_sociale: form.raison_sociale,
      })

      // Step 2: create the représentant with all seven identity fields
      await clientsStore.createRepresentant(newClient.id, {
        nom:            form.gerant_nom    || 'Non renseigné',
        prenom:         form.gerant_prenom || 'Non renseigné',
        cin:            form.gerant_cin    || 'Non renseigné',
        date_naissance: form.gerant_date_naissance || undefined,
        adresse:        form.gerant_adresse        || undefined,
        telephone:      form.gerant_telephone      || undefined,
        email:          form.gerant_email          || undefined,
      })

      success('Client créé avec succès')

    } else if (editId.value) {
      // Update company name
      await clientsStore.update(editId.value, {
        raison_sociale: form.raison_sociale,
      })

      // Update all représentant fields
      await clientsStore.updateRepresentant(editId.value, {
        nom:            form.gerant_nom,
        prenom:         form.gerant_prenom,
        cin:            form.gerant_cin,
        date_naissance: form.gerant_date_naissance || undefined,
        adresse:        form.gerant_adresse        || undefined,
        telephone:      form.gerant_telephone      || undefined,
        email:          form.gerant_email          || undefined,
      })

      success('Client mis à jour')
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
        placeholder="Rechercher par société, représentant, CIN, email..."
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12" style="color:var(--app-text-faint)">
      Chargement...
    </div>

    <!-- List -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="client in filtered"
        :key="client.id"
        class="card p-4 flex items-center justify-between gap-4 flex-wrap"
      >
        <NuxtLink
          :to="`/admin/clients/${client.id}`"
          class="flex items-center gap-4 min-w-0 flex-1 group"
        >
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center
                   font-bold text-sm shrink-0"
            style="background:rgba(200,169,110,0.15);color:#c8a96e"
          >
            {{ (client.raison_sociale ?? '?').slice(0, 2).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <!-- Company name -->
            <p class="font-semibold truncate group-hover:underline"
               style="text-underline-offset:3px">
              {{ client.raison_sociale }}
            </p>
            <!-- Représentant identity summary -->
            <p
              v-if="client.representant"
              class="text-xs mt-0.5 truncate"
              style="color:var(--app-text-faint)"
            >
              {{ client.representant.prenom }} {{ client.representant.nom }}
              <span v-if="client.representant.cin">
                · {{ client.representant.cin }}
              </span>
              <span v-if="client.representant.email">
                · {{ client.representant.email }}
              </span>
            </p>
          </div>
        </NuxtLink>

        <div class="flex items-center gap-2 flex-wrap shrink-0">
          <NuxtLink :to="`/admin/clients/${client.id}`" class="btn btn-gold btn-sm">
            Voir détail
          </NuxtLink>
          <button class="btn btn-outline btn-sm" @click.prevent="openEdit(client)">
            Modifier
          </button>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else class="card p-10 text-center" style="color:var(--app-text-faint)">
      <p class="text-3xl mb-3">🏢</p>
      <p>{{ search ? `Aucun résultat pour « ${search} »` : 'Aucun client trouvé.' }}</p>
      <button v-if="!search" class="btn btn-gold btn-md mt-4" @click="openCreate">
        Créer le premier client
      </button>
    </div>


    <!-- ════ Modal: eight fields only ════ -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.75)"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-lg max-h-[90vh] flex flex-col" @click.stop>

          <!-- Header -->
          <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0"
               style="border-bottom:1px solid var(--app-border-2)">
            <h2 class="font-serif text-xl">
              {{ modalMode === 'create' ? 'Nouveau client' : 'Modifier le client' }}
            </h2>
            <button
              class="w-8 h-8 rounded-lg flex items-center justify-center nav-inactive"
              @click="showModal = false"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-5">
            <form class="space-y-4" @submit.prevent="submitEntreprise">

              <!-- 1. Company name -->
              <div>
                <label class="f-label">Nom de la société *</label>
                <input
                  v-model="form.raison_sociale"
                  class="f-input"
                  required
                  placeholder="WEST ODYSSÉE SARL"
                />
              </div>

              <!-- Separator -->
              <div class="pt-1">
                <p class="text-xs uppercase tracking-widest font-bold"
                   style="color:#c8a96e">
                  Représentant légal
                </p>
                <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">
                  Informations telles qu'elles apparaissent sur son CIN ou Passeport
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                <!-- 2. Nom -->
                <div>
                  <label class="f-label">Nom *</label>
                  <input
                    v-model="form.gerant_nom"
                    class="f-input"
                    required
                    placeholder="WONG"
                  />
                </div>

                <!-- 3. Prénom -->
                <div>
                  <label class="f-label">Prénom *</label>
                  <input
                    v-model="form.gerant_prenom"
                    class="f-input"
                    required
                    placeholder="LAETITIA"
                  />
                </div>

                <!-- 4. Email -->
                <div>
                  <label class="f-label">Email</label>
                  <input
                    v-model="form.gerant_email"
                    class="f-input"
                    type="email"
                    placeholder="laetitia@exemple.com"
                  />
                </div>

                <!-- 5. Téléphone -->
                <div>
                  <label class="f-label">Téléphone</label>
                  <input
                    v-model="form.gerant_telephone"
                    class="f-input"
                    placeholder="+33 6 26 01 11 49"
                  />
                </div>

                <!-- 6. Date de naissance -->
                <div>
                  <label class="f-label">Date de naissance</label>
                  <input
                    v-model="form.gerant_date_naissance"
                    class="f-input"
                    type="date"
                  />
                </div>

                <!-- 8. CIN ou Passeport -->
                <div>
                  <label class="f-label">CIN / Passeport *</label>
                  <input
                    v-model="form.gerant_cin"
                    class="f-input"
                    required
                    placeholder="BJ422176 ou 19AC67035"
                  />
                </div>

                <!-- 7. Adresse de résidence (as on CIN/Passeport) — full width -->
                <div class="sm:col-span-2">
                  <label class="f-label">
                    Adresse de résidence
                    <span class="text-[10px] ml-1 font-normal"
                          style="color:var(--app-text-faint)">
                      telle qu'inscrite sur le CIN ou Passeport
                    </span>
                  </label>
                  <input
                    v-model="form.gerant_adresse"
                    class="f-input"
                    placeholder="5 Avenue Charcot, 92600 Asnières-sur-Seine, France"
                  />
                </div>

              </div>

              <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

              <div class="flex gap-3 justify-end pt-1">
                <button type="button" class="btn btn-outline btn-md"
                        @click="showModal = false">
                  Annuler
                </button>
                <button type="submit" class="btn btn-gold btn-md" :disabled="saving">
                  {{
                    saving
                      ? 'Enregistrement...'
                      : modalMode === 'create'
                        ? 'Créer le client'
                        : 'Sauvegarder'
                  }}
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>