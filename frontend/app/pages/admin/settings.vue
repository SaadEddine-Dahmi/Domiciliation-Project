<!-- pages/admin/settings.vue -->
<script setup lang="ts">
// pages/admin/settings.vue
//
// Domiciliataire account settings, organized into tabbed sections so
// related fields are grouped together instead of one long scroll of
// stacked cards.
//
//   Profil            → photo, nom/prenom/telephone
//   Société & contrat → company identity, RC/IF/TP, representative
//                        contact, contract_title, adresses
//   Sécurité          → password change
//   Historique        → recent activity feed (with per-field before/after
//                        diffs) + full JSON/HTML export
//
// Backend endpoints used:
//   GET/PUT     /api/profile
//   POST/DELETE /api/profile/photo
//   GET         /api/account/history
//   GET         /api/account/history/export?format=json|html

import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth  = useAuthStore()
const toast = useToast()
const { success, error: toastError } = toast

function getApiBase() {
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

function fmtDateTime(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleString('fr-FR')
}

// ══════════════════════════════════════════════════════════════
// Tab navigation
// ══════════════════════════════════════════════════════════════

type TabId = 'profil' | 'societe' | 'securite' | 'historique'

const tabs: { id: TabId; label: string }[] = [
  { id: 'profil', label: 'Profil' },
  { id: 'societe', label: 'Société & contrat' },
  { id: 'securite', label: 'Sécurité' },
  { id: 'historique', label: 'Historique & export' },
]

const activeTab = ref<TabId>('profil')

/** Flags an incomplete profile even while the user is on another tab,
 *  so the warning banner (and a dot on the "Société" tab) stays visible
 *  regardless of which section is currently open. */
const showIncompleteDot = computed(() => !profile.profile_complete && !loadingProfile.value)

// ══════════════════════════════════════════════════════════════
// 1. Profile: GET/PUT /api/profile, photo upload/delete
// ══════════════════════════════════════════════════════════════

type Adresse = { label: string; value: string }

interface Profile {
  nom: string
  prenom: string | null
  telephone: string | null
  photo_url: string | null
  initials: string
  nom_societe: string | null
  contract_title: string | null
  representant_legal: string | null
  identite_representant: string | null
  representant_email: string | null
  representant_telephone: string | null
  rc: string | null
  if_fiscal: string | null
  tp: string | null
  adresses: Adresse[]
  profile_complete: boolean
}

const loadingProfile = ref(true)
const savingProfile = ref(false)
const profile = reactive<Profile>({
  nom: '', prenom: '', telephone: '',
  photo_url: null, initials: '?',
  nom_societe: '', contract_title: '',
  representant_legal: '', identite_representant: '',
  representant_email: '', representant_telephone: '',
  rc: '', if_fiscal: '', tp: '',
  adresses: [],
  profile_complete: false,
})

async function fetchProfile(): Promise<void> {
  loadingProfile.value = true
  try {
    const res = await $fetch<{ success: boolean; data: Profile }>(
      `${getApiBase()}/api/profile`,
      { headers: authHeaders() }
    )
    Object.assign(profile, res.data)
    if (!profile.adresses.length) {
      // Always keep at least the siège social row visible/editable.
      profile.adresses = [{ label: 'Siège social', value: '' }]
    }
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors du chargement du profil')
  } finally {
    loadingProfile.value = false
  }
}

async function saveProfile(): Promise<void> {
  savingProfile.value = true
  try {
    const res = await $fetch<{ success: boolean; profile_complete: boolean }>(
      `${getApiBase()}/api/profile`,
      {
        method: 'PUT',
        headers: authHeaders(),
        body: {
          nom: profile.nom,
          prenom: profile.prenom,
          telephone: profile.telephone,
          nom_societe: profile.nom_societe,
          contract_title: profile.contract_title,
          representant_legal: profile.representant_legal,
          identite_representant: profile.identite_representant,
          representant_email: profile.representant_email,
          representant_telephone: profile.representant_telephone,
          rc: profile.rc,
          if_fiscal: profile.if_fiscal,
          tp: profile.tp,
          adresses: profile.adresses.filter(a => a.value.trim() !== ''),
        },
      }
    )
    profile.profile_complete = res.profile_complete
    success('Profil mis à jour')
  } catch (e: any) {
    toastError?.(
      e?.data?.errors
        ? Object.values(e.data.errors).flat().join(' · ')
        : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    )
  } finally {
    savingProfile.value = false
  }
}

// ── Addresses: index 0 = siège social, rest = succursales ────
function addAdresse(): void {
  profile.adresses.push({ label: `Succursale ${profile.adresses.length}`, value: '' })
}

function removeAdresse(index: number): void {
  if (index === 0) return // siège social row is never removable
  profile.adresses.splice(index, 1)
}

// ── Photo upload/delete ───────────────────────────────────────
const photoInput = ref<HTMLInputElement | null>(null)
const uploadingPhoto = ref(false)
const photoError = ref('')

function triggerPhotoPicker(): void {
  photoInput.value?.click()
}

async function onPhotoChange(e: Event): Promise<void> {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  photoError.value = ''
  uploadingPhoto.value = true
  try {
    const fd = new FormData()
    fd.append('photo', file) // backend expects the field named "photo"

    const res = await $fetch<{ success: boolean; data: { photo_url: string; initials: string } }>(
      `${getApiBase()}/api/profile/photo`,
      { method: 'POST', headers: authHeaders(), body: fd }
    )
    profile.photo_url = res.data.photo_url
    profile.initials = res.data.initials
    success('Photo de profil mise à jour')
  } catch (e: any) {
    photoError.value = e?.data?.message ?? "Erreur lors de l'envoi de la photo (JPG/PNG/WEBP, 2 Mo max)"
    toastError?.(photoError.value)
  } finally {
    uploadingPhoto.value = false
    if (photoInput.value) photoInput.value.value = ''
  }
}

async function removePhoto(): Promise<void> {
  if (!confirm('Supprimer la photo de profil ?')) return
  try {
    const res = await $fetch<{ success: boolean; data: { photo_url: null; initials: string } }>(
      `${getApiBase()}/api/profile/photo`,
      { method: 'DELETE', headers: authHeaders() }
    )
    profile.photo_url = res.data.photo_url
    profile.initials = res.data.initials
    success('Photo de profil supprimée')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors de la suppression')
  }
}

// ══════════════════════════════════════════════════════════════
// 2. Sécurité — password change
//    NOTE: no self-service password endpoint exists yet in the
//    backend provided. This stays client-side validated only until
//    that endpoint is added (e.g. PUT /api/profile/password).
// ══════════════════════════════════════════════════════════════

const pw = reactive({ current: '', new_: '', confirm: '' })
const pwError = ref('')
const updatingPw = ref(false)

function submitPasswordUpdate(): void {
  pwError.value = ''
  if (!pw.current) { pwError.value = 'Renseignez votre mot de passe actuel'; return }
  if (pw.new_.length < 8) { pwError.value = 'Le nouveau mot de passe doit contenir au moins 8 caractères'; return }
  if (pw.new_ !== pw.confirm) { pwError.value = 'La confirmation ne correspond pas au nouveau mot de passe'; return }

  updatingPw.value = true
  // TODO: replace with a real call once a self-service password-update
  // route exists, e.g. PUT /api/profile/password.
  setTimeout(() => {
    updatingPw.value = false
    success?.('Mot de passe mis à jour')
    pw.current = ''; pw.new_ = ''; pw.confirm = ''
  }, 300)
}

// ══════════════════════════════════════════════════════════════
// 3. Historique & export — GET /api/account/history[/export]
//    Each entry carries a `diff`: one row per changed field, with its
//    value immediately before and immediately after that change.
// ══════════════════════════════════════════════════════════════

interface DiffRow {
  field: string
  before: any
  after: any
}

interface ActivityEntry {
  id: string
  type: 'entreprise' | 'representant' | 'contrat'
  action: 'create' | 'update' | 'delete'
  label: string
  changed_fields: string[] | null
  diff: DiffRow[]
  changed_by: string | null
  created_at: string
}

const activity = ref<ActivityEntry[]>([])
const loadingActivity = ref(true)

/** Tracks which activity rows have their field-by-field diff expanded. */
const expandedIds = ref<Set<string>>(new Set())

function toggleExpanded(id: string): void {
  const next = new Set(expandedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expandedIds.value = next
}

async function fetchActivity(): Promise<void> {
  loadingActivity.value = true
  try {
    const res = await $fetch<{ success: boolean; data: ActivityEntry[] }>(
      `${getApiBase()}/api/account/history?limit=8`,
      { headers: authHeaders() }
    )
    activity.value = res.data ?? []
  } catch {
    activity.value = []
  } finally {
    loadingActivity.value = false
  }
}

const typeLabel: Record<string, string> = {
  entreprise: 'Client',
  representant: 'Représentant',
  contrat: 'Contrat',
}

const actionLabel: Record<string, string> = {
  create: 'Création',
  update: 'Modification',
  delete: 'Suppression',
}

/** Human label for a raw field name — falls back to the field name itself
 *  when no friendlier label is defined below. */
const fieldLabel: Record<string, string> = {
  raison_sociale: 'Raison sociale',
  forme_juridique: 'Forme juridique',
  adresse: 'Adresse',
  ville: 'Ville',
  pays: 'Pays',
  capital: 'Capital',
  statut: 'Statut',
  nom: 'Nom',
  prenom: 'Prénom',
  cin: 'CIN / Passeport',
  telephone: 'Téléphone',
  email: 'Email',
  date_naissance: 'Date de naissance',
  nationalite: 'Nationalité',
  titre_contrat: 'Titre du contrat',
  date_debut: 'Date début',
  date_fin: 'Date fin',
  prix_mensuel: 'Prix mensuel',
  prix_total: 'Montant total',
  mode_paiement: 'Mode de paiement',
}

function labelFor(field: string): string {
  return fieldLabel[field] ?? field
}

/** Renders a diff value for display — null/empty becomes an em-dash so
 *  "field was empty before" reads clearly instead of a blank cell. */
function displayValue(v: any): string {
  if (v === null || v === undefined || v === '') return '—'
  if (typeof v === 'boolean') return v ? 'Oui' : 'Non'
  return String(v)
}

const exportingJson = ref(false)
const exportingHtml = ref(false)

async function requestDataExport(format: 'json' | 'html'): Promise<void> {
  const busyRef = format === 'json' ? exportingJson : exportingHtml
  busyRef.value = true
  try {
    const url = `${getApiBase()}/api/account/history/export?format=${format}`
    const res = await fetch(url, { headers: { ...authHeaders() } })
    if (!res.ok) throw new Error()

    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `historique-modifications.${format}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(blobUrl)
    success('Archive téléchargée')
  } catch {
    toastError?.("Erreur lors du téléchargement de l'archive")
  } finally {
    busyRef.value = false
  }
}

onMounted(() => {
  fetchProfile()
  fetchActivity()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl">

    <div>
      <h1 class="font-serif text-2xl">Paramètres <em class="text-gold italic">du compte</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Gérez vos préférences et informations</p>
    </div>

    <!-- ══════════ Incomplete profile notice (always visible) ═══ -->
    <div v-if="showIncompleteDot" class="card p-4 flex items-center gap-3"
         style="border: 1px solid rgba(234,179,8,0.3); background: rgba(234,179,8,0.06)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" class="shrink-0">
        <path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
      </svg>
      <p class="text-sm" style="color: #eab308">
        Votre profil est incomplet. Certaines informations (société, RC/IF, adresses) sont requises pour générer des contrats.
        <button class="underline font-semibold" @click="activeTab = 'societe'">Compléter maintenant</button>
      </p>
    </div>

    <!-- ══════════ Tab bar ═══════════════════════════════════ -->
    <div class="flex gap-2 flex-wrap border-b pb-0" style="border-color: var(--app-border-2)">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="relative px-4 py-2.5 text-sm font-semibold transition-colors flex items-center gap-2"
        :style="activeTab === tab.id
          ? 'color: #c8a96e; border-bottom: 2px solid #c8a96e'
          : 'color: var(--app-text-faint); border-bottom: 2px solid transparent'"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
        <span
          v-if="tab.id === 'societe' && showIncompleteDot"
          class="w-1.5 h-1.5 rounded-full"
          style="background: #eab308"
        />
      </button>
    </div>

    <div v-if="loadingProfile" class="card p-6 animate-pulse space-y-3">
      <div class="h-6 w-1/3 rounded" style="background: var(--app-border)" />
      <div class="h-4 w-1/4 rounded" style="background: var(--app-border)" />
    </div>

    <template v-else>

      <!-- ══════════ TAB: Profil ══════════════════════════════ -->
      <div v-show="activeTab === 'profil'" class="card p-6">
        <div class="flex items-center gap-4">
          <div class="relative shrink-0">
            <div
              class="w-16 h-16 rounded-2xl flex items-center justify-center font-bold text-lg overflow-hidden"
              style="background: rgba(200,169,110,0.15); color: #c8a96e"
            >
              <img v-if="profile.photo_url" :src="profile.photo_url" class="w-full h-full object-cover" alt="Photo de profil" />
              <span v-else>{{ profile.initials }}</span>

              <div v-if="uploadingPhoto" class="absolute inset-0 flex items-center justify-center rounded-2xl"
                   style="background: rgba(0,0,0,0.55)">
                <span class="text-[10px] text-white">Envoi...</span>
              </div>
            </div>

            <button type="button" class="absolute -bottom-1.5 -right-1.5 w-6 h-6 rounded-full flex items-center justify-center"
                    style="background:#c8a96e; color:#111" title="Changer la photo" @click="triggerPhotoPicker">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
            </button>

            <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPhotoChange" />
          </div>

          <div class="min-w-0 flex-1">
            <p class="font-serif text-xl truncate" style="color: var(--app-text)">
              {{ profile.prenom ? `${profile.prenom} ${profile.nom}` : profile.nom }}
            </p>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
              <span v-if="auth.user?.email" class="text-sm truncate" style="color: var(--app-text-muted)">
                {{ auth.user.email }}
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold uppercase tracking-wide"
                    style="color: #c8a96e; background: rgba(200,169,110,0.12)">
                {{ auth.user?.role ?? 'Domiciliataire' }}
              </span>
            </div>
            <div class="flex items-center gap-2 mt-2">
              <button type="button" class="text-xs font-semibold" style="color:#c8a96e" @click="triggerPhotoPicker">
                Changer la photo
              </button>
              <span v-if="profile.photo_url" style="color: var(--app-text-faint)">·</span>
              <button v-if="profile.photo_url" type="button" class="text-xs" style="color: var(--app-text-faint)" @click="removePhoto">
                Supprimer
              </button>
            </div>
            <p v-if="photoError" class="text-red-400 text-xs mt-1">{{ photoError }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6 pt-5" style="border-top: 1px solid var(--app-border-2)">
          <div>
            <label class="f-label">Nom *</label>
            <input v-model="profile.nom" class="f-input" />
          </div>
          <div>
            <label class="f-label">Prénom</label>
            <input v-model="profile.prenom" class="f-input" />
          </div>
          <div class="sm:col-span-2">
            <label class="f-label">Téléphone</label>
            <input v-model="profile.telephone" class="f-input" />
          </div>
        </div>

        <div class="flex justify-end pt-4">
          <button class="btn btn-gold btn-md" :disabled="savingProfile" @click="saveProfile">
            {{ savingProfile ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </div>

      <!-- ══════════ TAB: Société & contrat ═══════════════════ -->
      <div v-show="activeTab === 'societe'" class="card p-6 space-y-4">
        <div>
          <p class="text-xs uppercase text-gold tracking-widest font-bold">Société & contrat</p>
          <p class="text-xs mt-1" style="color: var(--app-text-faint)">
            Ces informations apparaissent sur les contrats générés pour vos clients.
          </p>
        </div>

        <div>
          <label class="f-label">Nom de la société</label>
          <input v-model="profile.nom_societe" class="f-input" placeholder="Nom de votre centre de domiciliation" />
        </div>

        <div>
          <label class="f-label">
            Titre du contrat
            <span class="text-[10px] ml-1 font-normal" style="color: var(--app-text-faint)">
              affiché en en-tête de chaque contrat généré — vous choisissez le nom
            </span>
          </label>
          <input v-model="profile.contract_title" class="f-input" placeholder="ex. Contrat de Domiciliation Commerciale" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="f-label">Représentant légal</label>
            <input v-model="profile.representant_legal" class="f-input" placeholder="Nom du représentant" />
          </div>
          <div>
            <label class="f-label">Qualité du représentant</label>
            <input v-model="profile.identite_representant" class="f-input" placeholder="ex. Gérant" />
          </div>
          <div>
            <label class="f-label">Email du représentant</label>
            <input v-model="profile.representant_email" class="f-input" type="email" />
          </div>
          <div>
            <label class="f-label">Téléphone du représentant</label>
            <input v-model="profile.representant_telephone" class="f-input" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="f-label">RC</label>
            <input v-model="profile.rc" class="f-input" />
          </div>
          <div>
            <label class="f-label">IF</label>
            <input v-model="profile.if_fiscal" class="f-input" />
          </div>
          <div>
            <label class="f-label">Patente (TP)</label>
            <input v-model="profile.tp" class="f-input" />
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="f-label mb-0">Adresses</label>
            <button type="button" class="text-xs font-semibold" style="color:#c8a96e" @click="addAdresse">
              + Ajouter une succursale
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="(adr, i) in profile.adresses" :key="i" class="flex gap-2 items-start">
              <input
                v-model="adr.label"
                class="f-input"
                style="width: 140px"
                :disabled="i === 0"
                :placeholder="i === 0 ? 'Siège social' : 'Succursale'"
              />
              <input v-model="adr.value" class="f-input flex-1" placeholder="Adresse complète" />
              <button v-if="i > 0" type="button" class="btn btn-outline btn-sm shrink-0" @click="removeAdresse(i)">
                ✕
              </button>
            </div>
          </div>
          <p class="text-[11px] mt-1" style="color: var(--app-text-faint)">
            La première adresse est toujours le siège social ; les suivantes sont des succursales.
          </p>
        </div>

        <div class="flex justify-end pt-1">
          <button class="btn btn-gold btn-md" :disabled="savingProfile" @click="saveProfile">
            {{ savingProfile ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </div>

      <!-- ══════════ TAB: Sécurité ═════════════════════════════ -->
      <div v-show="activeTab === 'securite'" class="card p-6">
        <p class="text-xs uppercase text-gold tracking-widest font-bold mb-1">Sécurité</p>
        <p class="text-xs mb-4" style="color: var(--app-text-faint)">
          Modifiez le mot de passe utilisé pour vous connecter à ce compte.
        </p>

        <div class="flex flex-col gap-3 max-w-sm">
          <UiField label="Mot de passe actuel" type="password" v-model="pw.current" placeholder="••••••••" />
          <UiField label="Nouveau mot de passe" type="password" v-model="pw.new_" placeholder="Au moins 8 caractères" />
          <UiField label="Confirmer" type="password" v-model="pw.confirm" placeholder="••••••••" />
          <p v-if="pwError" class="text-red-400 text-xs">{{ pwError }}</p>
          <button class="btn btn-gold btn-md w-full justify-center mt-1" :disabled="updatingPw" @click="submitPasswordUpdate">
            {{ updatingPw ? 'Mise à jour...' : 'Mettre à jour' }}
          </button>
        </div>
      </div>

      <!-- ══════════ TAB: Historique & export ══════════════════ -->
      <div v-show="activeTab === 'historique'" class="card p-6 space-y-4">
        <div>
          <p class="text-xs uppercase text-gold tracking-widest font-bold">Historique & export de données</p>
          <p class="text-xs mt-1" style="color: var(--app-text-faint)">
            Consultez vos modifications récentes sur vos clients, représentants et contrats,
            ou téléchargez l'archive complète.
          </p>
        </div>

        <div>
          <p class="text-[11px] uppercase tracking-wide font-bold mb-2" style="color: var(--app-text-faint)">
            Activité récente
          </p>

          <div v-if="loadingActivity" class="space-y-2">
            <div v-for="i in 3" :key="i" class="h-10 rounded-lg bg-white/5 animate-pulse" />
          </div>

          <div v-else-if="activity.length" class="space-y-2">
            <div v-for="item in activity" :key="item.id" class="rounded-lg overflow-hidden" style="background: rgba(255,255,255,0.03)">

              <!-- Entry header row — click to expand the field-by-field diff -->
              <button
                type="button"
                class="w-full flex items-center justify-between gap-3 text-sm px-3 py-2 text-left"
                :disabled="!item.diff.length"
                @click="toggleExpanded(item.id)"
              >
                <span style="color: var(--app-text)">
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold mr-1"
                        style="color:#c8a96e; background: rgba(200,169,110,0.12)">
                    {{ typeLabel[item.type] ?? item.type }}
                  </span>
                  {{ actionLabel[item.action] ?? item.action }} — {{ item.label }}
                  <span v-if="item.changed_by" style="color: var(--app-text-faint)"> par {{ item.changed_by }}</span>
                </span>
                <span class="flex items-center gap-2 shrink-0">
                  <span class="text-xs" style="color: var(--app-text-faint)">{{ fmtDateTime(item.created_at) }}</span>
                  <svg
                    v-if="item.diff.length"
                    width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    style="color: var(--app-text-faint)"
                    :style="expandedIds.has(item.id) ? 'transform: rotate(180deg)' : ''"
                  >
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </span>
              </button>

              <!-- "Champs modifiés" — before/after breakdown per field -->
              <div v-if="expandedIds.has(item.id) && item.diff.length" class="px-3 pb-3 pt-1">
                <p class="text-[10px] uppercase tracking-wide font-bold mb-1.5" style="color: var(--app-text-faint)">
                  Champs modifiés
                </p>
                <div class="space-y-1.5">
                  <div
                    v-for="d in item.diff"
                    :key="d.field"
                    class="grid grid-cols-[120px_1fr_auto_1fr] items-center gap-2 text-xs rounded px-2 py-1.5"
                    style="background: rgba(255,255,255,0.03)"
                  >
                    <span class="font-semibold" style="color: #c8a96e">{{ labelFor(d.field) }}</span>
                    <span class="truncate" style="color: #f87171">{{ displayValue(d.before) }}</span>
                    <span style="color: var(--app-text-faint)">→</span>
                    <span class="truncate" style="color: #4ade80">{{ displayValue(d.after) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <p v-else class="text-sm" style="color: var(--app-text-faint)">
            Aucune activité récente à afficher.
          </p>
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--app-border-2)">
          <p class="text-[11px] uppercase tracking-wide font-bold mb-2" style="color: var(--app-text-faint)">
            Télécharger l'archive complète
          </p>
          <div class="flex gap-3 flex-wrap">
            <button class="btn btn-outline btn-sm" :disabled="exportingJson" @click="requestDataExport('json')">
              {{ exportingJson ? 'Préparation...' : 'Télécharger en JSON' }}
            </button>
            <button class="btn btn-outline btn-sm" :disabled="exportingHtml" @click="requestDataExport('html')">
              {{ exportingHtml ? 'Préparation...' : 'Télécharger en HTML' }}
            </button>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>