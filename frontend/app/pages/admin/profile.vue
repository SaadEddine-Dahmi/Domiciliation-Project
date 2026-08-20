<!-- pages/admin/profile.vue -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import type { Address } from '~/components/AddressListEditor.vue'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()
const { success, error: toastError } = useToast()

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

interface Address {
  label: string
  value: string
}

const loading = ref(true)
const saving  = ref(false)

const form = reactive({
  nom:                     '',
  prenom:                  '',
  telephone:               '',
  nom_societe:             '',
  representant_legal:      '',
  identite_representant:   '',

  // FIX: these two fields exist on the backend (domiciliataire_profiles
  // .representant_email / .representant_telephone) but were missing from
  // this form entirely — so they were never populated on load and never
  // sent on save, even if a matching input existed somewhere in the DOM.
  representant_email:      '',
  representant_telephone:  '',

  rc:                      '',
  if_fiscal:               '',
  tp:                      '',
})

const adresses = ref<Address[]>([])

function addAddress(): void    { adresses.value.push({ label: '', value: '' }) }
function removeAddress(i: number): void { adresses.value.splice(i, 1) }

function moveUp(i: number): void {
  if (i === 0) return
  const arr = adresses.value;
  [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]]
}

function moveDown(i: number): void {
  if (i === adresses.value.length - 1) return
  const arr = adresses.value;
  [arr[i], arr[i + 1]] = [arr[i + 1], arr[i]]
}

async function fetchProfile(): Promise<void> {
  loading.value = true
  try {
    const res = await $fetch<{ success: boolean; data: any }>(
      `${getApiBase()}/api/profile`,
      { headers: authHeaders() }
    )
    const d = res.data
    Object.assign(form, {
      nom:                    d.nom                    ?? '',
      prenom:                 d.prenom                 ?? '',
      telephone:              d.telephone               ?? '',
      nom_societe:            d.nom_societe             ?? '',
      representant_legal:     d.representant_legal      ?? '',
      identite_representant:  d.identite_representant   ?? '',

      // FIX: previously missing — the API already returns these two keys
      // (see DomiciliataireProfileController::show()), they just weren't
      // being read into the form.
      representant_email:     d.representant_email      ?? '',
      representant_telephone: d.representant_telephone  ?? '',

      rc:                     d.rc                      ?? '',
      if_fiscal:              d.if_fiscal                ?? '',
      tp:                     d.tp                       ?? '',
    })
    adresses.value = Array.isArray(d.adresses)
      ? d.adresses.map((a: any) => ({ label: a.label ?? '', value: a.value ?? '' }))
      : []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement profil')
  } finally {
    loading.value = false
  }
}

async function saveProfile(): Promise<void> {
  const validAdresses = adresses.value.filter(a => a.label.trim() !== '' && a.value.trim() !== '')
  saving.value = true
  try {
    await $fetch(`${getApiBase()}/api/profile`, {
      method: 'PUT', headers: authHeaders(),
      body: { ...form, adresses: validAdresses },
    })
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

const requiredFields = computed(() => [
  { label: 'Nom de la société',     filled: !!form.nom_societe },
  { label: 'Représentant légal',    filled: !!form.representant_legal },
  { label: 'Identité représentant', filled: !!form.identite_representant },
  { label: 'RC',                    filled: !!form.rc },
  { label: 'IF',                    filled: !!form.if_fiscal },
  { label: 'Au moins une adresse',  filled: adresses.value.length > 0 },
])

const completionPct = computed(() => {
  const filled = requiredFields.value.filter(f => f.filled).length
  return Math.round((filled / requiredFields.value.length) * 100)
})

const completionColor = computed(() => {
  if (completionPct.value === 100) return '#22c55e'
  if (completionPct.value >= 60)  return '#c8a96e'
  return '#ef4444'
})

onMounted(fetchProfile)
</script>

<template>
  <div class="space-y-6 animate-fade-up max-w-3xl">
<ProfilePhotoUpload />
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

    <!-- Completion card -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <p class="font-semibold text-sm" style="color:var(--app-text)">Profil complété à {{ completionPct }}%</p>
          <p class="text-xs mt-0.5" style="color:var(--app-text-muted)">
            {{ completionPct === 100
              ? '✓ Autofill activé — vos contrats se remplissent automatiquement'
              : "Complétez pour activer l'autofill dans les contrats" }}
          </p>
        </div>
        <span class="text-2xl font-serif font-bold" :style="`color:${completionColor}`">{{ completionPct }}%</span>
      </div>
      <div class="h-2 rounded-full overflow-hidden mb-4" style="background:var(--app-surface-2)">
        <div class="h-full rounded-full transition-all duration-700"
             :style="`width:${completionPct}%;background:${completionColor}`"/>
      </div>
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

    <div v-if="loading" class="space-y-4">
      <div class="card p-6 animate-pulse space-y-4">
        <div v-for="i in 5" :key="i" class="h-10 rounded-xl" style="background:var(--app-border)"/>
      </div>
    </div>

    <template v-else>

      <!-- Company identity -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-1 h-5 rounded-full shrink-0" style="background:#c8a96e"/>
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
            Identité de l'entreprise de domiciliation
          </p>
        </div>
        <p class="text-xs" style="color:var(--app-text-faint)">Partie "Domiciliataire" dans vos contrats PDF</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="f-label">Nom de la société *</label>
            <input v-model="form.nom_societe" class="f-input" placeholder="Nom de votre société" />
          </div>
          <div>
            <label class="f-label">Représentant légal *</label>
            <input v-model="form.representant_legal" class="f-input" placeholder="Nom complet du gérant" />
          </div>
          <div>
            <label class="f-label">Identité du représentant (CIN / Passeport) *</label>
            <input v-model="form.identite_representant" class="f-input" placeholder="BJ422176" />
          </div>

          <!-- FIX: previously missing inputs — representant_email and
               representant_telephone now bound to the form and sent/loaded
               correctly with the rest of the profile payload. -->
          <div>
            <label class="f-label">
              Email du représentant
              <span class="ml-1 text-[10px]" style="color:var(--app-text-faint)">→ affiché dans le contrat</span>
            </label>
            <input v-model="form.representant_email" type="email" class="f-input" placeholder="representant@societe.ma" />
          </div>
          <div>
            <label class="f-label">Téléphone du représentant</label>
            <input v-model="form.representant_telephone" class="f-input" placeholder="+212 6XX XXX XXX" />
          </div>

          <div>
            <label class="f-label">
              Registre du Commerce (RC)
              <span class="ml-1 text-[10px]" style="color:var(--app-text-faint)">→ rempli auto dans PDF</span>
            </label>
            <input v-model="form.rc" class="f-input" placeholder="123456" />
          </div>
          <div>
            <label class="f-label">Identifiant Fiscal (IF)</label>
            <input v-model="form.if_fiscal" class="f-input" placeholder="45678901" />
          </div>
          <div>
            <label class="f-label">Taxe Professionnelle (TP)</label>
            <input v-model="form.tp" class="f-input" placeholder="35123456" />
          </div>
        </div>
      </div>

      <!-- Contact -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-1 h-5 rounded-full shrink-0" style="background:#c8a96e"/>
          <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Informations de contact</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="f-label">Nom</label>
            <input v-model="form.nom" class="f-input" placeholder="Nom" maxlength="20" />
          </div>
          <div>
            <label class="f-label">Prénom</label>
            <input v-model="form.prenom" class="f-input" placeholder="Prénom" maxlength="20" />
          </div>
          <div>
            <label class="f-label">Téléphone</label>
            <input v-model="form.telephone" class="f-input" placeholder="+212 6XX XXX XXX" maxlength="13" />
          </div>
        </div>
      </div>

      <!-- Addresses -->
      <div class="card p-6 space-y-4">
        <AddressListEditor v-model="adresses" />
      </div>

      <!-- Save footer -->
      <div class="flex items-center justify-between flex-wrap gap-3 pb-6">
        <p class="text-xs" style="color:var(--app-text-faint)">
          Les adresses incomplètes (libellé ou adresse vide) sont ignorées à la sauvegarde.
        </p>
        <div class="flex gap-3">
          <button type="button" class="btn btn-outline btn-md" @click="fetchProfile">Réinitialiser</button>
          <button type="button" class="btn btn-gold btn-md" :disabled="saving" @click="saveProfile">
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