<script setup lang="ts">
// pages/admin/clients/index.vue
//
// Client list page.
//
// Password field (create mode only):
//   - Optional. Left blank → backend generates a random password and
//     returns it once; PasswordRevealModal shows it so the domiciliataire
//     can copy it for the client.
//   - Filled in (typed manually, or pre-filled via the "Générer" button)
//     → sent as-is; no reveal modal afterwards since the domiciliataire
//     already has it in the form they just typed.
//   - Not shown at all in edit mode — changing an existing client's
//     password is a separate action (reset), not part of profile edits.
//
// Phone field:
//   Delegated to <PhoneNumberField> (components/PhoneNumberField.vue).
//   sortedDialCodeValues is still needed here for splitPhone() when
//   loading an existing client's phone number into the edit form.

import { storeToRefs } from 'pinia'
import { useClientsStore } from '~/stores/clients'
import PasswordRevealModal from '~/components/PasswordRevealModal.vue'
import { sortedDialCodeValues } from '~/utils/countryDialCodes'

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

function joinPhone(dialCode: string, number: string): string {
  const local = number.trim().replace(/^0+/, '')
  return local ? `${dialCode} ${local}` : ''
}

function splitPhone(value: string | null | undefined): { dialCode: string; number: string } {
  const raw = (value ?? '').trim()
  const matchedCode = sortedDialCodeValues.find(code => raw.startsWith(code))
  if (!matchedCode) return { dialCode: '+212', number: raw.replace(/^\+/, '') }
  return {
    dialCode: matchedCode,
    number: raw.slice(matchedCode.length).trim(),
  }
}

/**
 * client_email/client_password only apply in create mode — they're not
 * sent by update() at all (see submitEntreprise()).
 */
const form = reactive({
  raison_sociale:        '',
  gerant_nom:            '',
  gerant_prenom:         '',
  gerant_email:          '',
  gerant_dial_code:      '+212',
  gerant_phone_number:   '',
  gerant_date_naissance: '',
  gerant_adresse:        '', // residence address as on CIN/Passport
  gerant_cin:            '',
  client_email:          '', // client portal login
  client_password:       '', // optional — blank = auto-generated
})

// ── Generated-password reveal modal state ──────────────────────
const showPasswordModal   = ref(false)
const revealedPassword    = ref('')
const revealedClientName  = ref('')

/**
 * Fills the password field with a random suggestion the domiciliataire
 * can accept, edit, or clear. Purely a client-side convenience — the
 * value is only "real" once the form is submitted; leaving the field
 * blank still lets the backend generate its own on submit.
 */
function generateSuggestedPassword(): void {
  const alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
  let out = ''
  for (let i = 0; i < 10; i++) {
    out += alphabet[Math.floor(Math.random() * alphabet.length)]
  }
  form.client_password = out
}

// ── Search / filter ──────────────────────────────────────────
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

// ── Small display helpers ────────────────────────────────────
const statutColor: Record<string, string> = {
  actif: '#22c55e',
  inactif: '#ef4444',
  suspendu: '#f59e0b',
}

function initials(name: string | null | undefined): string {
  return (name ?? '?').slice(0, 2).toUpperCase()
}

// ── Create / edit modal ──────────────────────────────────────
function resetForm(): void {
  Object.assign(form, {
    raison_sociale: '',
    gerant_nom: '', gerant_prenom: '', gerant_email: '',
    gerant_dial_code: '+212', gerant_phone_number: '', gerant_date_naissance: '',
    gerant_adresse: '', gerant_cin: '',
    client_email: '', client_password: '',
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
  const phone = splitPhone(client.representant?.telephone)
  Object.assign(form, {
    raison_sociale:        client.raison_sociale               ?? '',
    gerant_nom:            client.representant?.nom            ?? '',
    gerant_prenom:         client.representant?.prenom         ?? '',
    gerant_email:          client.representant?.email          ?? '',
    gerant_dial_code:      phone.dialCode,
    gerant_phone_number:   phone.number,
    gerant_date_naissance: client.representant?.date_naissance ?? '',
    gerant_adresse:        client.representant?.adresse        ?? '',
    gerant_cin:            client.representant?.cin             ?? '',
    client_email:          '',
    client_password:       '',
  })
  showModal.value = true
}

async function submitEntreprise(): Promise<void> {
  serverError.value = ''
  saving.value      = true
  try {
    const gerantTelephone = joinPhone(form.gerant_dial_code, form.gerant_phone_number)

    if (modalMode.value === 'create') {
      if (!form.client_email.trim()) {
        serverError.value = "L'email d'accès au portail client est obligatoire."
        saving.value = false
        return
      }

      // Step 1: create the company record + linked client portal account.
      // client_password is passed through as-is: blank means "let the
      // backend generate one", filled means "use exactly this".
      const { entreprise, generatedPassword } = await clientsStore.create({
        raison_sociale:    form.raison_sociale,
        client_nom:        form.gerant_nom    || 'Non renseigné',
        client_prenom:     form.gerant_prenom || undefined,
        client_email:      form.client_email,
        client_telephone:  gerantTelephone || undefined,
        client_password:   form.client_password  || undefined,
      })

      // Step 2: create the linked legal representative.
      await clientsStore.createRepresentant(entreprise.id, {
        nom:            form.gerant_nom    || 'Non renseigné',
        prenom:         form.gerant_prenom || 'Non renseigné',
        cin:            form.gerant_cin    || 'Non renseigné',
        date_naissance: form.gerant_date_naissance || undefined,
        adresse:        form.gerant_adresse        || undefined,
        telephone:      gerantTelephone            || undefined,
        email:          form.gerant_email          || undefined,
      })

      success('Client créé avec succès')
      showModal.value = false

      // Only show the reveal modal when the backend generated the
      // password itself — if the domiciliataire typed their own, they
      // already have it and don't need it echoed back.
      if (generatedPassword) {
        revealedPassword.value   = generatedPassword
        revealedClientName.value = entreprise.raison_sociale
        showPasswordModal.value  = true
      }

    } else if (editId.value) {
      await clientsStore.update(editId.value, {
        raison_sociale: form.raison_sociale,
      })

      await clientsStore.updateRepresentant(editId.value, {
        nom:            form.gerant_nom,
        prenom:         form.gerant_prenom,
        cin:            form.gerant_cin,
        date_naissance: form.gerant_date_naissance || undefined,
        adresse:        form.gerant_adresse        || undefined,
        telephone:      gerantTelephone            || undefined,
        email:          form.gerant_email          || undefined,
      })

      success('Client mis à jour')
      showModal.value = false
    }

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

    <!-- ── Header ────────────────────────────────────────── -->
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

    <!-- ── Search ────────────────────────────────────────── -->
    <div class="relative w-full max-w-xl">
      <svg
        class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--app-text-faint)]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        aria-hidden="true"
      >
        <circle cx="11" cy="11" r="7.5" />
        <path d="m20 20-3.8-3.8" />
      </svg>

      <input
        v-model.trim="search"
        type="search"
        autocomplete="off"
        class="f-input w-full !pl-10 !pr-10 transition-all duration-200
               focus:border-[var(--app-primary)] focus:ring-2 focus:ring-[var(--app-primary)]/20
               placeholder:text-[var(--app-text-faint)]
               [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none"
        placeholder="Rechercher une société, un représentant, une CIN ou un email..."
        aria-label="Rechercher une société, un représentant, une CIN ou un email"
        @keyup.esc="search = ''"
      />

      <button
        v-if="search"
        type="button"
        class="absolute right-2.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-[var(--app-text-faint)] transition-colors hover:bg-black/5 hover:text-black focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-primary)] dark:hover:bg-white/10 dark:hover:text-white"
        aria-label="Effacer la recherche"
        @click="search = ''"
      >
        <svg
          class="h-3.5 w-3.5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M18 6 6 18" />
          <path d="m6 6 12 12" />
        </svg>
      </button>
    </div>

    <!-- ── Loading state ─────────────────────────────────── -->
    <div v-if="loading" class="text-center py-12" style="color:var(--app-text-faint)">
      Chargement...
    </div>

    <!-- ── Client account cards ──────────────────────────── -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="client in filtered"
        :key="client.id"
        class="card p-4 flex items-center justify-between gap-4 flex-wrap"
      >
        <ProfileImageLightbox
          :src="client.client_user?.photo_url"
          :initials="client.client_user?.initials ?? initials(client.raison_sociale)"
          :label="`Photo de ${client.raison_sociale}`"
          :size="44"
          rounded="xl"
        />

        <NuxtLink
          :to="`/admin/clients/${client.id}`"
          class="min-w-0 flex-1 group"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold truncate group-hover:underline"
                 style="text-underline-offset:3px; color: var(--app-text)">
                {{ client.raison_sociale }}
              </p>
              <span
                v-if="client.statut"
                class="text-[10px] px-2 py-0.5 rounded-full font-semibold uppercase tracking-wide"
                :style="`color: ${statutColor[client.statut] ?? '#94a3b8'}; background: ${statutColor[client.statut] ?? '#94a3b8'}18`"
              >{{ client.statut }}</span>
            </div>

            <p
              v-if="client.representant"
              class="text-xs mt-0.5 truncate"
              style="color:var(--app-text-faint)"
            >
              {{ client.representant.prenom }} {{ client.representant.nom }}
              <span v-if="client.representant.cin"> · {{ client.representant.cin }}</span>
              <span v-if="client.representant.email"> · {{ client.representant.email }}</span>
            </p>

            <p
              v-if="client.ville || client.forme_juridique"
              class="text-[11px] mt-0.5 truncate"
              style="color: var(--app-text-faint)"
            >
              <span v-if="client.forme_juridique">{{ client.forme_juridique }}</span>
              <span v-if="client.forme_juridique && client.ville"> · </span>
              <span v-if="client.ville">{{ client.ville }}</span>
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

    <!-- ── Empty state ───────────────────────────────────── -->
    <div v-else class="card p-10 text-center" style="color:var(--app-text-faint)">
      <p class="text-3xl mb-3">🏢</p>
      <p>{{ search ? `Aucun résultat pour « ${search} »` : 'Aucun client trouvé.' }}</p>
      <button v-if="!search" class="btn btn-gold btn-md mt-4" @click="openCreate">
        Créer le premier client
      </button>
    </div>

    <!-- ── Create / edit modal ───────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-200 flex items-center justify-center p-4 overflow-y-auto"
        style="background:rgba(0,0,0,0.75)"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-lg max-h-[calc(100vh-2rem)] overflow-hidden flex flex-col" @click.stop>

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

          <div class="flex-1 min-h-0 overflow-y-auto overflow-x-visible px-6 py-5">
            <form class="space-y-4" @submit.prevent="submitEntreprise">

              <div>
                <label class="f-label">Nom de la société *</label>
                <input
                  v-model="form.raison_sociale"
                  class="f-input"
                  required
                  placeholder="WEST ODYSSÉE SARL"
                />
              </div>

              <div class="pt-1">
                <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
                  Représentant légal
                </p>
                <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">
                  Informations telles qu'elles apparaissent sur son CIN ou Passeport
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="f-label">Nom *</label>
                  <input v-model="form.gerant_nom" class="f-input" required placeholder="WONG" />
                </div>
                <div>
                  <label class="f-label">Prénom *</label>
                  <input v-model="form.gerant_prenom" class="f-input" required placeholder="LAETITIA" />
                </div>
                <div>
                  <label class="f-label">Email (contact représentant)</label>
                  <input v-model="form.gerant_email" class="f-input" type="email" placeholder="laetitia@exemple.com" />
                </div>
                <PhoneNumberField
  v-model:dial-code="form.gerant_dial_code"
  v-model:number="form.gerant_phone_number"
  label="Téléphone"
  placeholder="6 26 01 11 49"
  class="sm:col-span-2"
/>
<div>
                  <label class="f-label">Date de naissance</label>
                  <input v-model="form.gerant_date_naissance" class="f-input" type="date" />
                </div>
                <div>
                  <label class="f-label">CIN / Passeport *</label>
                  <input v-model="form.gerant_cin" class="f-input" required placeholder="BJ422176 ou 19AC67035" />
                </div>
                <div class="sm:col-span-2">
                  <label class="f-label">
                    Adresse de résidence
                    <span class="text-[10px] ml-1 font-normal" style="color:var(--app-text-faint)">
                      telle qu'inscrite sur le CIN ou Passeport
                    </span>
                  </label>
                  <input v-model="form.gerant_adresse" class="f-input" placeholder="5 Avenue Charcot, 92600 Asnières-sur-Seine, France" />
                </div>
              </div>

              <!-- ── Portail client — création uniquement ─────────── -->
              <template v-if="modalMode === 'create'">
                <div class="pt-1">
                  <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
                    Accès portail client
                  </p>
                  <p class="text-xs mt-0.5" style="color:var(--app-text-faint)">
                    Identifiants de connexion à l'espace client
                  </p>
                </div>

                <div class="grid grid-cols-1 gap-3">
                  <div>
                    <label class="f-label">Email de connexion *</label>
                    <input
                      v-model="form.client_email"
                      class="f-input"
                      type="email"
                      required
                      placeholder="contact@westodyssee.com"
                    />
                  </div>

                  <div>
                    <div class="flex items-center justify-between mb-1">
                      <label class="f-label mb-0">Mot de passe</label>
                      <button type="button" class="text-xs underline" style="color:#c8a96e" @click="generateSuggestedPassword">
                        Générer automatiquement
                      </button>
                    </div>
                    <input
                      v-model="form.client_password"
                      class="f-input font-mono"
                      type="text"
                      placeholder="Laisser vide pour générer automatiquement"
                      minlength="8"
                    />
                    <p class="text-[10px] mt-1" style="color:var(--app-text-faint)">
                      Minimum 8 caractères. Si laissé vide, un mot de passe sera généré
                      et affiché après la création du client. Le client sera invité à le
                      changer à sa première connexion.
                    </p>
                  </div>
                </div>
              </template>

              <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

              <div class="flex gap-3 justify-end pt-1">
                <button type="button" class="btn btn-outline btn-md" @click="showModal = false">
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

    <!-- ── Generated-password reveal modal ───────────────── -->
    <PasswordRevealModal
      :show="showPasswordModal"
      :password="revealedPassword"
      :client-name="revealedClientName"
      @close="showPasswordModal = false"
    />

  </div>
</template>
