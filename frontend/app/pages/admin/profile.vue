<!-- ============================================================
  pages/admin/profile.vue

  Profile management page for the domiciliataire.

  WHAT THIS PAGE DOES:
    - Loads all company identity and contact fields via GET /api/profile.
    - Allows editing and saving all fields via PUT /api/profile.
    - Manages the addresses list (siège + succursales) that appear in the
      PDF footer and D'UNE PART section of every generated contract.
============================================================ -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()
const { success, error: toastError } = useToast()

// ── API helpers ───────────────────────────────────────────────────────────────

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

/**
 * Build the Authorization header from localStorage.
 * Key: 'app_auth' — written by the auth store on successful login.
 */
function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── Types ─────────────────────────────────────────────────────────────────────

/**
 * Each address entry in the adresses JSON array.
 * label: human-readable name shown in the PDF (e.g. "Siège social", "Succursale 1")
 * value: full street address string printed in the PDF
 */
interface Address {
  label: string
  value: string
}

// ── State ─────────────────────────────────────────────────────────────────────

const loading = ref(true)
const saving  = ref(false)

/**
 * All editable profile fields.
 * email is now included — was missing in the previous version.
 */
const form = reactive({
  nom:                   '',
  prenom:                '',
  email:                 '', 
  telephone:             '',
  nom_societe:           '',
  representant_legal:    '',
  identite_representant: '',   
  rc:                    '',
  if_fiscal:             '',
  tp:                    '',
})

/**
 * Address list: [{label, value}, ...]
 * Index 0 → siège principal (required for PDF)
 * Index 1+ → succursales (optional, all appear in PDF footer)
 */
const adresses = ref<Address[]>([])

// ── Address management ────────────────────────────────────────────────────────

/**
 * Add a new address entry with a pre-suggested label based on position.
 * Position 0 → "Siège social"
 * Position 1 → "Succursale 1"
 * Position 2 → "Succursale 2", etc.
 */
function addAddress(): void {
  const label = adresses.value.length === 0
    ? 'Siège social'
    : `Succursale ${adresses.value.length}`
  adresses.value.push({ label, value: '' })
}

/** Remove an address entry by index. */
function removeAddress(i: number): void {
  adresses.value.splice(i, 1)
}

/** Move an address entry up one position (swap with the one above). */
function moveUp(i: number): void {
  if (i === 0) return
  const arr = adresses.value;
  [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]]
}

/** Move an address entry down one position (swap with the one below). */
function moveDown(i: number): void {
  if (i === adresses.value.length - 1) return
  const arr = adresses.value;
  [arr[i], arr[i + 1]] = [arr[i + 1], arr[i]]
}

// ── API calls ─────────────────────────────────────────────────────────────────

/**
 * Load the profile from GET /api/profile and populate the form.
 * Called on mount and also when the user clicks "Réinitialiser".
 */
async function fetchProfile(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/profile`,
      { headers: authHeaders() }
    )
    const d = res.data

    // Populate all form fields from the API response.
    Object.assign(form, {
      nom:                   d.nom                   ?? '',
      prenom:                d.prenom                ?? '',
      email:                 d.email                 ?? '',
      telephone:             d.telephone             ?? '',
      nom_societe:           d.nom_societe           ?? '',
      representant_legal:    d.representant_legal    ?? '',
      identite_representant: d.identite_representant ?? '',
      rc:                    d.rc                    ?? '',
      if_fiscal:             d.if_fiscal             ?? '',
      tp:                    d.tp                    ?? '',
    })

    // Populate the address list.
    adresses.value = Array.isArray(d.adresses)
      ? d.adresses.map((a: any) => ({
          label: a.label ?? '',
          value: a.value ?? '',
        }))
      : []

  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement profil')
  } finally {
    loading.value = false
  }
}

/**
 * Save all profile fields via PUT /api/profile.
 *
 * Strips incomplete address entries (missing label or value) before saving
 * so the backend never receives malformed address objects.
 */
async function saveProfile(): Promise<void> {
  // Filter out any addresses where label or value is blank.
  const validAdresses = adresses.value.filter(
    a => a.label.trim() !== '' && a.value.trim() !== ''
  )

  saving.value = true
  try {
    await $fetch(`${getApiBase()}/api/profile`, {
      method:  'PUT',
      headers: authHeaders(),
      body:    { ...form, adresses: validAdresses },
    })

    // Update local state to match what was actually saved.
    adresses.value = validAdresses
    success('Profil enregistré avec succès ✓')

  } catch (e: any) {
    const msg = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur sauvegarde'
    toastError?.(msg)
  } finally {
    saving.value = false
  }
}

// ── Completion indicator ──────────────────────────────────────────────────────

/**
 * Fields that must be set for the autofill wizard banner to show "✓ Complet".
 * email is now included — without it, the PDF token {{domiciliataire_email}}
 * would always resolve to an empty string.
 */
const requiredFields = computed(() => [
  { label: 'Nom de la société',     filled: !!form.nom_societe },
  { label: 'Représentant légal',    filled: !!form.representant_legal },
  { label: 'Identité représentant', filled: !!form.identite_representant },
  { label: 'RC',                    filled: !!form.rc },
  { label: 'IF',                    filled: !!form.if_fiscal },
  { label: 'Email',                 filled: !!form.email },               // FIX: new
  { label: 'Au moins une adresse',  filled: adresses.value.length > 0 },
])

/** Percentage of required fields that are filled (0–100). */
const completionPct = computed(() => {
  const filled = requiredFields.value.filter(f => f.filled).length
  return Math.round((filled / requiredFields.value.length) * 100)
})

/** Progress bar and percentage colour based on completion level. */
const completionColor = computed(() => {
  if (completionPct.value === 100) return '#22c55e'   // green — complete
  if (completionPct.value >= 60)  return '#c8a96e'   // gold  — partial
  return '#ef4444'                                    // red   — incomplete
})

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(fetchProfile)
</script>

<template>
  <div class="space-y-6 animate-fade-up max-w-3xl">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <div class="flex items-start justify-between flex-wrap gap-4">
      <div>
        <h1 class="font-serif text-2xl" style="color:var(--app-text)">
          Mon <em class="italic" style="color:#c8a96e">Profil Entreprise</em>
        </h1>
        <p class="text-sm mt-1" style="color:var(--app-text-muted)">
          Ces informations s'insèrent automatiquement dans vos contrats PDF.
        </p>
      </div>
      <button class="btn btn-gold btn-md" :disabled="saving || loading" @click="saveProfile">
        <svg v-if="saving" class="animate-spin mr-1" width="14" height="14"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/>
        </svg>
        {{ saving ? 'Enregistrement...' : 'Sauvegarder' }}
      </button>
    </div>

    <!-- ── Profile completion card ─────────────────────────────────────────── -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <p class="font-semibold text-sm" style="color:var(--app-text)">
            Profil complété à {{ completionPct }}%
          </p>
          <p class="text-xs mt-0.5" style="color:var(--app-text-muted)">
            {{ completionPct === 100
              ? '✓ Autofill activé — vos contrats se remplissent automatiquement'
              : "Complétez ces champs pour activer l'autofill dans le wizard" }}
          </p>
        </div>
        <span class="text-2xl font-serif font-bold"
              :style="`color:${completionColor}`">{{ completionPct }}%</span>
      </div>

      <!-- Progress bar -->
      <div class="h-2 rounded-full overflow-hidden mb-4"
           style="background:var(--app-surface-2)">
        <div class="h-full rounded-full transition-all duration-700"
             :style="`width:${completionPct}%;background:${completionColor}`"/>
      </div>

      <!-- Field checklist -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
        <div v-for="f in requiredFields" :key="f.label"
             class="flex items-center gap-2 text-xs"
             :style="f.filled ? 'color:var(--app-text-muted)' : 'color:#ef4444'">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path v-if="f.filled" d="M20 6L9 17l-5-5"/>
            <path v-else d="M18 6L6 18M6 6l12 12"/>
          </svg>
          {{ f.label }}
        </div>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="card p-6 animate-pulse space-y-4">
        <div v-for="i in 7" :key="i" class="h-10 rounded-xl"
             style="background:var(--app-border)"/>
      </div>
    </div>

    <template v-else>

      <!-- ── Company identity ────────────────────────────────────────────────── -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-1 h-5 rounded-full shrink-0" style="background:#c8a96e"/>
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Identité de l'entreprise de domiciliation
          </p>
        </div>
        <p class="text-xs" style="color:var(--app-text-faint)">
          Ces champs apparaissent dans la section « D'une part » de chaque contrat PDF.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Company name: appears as the header of every PDF -->
          <div class="sm:col-span-2">
            <label class="f-label">Nom de la société *</label>
            <input v-model="form.nom_societe" class="f-input"
                   placeholder="Votre société de domiciliation" />
          </div>

          <!-- Legal representative: appears in D'UNE PART and signature block -->
          <div>
            <label class="f-label">Représentant légal *</label>
            <input v-model="form.representant_legal" class="f-input"
                   placeholder="Nom complet du gérant" />
          </div>

          <!-- CIN: "titulaire de la CIN N° ..." in D'UNE PART -->
          <div>
            <label class="f-label">
              CIN du représentant *
              <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">
                → affiché dans le contrat
              </span>
            </label>
            <input v-model="form.identite_representant" class="f-input"
                   placeholder="BJ422176" />
          </div>

          <!-- RC, IF, TP: appear in D'UNE PART and footer -->
          <div>
            <label class="f-label">
              Registre du Commerce (RC)
              <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">→ PDF auto</span>
            </label>
            <input v-model="form.rc" class="f-input" placeholder="56989" />
          </div>
          <div>
            <label class="f-label">Identifiant Fiscal (IF)</label>
            <input v-model="form.if_fiscal" class="f-input" placeholder="60102285" />
          </div>
          <div>
            <label class="f-label">Taxe Professionnelle (TP)</label>
            <input v-model="form.tp" class="f-input" placeholder="55004406" />
          </div>

        </div>
      </div>

      <!-- ── Contact information ─────────────────────────────────────────────── -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-1 h-5 rounded-full shrink-0" style="background:#c8a96e"/>
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Informations de contact
          </p>
        </div>
        <p class="text-xs" style="color:var(--app-text-faint)">
          Ces champs alimentent les tokens PDF
          <code style="color:#c8a96e">{{domiciliataire_email}}</code>,
          <code style="color:#c8a96e">{{domiciliataire_telephone}}</code>, etc.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="f-label">Nom</label>
            <input v-model="form.nom" class="f-input" placeholder="Nom" maxlength="20" />
          </div>
          <div>
            <label class="f-label">Prénom</label>
            <input v-model="form.prenom" class="f-input" placeholder="Prénom" maxlength="20" />
          </div>

          <!--
            EMAIL — FIX: this field was completely absent in the previous version.
            The domiciliataire's email appears:
              • In the PDF D'UNE PART section: "Email : contact@societe.ma"
              • Via the {{domiciliataire_email}} article body token
            Validated server-side with Rule::unique()->ignore(currentUser->id)
            so saving the same email doesn't trigger a uniqueness error.
          -->
          <div class="sm:col-span-2">
            <label class="f-label">
              Email *
              <span class="text-[10px] ml-1" style="color:var(--app-text-faint)">
                → affiché dans le contrat · token {{domiciliataire_email}}
              </span>
            </label>
            <input v-model="form.email" type="email" class="f-input"
                   placeholder="contact@votre-societe.ma" />
          </div>

          <div>
            <label class="f-label">Téléphone</label>
            <input v-model="form.telephone" class="f-input"
                   placeholder="+212 5XX XX XX XX" maxlength="13" />
          </div>
        </div>
      </div>

      <!-- ── Addresses — siège + succursales ────────────────────────────────── -->
      <!--
        CRITICAL FOR PDF QUALITY:
        The adresses array drives three things in the generated contract:
          1. D'UNE PART paragraph: "sise Siège social : N° 78... – Succursale 1 : APPT N°4..."
          2. D'AUTRE PART C/O line: uses the first address value
          3. Footer (every page): all addresses on line 1, then RC/IF/TP on line 2

        Structure: [{label: "Siège social", value: "N° 78 KASBAR SOUSS..."}, ...]
        Position 0 → ALWAYS the siège principal
        Position 1+ → succursales in display order
      -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-1 h-5 rounded-full shrink-0" style="background:#c8a96e"/>
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Adresses (Siège + Succursales)
          </p>
        </div>
        <p class="text-xs leading-relaxed" style="color:var(--app-text-faint)">
          La <strong style="color:var(--app-text)">première adresse</strong> est le siège
          principal. Les suivantes sont vos succursales. Elles apparaissent toutes dans
          le bas-de-page du contrat et dans la section « D'une part ».
          Exemple de libellé : <em>Siège social</em>, <em>Succursale 1</em>, <em>Succursale 2</em>.
        </p>

        <!-- Address entries list -->
        <div class="space-y-3">
          <div
            v-for="(addr, i) in adresses"
            :key="i"
            class="rounded-xl p-4 space-y-3"
            :style="`background:var(--app-surface-2);
                     border:2px solid ${i === 0 ? 'rgba(200,169,110,0.5)' : 'var(--app-border)'}`"
          >
            <!-- Entry header: badge + reorder/delete controls -->
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <span
                class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wide"
                :style="i === 0
                  ? 'background:rgba(200,169,110,0.15);color:#c8a96e'
                  : 'background:rgba(255,255,255,0.06);color:var(--app-text-faint)'"
              >
                {{ i === 0 ? '★ Siège principal' : `Succursale ${i}` }}
              </span>

              <div class="flex gap-1">
                <!-- Move up (disabled for first entry) -->
                <button
                  v-if="i > 0"
                  type="button"
                  class="w-7 h-7 rounded-lg flex items-center justify-center text-xs"
                  style="background:var(--app-surface);color:var(--app-text-faint)"
                  title="Monter"
                  @click="moveUp(i)"
                >↑</button>

                <!-- Move down (disabled for last entry) -->
                <button
                  v-if="i < adresses.length - 1"
                  type="button"
                  class="w-7 h-7 rounded-lg flex items-center justify-center text-xs"
                  style="background:var(--app-surface);color:var(--app-text-faint)"
                  title="Descendre"
                  @click="moveDown(i)"
                >↓</button>

                <!-- Delete -->
                <button
                  type="button"
                  class="w-7 h-7 rounded-lg flex items-center justify-center text-xs"
                  style="background:rgba(239,68,68,0.1);color:#ef4444"
                  title="Supprimer"
                  @click="removeAddress(i)"
                >✕</button>
              </div>
            </div>

            <!-- Label + full address fields -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-1">
                <label class="f-label text-[10px]">Libellé *</label>
                <input
                  v-model="addr.label"
                  class="f-input"
                  :placeholder="i === 0 ? 'Siège social' : `Succursale ${i}`"
                />
              </div>
              <div class="sm:col-span-2">
                <label class="f-label text-[10px]">Adresse complète *</label>
                <input
                  v-model="addr.value"
                  class="f-input"
                  :placeholder="i === 0
                    ? 'N° 78 KASBAR SOUSS KM5 BENSERGAO AGADIR'
                    : 'APPT N°4 IMM 617 AV MOHAMED EL FASSI RUE 951 HAY SALAM AGADIR'"
                />
              </div>
            </div>
          </div>

          <!-- Warning when no addresses configured -->
          <div
            v-if="adresses.length === 0"
            class="rounded-xl p-5 text-center text-sm"
            style="background:rgba(245,158,11,0.06);
                   border:2px dashed rgba(245,158,11,0.3);
                   color:#f59e0b"
          >
            ⚠ Aucune adresse — la section « D'une part » et le bas-de-page du contrat
            PDF seront incomplets.
          </div>

          <!-- Add address button -->
          <button type="button" class="btn btn-outline btn-sm" @click="addAddress">
            + {{ adresses.length === 0 ? 'Ajouter le siège social' : 'Ajouter une succursale' }}
          </button>
        </div>

        <!-- Live footer preview — shows exactly what will appear at the bottom of the PDF -->
        <div
          v-if="adresses.some(a => a.value.trim())"
          class="rounded-xl px-4 py-3 mt-2"
          style="background:var(--app-surface);border:1px solid var(--app-border)"
        >
          <p class="text-[10px] uppercase tracking-widest font-bold mb-2"
             style="color:var(--app-text-faint)">
            Aperçu bas-de-page PDF
          </p>
          <p class="text-xs leading-relaxed" style="color:var(--app-text-muted)">
            <template v-for="(addr, i) in adresses.filter(a => a.value.trim())" :key="i">
              <span v-if="i > 0"> – </span>
              <strong>{{ addr.label || (i === 0 ? 'Siège' : `Succursale ${i}`) }}</strong>
              : {{ addr.value }}
            </template>
            <br>
            <strong>{{ form.nom_societe || 'Société' }}</strong>
            — RC : <strong>{{ form.rc || '—' }}</strong>
            | IF : <strong>{{ form.if_fiscal || '—' }}</strong>
            | TP : <strong>{{ form.tp || '—' }}</strong>
          </p>
        </div>
      </div>

      <!-- ── Save footer ──────────────────────────────────────────────────────── -->
      <div class="flex items-center justify-between flex-wrap gap-3 pb-6">
        <p class="text-xs" style="color:var(--app-text-faint)">
          Les adresses avec libellé ou valeur vide sont ignorées lors de la sauvegarde.
        </p>
        <div class="flex gap-3">
          <button type="button" class="btn btn-outline btn-md" @click="fetchProfile">
            Réinitialiser
          </button>
          <button type="button" class="btn btn-gold btn-md" :disabled="saving"
                  @click="saveProfile">
            <svg v-if="saving" class="animate-spin mr-1" width="14" height="14"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v4M12 18v4"/>
            </svg>
            {{ saving ? 'Enregistrement...' : 'Sauvegarder le profil' }}
          </button>
        </div>
      </div>

    </template>
  </div>
</template>