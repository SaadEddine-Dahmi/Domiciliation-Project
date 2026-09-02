<!-- pages/admin/settings.vue -->
<script setup lang="ts">
// pages/admin/settings.vue
//
// Shared account-settings page for the two "internal" roles that use the
// /admin/* section: Domiciliataire and Super Admin. Both reach this same
// route, but a Super Admin does not run a domiciliation company, so the
// tab list is role-aware and only the tabs that apply to the current
// user are shown.
//
//   Profil            → photo, nom/prenom/telephone          (both roles)
//   Société & contrat → company identity, RC/IF/TP, representative
//                        contact, contract_title, adresses    (domiciliataire only)
//   Sécurité          → password change                      (both roles)
//   Historique        → recent activity feed (with per-field before/after
//                        diffs) + full JSON/HTML export       (domiciliataire only —
//                        it tracks changes to a domiciliataire's own
//                        clients/representatives/contracts, which a
//                        Super Admin does not have)
//
// Backend endpoints used:
//   GET/PUT     /api/profile
//   POST/DELETE /api/profile/photo
//   PUT         /api/account/password
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
    const raw = localStorage.getItem((useRuntimeConfig().public.authStorageKey as string) ?? 'app_auth')
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
// Tab navigation — role-aware
// ══════════════════════════════════════════════════════════════

type TabId = 'profil' | 'societe' | 'securite' | 'historique'

/** Tabs that only make sense for a Domiciliataire (they describe a
 *  company: RC/IF/TP, legal representative, contract title, and the
 *  activity log of that company's own clients/contracts). A Super Admin
 *  has none of that, so those two tabs are simply not rendered for them
 *  — not just hidden, never mounted, so their fetch calls never fire. */
const tabs = computed<{ id: TabId; label: string }[]>(() => {
  const base: { id: TabId; label: string }[] = [
    { id: 'profil', label: 'Profil' },
  ]
  if (!auth.isAdmin) {
    base.push({ id: 'societe', label: 'Société & contrat' })
  }
  base.push({ id: 'securite', label: 'Sécurité' })
  if (!auth.isAdmin) {
    base.push({ id: 'historique', label: 'Historique & export' })
  }
  return base
})

const activeTab = ref<TabId>('profil')

/** Flags an incomplete profile even while the user is on another tab,
 *  so the warning banner (and a dot on the "Société" tab) stays visible
 *  regardless of which section is currently open. Only applies to
 *  Domiciliataire — a Super Admin has no company profile to complete. */
const showIncompleteDot = computed(() =>
  !auth.isAdmin && !profile.profile_complete && !loadingProfile.value
)

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
    // The "siège social" placeholder row only matters for a Domiciliataire's
    // address list — a Super Admin never sees or edits the addresses field.
    if (!auth.isAdmin && !profile.adresses.length) {
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
    // Super Admin only ever edits their personal identity — sending the
    // entreprise fields for a role that has no company would either be
    // silently ignored server-side or, worse, write stray empty values
    // over data that isn't theirs. Keep the payload role-scoped.
    const body = auth.isAdmin
      ? {
          nom: profile.nom,
          prenom: profile.prenom,
          telephone: profile.telephone,
        }
      : {
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
        }

    const res = await $fetch<{ success: boolean; profile_complete: boolean }>(
      `${getApiBase()}/api/profile`,
      { method: 'PUT', headers: authHeaders(), body }
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
const profilePhotoVersion = ref(0)

const profilePhotoUrl = computed(() => {
  if (!profile.photo_url) return null
  if (!profilePhotoVersion.value) return profile.photo_url

  const separator = profile.photo_url.includes('?') ? '&' : '?'
  return `${profile.photo_url}${separator}_pv=${profilePhotoVersion.value}`
})

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
    profilePhotoVersion.value++
    auth.setPhoto(res.data.photo_url)
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
    profilePhotoVersion.value++
    auth.setPhoto(null)
    success('Photo de profil supprimée')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur lors de la suppression')
  }
}

// ══════════════════════════════════════════════════════════════
// 2. Sécurité — password change
//
// SECURITY FIX: this previously called setTimeout() and faked a
// success toast without ever contacting the backend — any input in
// "Mot de passe actuel" was silently accepted and nothing changed in
// the database. Now calls the real PUT /api/account/password endpoint
// (AuthController::changePassword), which verifies current_password
// with Hash::check() server-side before allowing the update.
// ══════════════════════════════════════════════════════════════

const pw = reactive({ current: '', new_: '', confirm: '' })
const pwError = ref('')
const updatingPw = ref(false)

async function submitPasswordUpdate(): Promise<void> {
  pwError.value = ''
  if (!pw.current) { pwError.value = 'Renseignez votre mot de passe actuel'; return }
  if (pw.new_.length < 8) { pwError.value = 'Le nouveau mot de passe doit contenir au moins 8 caractères'; return }
  if (pw.new_ !== pw.confirm) { pwError.value = 'La confirmation ne correspond pas au nouveau mot de passe'; return }

  updatingPw.value = true
  try {
    await $fetch(`${getApiBase()}/api/account/password`, {
      method: 'PUT',
      headers: authHeaders(),
      body: {
        current_password: pw.current,
        // Laravel's 'confirmed' rule checks 'password' against a field
        // literally named 'password_confirmation' — not 'confirm'.
        password: pw.new_,
        password_confirmation: pw.confirm,
      },
    })

    success('Mot de passe mis à jour')
    auth.clearMustChangePassword()
    pw.current = ''; pw.new_ = ''; pw.confirm = ''
  } catch (e: any) {
    const msg =
      e?.data?.errors?.current_password?.[0] ??
      e?.data?.errors?.password?.[0] ??
      e?.data?.message ??
      'Erreur lors de la mise à jour du mot de passe'
    pwError.value = msg
    toastError?.(msg)
  } finally {
    updatingPw.value = false
  }
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

function filenameFromContentDisposition(header: string | null, fallback: string): string {
  if (!header) return fallback
  const utf = header.match(/filename\*\s*=\s*UTF-8''([^;]+)/i)
  if (utf?.[1]) return decodeURIComponent(utf[1])
  const ascii = header.match(/filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i)
  return (ascii?.[1] || ascii?.[2] || fallback).trim().replace(/^"|"$/g, '')
}

const exportingJson = ref(false)
const exportingHtml = ref(false)

async function requestDataExport(format: 'json' | 'html'): Promise<void> {
  const busyRef = format === 'json' ? exportingJson : exportingHtml
  busyRef.value = true
  try {
    const url = `${getApiBase()}/api/account/history/export?format=${format}`
    const response = await $fetch.raw<Blob>(url, {
      headers: authHeaders(),
      responseType: 'blob',
    })
    const blob = response._data ?? new Blob()
    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = filenameFromContentDisposition(
      response.headers.get('content-disposition'),
      `historique-modifications.${format}`,
    )
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
  // The activity log tracks a Domiciliataire's own clients/representants/
  // contrats — a Super Admin has none of those, and the "Historique" tab
  // isn't even rendered for them (see `tabs` above), so skip the call.
  if (!auth.isAdmin) fetchActivity()
})
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-3xl">

    <div>
      <h1 class="font-serif text-2xl">Paramètres <em class="text-gold italic">du compte</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Gérez vos préférences et informations</p>
    </div>

    <!-- ══════════ Incomplete profile notice — Domiciliataire only,
         `showIncompleteDot` already excludes Super Admin ══════════ -->
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

    <!-- ══════════ Tab bar — `tabs` is role-aware; a Super Admin only
         ever sees Profil + Sécurité ═══════════════════════════════ -->
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

      <!-- ══════════ TAB: Profil — shown to every role ═══════════ -->
      <div v-show="activeTab === 'profil'" class="card p-6">
        <div class="flex items-center gap-4">
          <div class="relative shrink-0">
            <div
              class="w-16 h-16 rounded-2xl flex items-center justify-center font-bold text-lg overflow-hidden"
              style="background: rgba(200,169,110,0.15); color: #c8a96e"
            >
              <img v-if="profilePhotoUrl" :key="profilePhotoUrl" :src="profilePhotoUrl" class="w-full h-full object-cover" alt="Photo de profil" />
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
                {{ auth.user?.role ?? 'Utilisateur' }}
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

      <!-- ══════════ TAB: Société & contrat — never rendered for
           Super Admin, since `tabs` excludes it above ═══════════ -->
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
          <input v-model="profile.contract_title" class="f-input" placeholder="ex. Contrat de Domiciliation" />
        </div>

        <!-- <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- <div>
            <label class="f-label">Représentant légal</label>
            <input v-model="profile.representant_legal" class="f-input" placeholder="Nom du représentant" />
          </div> -->
          <!-- <div>
            <label class="f-label">Qualité du représentant</label>
            <input v-model="profile.identite_representant" class="f-input" placeholder="ex. Gérant" />
          </div> -->
          <!-- <div>
            <label class="f-label">Email du représentant</label>
            <input v-model="profile.representant_email" class="f-input" type="email" />
          </div> -->
          <!-- <div>
            <label class="f-label">Téléphone du représentant</label>
            <input v-model="profile.representant_telephone" class="f-input" />
          </div> 
        </div> -->

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

      <!-- ══════════ TAB: Sécurité — shown to every role ══════════ -->
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

      <!-- ══════════ TAB: Historique & export — never rendered for
           Super Admin, since `tabs` excludes it above ══════════════ -->
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
