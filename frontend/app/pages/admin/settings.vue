<!-- ============================================================
  pages/admin/parametres.vue
  Paramètres du compte domiciliataire :
  - Préférences de notifications (délais d'alerte)
  - Informations du compte
============================================================ -->
<script setup lang="ts">
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
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch { return {} }
}

// ── Préférences notification ──────────────────────────────
const selectedDelays = ref<number[]>([1])
const savingPrefs    = ref(false)
const loadingPrefs   = ref(true)

function loadDelaysFromStorage(): number[] {
  if (!import.meta.client) return [1]
  try {
    const raw = localStorage.getItem('astfisc_notif_delays')
    if (raw) return JSON.parse(raw)
  } catch {}
  return [1]
}

function saveDelaysToStorage(delays: number[]): void {
  if (!import.meta.client) return
  localStorage.setItem('astfisc_notif_delays', JSON.stringify(delays))
}

async function loadPreferences(): Promise<void> {
  selectedDelays.value = loadDelaysFromStorage()
  loadingPrefs.value = true
  try {
    const res = await $fetch<{ success: boolean; data: { delays: number[] } }>(
      `${getApiBase()}/api/notifications/preferences`,
      { headers: authHeaders() }
    )
    const dbDelays = res.data?.delays ?? []
    if (dbDelays.length > 0) {
      selectedDelays.value = dbDelays
      saveDelaysToStorage(dbDelays)
    }
  } catch {} finally {
    loadingPrefs.value = false
  }
}

function toggleDelay(months: number): void {
  if (selectedDelays.value.includes(months)) {
    if (selectedDelays.value.length === 1) return
    selectedDelays.value = selectedDelays.value.filter(d => d !== months)
  } else {
    selectedDelays.value = [...selectedDelays.value, months].sort((a, b) => a - b)
  }
  saveDelaysToStorage(selectedDelays.value)
}

async function savePreferences(): Promise<void> {
  savingPrefs.value = true
  try {
    await $fetch(`${getApiBase()}/api/notifications/preferences`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { delays: selectedDelays.value },
    })
    saveDelaysToStorage(selectedDelays.value)
    success('Préférences de notification sauvegardées ✓')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur sauvegarde')
  } finally {
    savingPrefs.value = false
  }
}




// definePageMeta({ layout: 'dashboard', middleware: 'auth' })
const toast = useToast()
const pw = reactive({ current:'', new_:'', confirm:'' })


onMounted(loadPreferences)
</script>

<template>
  <div class="space-y-6 animate-fade-up max-w-2xl">

    <div>
      <h1 class="font-serif text-2xl">Paramètres <em class="text-gold italic">du compte</em></h1>
      <p class="text-app-text/50 text-sm mt-1">Gérez vos préférences et informations</p>
    </div>

    <!-- Infos compte -->
    <div class="card p-5 space-y-3">
      <p class="text-xs uppercase text-gold tracking-widest font-bold">Compte</p>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p class="text-app-text/40 text-xs">Nom</p>
          <p class="font-semibold">{{ auth.user?.name ?? '-' }}</p>
        </div>
        <div>
          <p class="text-app-text/40 text-xs">Email</p>
          <p class="font-semibold">{{ auth.user?.email ?? '-' }}</p>
        </div>
        <div>
          <p class="text-app-text/40 text-xs">Rôle</p>
          <p class="font-semibold capitalize">{{ auth.user?.role ?? '-' }}</p>
        </div>
      </div>
    </div>

    <!-- update of password  -->
    <div class="card p-5">
        <h3 class="font-serif text-[16px] mb-4">Sécurité</h3>
        <div class="flex flex-col gap-3">
          <UiField label="Mot de passe actuel" type="password" v-model="pw.current" placeholder="••••••••" />
          <UiField label="Nouveau mot de passe" type="password" v-model="pw.new_" placeholder="••••••••" />
          <UiField label="Confirmer" type="password" v-model="pw.confirm" placeholder="••••••••" />
          <button class="btn btn-gold btn-md w-full justify-center mt-1"
                  @click="toast.success('Mot de passe mis à jour')">Mettre à jour</button>
        </div>
      </div>

    <!-- Préférences notifications -->
    <div class="card p-5 space-y-4">
      <div>
        <p class="text-xs uppercase text-gold tracking-widest font-bold">
          🔔 Alertes d'expiration de contrat
        </p>
        <p class="text-xs text-app-text/50 mt-1">
          Choisissez quand recevoir un rappel avant l'expiration d'un contrat.
          Vous pouvez sélectionner plusieurs délais simultanément.
        </p>
      </div>

      <div v-if="loadingPrefs" class="flex gap-3">
        <div v-for="i in 3" :key="i" class="h-10 w-24 bg-white/10 rounded-xl animate-pulse" />
      </div>

      <div v-else class="flex gap-3 flex-wrap">
        <button
          v-for="m in [1, 3, 6]" :key="m"
          class="px-5 py-2.5 rounded-xl text-sm font-bold border transition-all"
          :class="selectedDelays.includes(m)
            ? 'border-gold bg-gold/20 text-gold shadow-[0_0_12px_rgba(200,169,110,0.25)]'
            : 'border-white/10 text-app-text/40 hover:border-white/30 hover:text-app-text/70'"
          @click="toggleDelay(m)"
        >
          {{ m }} mois avant
          <span v-if="selectedDelays.includes(m)" class="ml-1">✓</span>
        </button>
      </div>

      <p class="text-xs text-app-text/50">
        Alertes actives :
        <span class="text-gold font-semibold">
          {{ selectedDelays.sort((a,b) => a-b).map(d => `${d} mois`).join(', ') }}
        </span>
        avant expiration
      </p>

      <button
        class="btn btn-gold btn-md"
        :disabled="savingPrefs || loadingPrefs"
        @click="savePreferences"
      >
        {{ savingPrefs ? '⏳ Sauvegarde...' : '💾 Enregistrer les préférences' }}
      </button>
    </div>

  </div>
</template>