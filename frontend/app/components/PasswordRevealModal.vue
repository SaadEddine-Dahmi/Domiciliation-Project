<!-- components/ChangePasswordPrompt.vue -->
<!--
  Shown when the authenticated user's password is still the one the
  system generated (auth.user.mustChangePassword === true) — i.e. a new
  client account, or an account whose password was just reset by its
  domiciliataire.

  Snooze behaviour:
    "Plus tard" does NOT dismiss the prompt permanently and does NOT rely
    on sessionStorage (which would just mean "until the tab closes").
    Instead it records a snooze timestamp in localStorage, keyed to the
    user's id, and the prompt is suppressed until SNOOZE_DAYS have passed
    — then it reappears automatically on a later visit, even within the
    same login session. This keeps the security nudge alive without being
    intrusive on every single page load.

    The prompt stops appearing entirely, permanently, the moment the user
    actually changes their password (must_change_password becomes false
    server-side and auth.clearMustChangePassword() updates the local copy).
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { passwordService } from '~/services/password.service'

const auth = useAuthStore()
const { success, error: toastError } = useToast()

// How long a "Plus tard" snooze lasts before the prompt is shown again.
// Change this single constant to tune the reminder frequency.
const SNOOZE_DAYS = 7

const visible = ref(false)
const mode = ref<'ask' | 'form'>('ask')
const saving = ref(false)
const serverError = ref('')

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

/**
 * Snooze storage key is namespaced per user id so switching accounts on
 * the same browser never leaks or reuses another user's snooze window.
 */
function snoozeKey(): string | null {
  if (!auth.user) return null
  return `pwd_prompt_snooze_${auth.user.id}`
}

/**
 * True if the user snoozed the prompt recently enough that it should
 * stay hidden. False if there's no snooze record yet, the record is
 * malformed, or SNOOZE_DAYS have already elapsed since it was set.
 */
function isSnoozed(): boolean {
  if (!import.meta.client) return true
  const key = snoozeKey()
  if (!key) return true

  const raw = localStorage.getItem(key)
  if (!raw) return false

  const snoozedAt = Number(raw)
  if (!Number.isFinite(snoozedAt)) {
    // Malformed value from an older version of this component —
    // treat as "not snoozed" instead of hiding the prompt forever.
    localStorage.removeItem(key)
    return false
  }

  const elapsedMs = Date.now() - snoozedAt
  const snoozeMs = SNOOZE_DAYS * 24 * 60 * 60 * 1000
  return elapsedMs < snoozeMs
}

/** Records "snoozed now" and hides the prompt until SNOOZE_DAYS pass. */
function snooze(): void {
  visible.value = false
  const key = snoozeKey()
  if (key && import.meta.client) {
    localStorage.setItem(key, String(Date.now()))
  }
}

/** Clears any snooze record — called after a successful password change
 *  so a stale snooze timestamp can never suppress a future prompt if
 *  must_change_password is ever set true again by a later reset. */
function clearSnooze(): void {
  const key = snoozeKey()
  if (key && import.meta.client) {
    localStorage.removeItem(key)
  }
}

async function submitChange(): Promise<void> {
  serverError.value = ''
  if (form.password !== form.password_confirmation) {
    serverError.value = 'Les mots de passe ne correspondent pas.'
    return
  }
  saving.value = true
  try {
    await passwordService.change(form.current_password, form.password, form.password_confirmation)
    auth.clearMustChangePassword()
    clearSnooze()
    success('Mot de passe mis à jour ✓')
    visible.value = false
  } catch (e: any) {
    serverError.value = e?.data?.errors?.current_password?.[0]
      ?? e?.data?.message
      ?? 'Erreur lors de la mise à jour'
    toastError?.(serverError.value)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (auth.user?.mustChangePassword && !isSnoozed()) {
    visible.value = true
    mode.value = 'ask'
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4">
      <div class="card w-full max-w-sm p-6 space-y-4">

        <template v-if="mode === 'ask'">
          <h2 class="font-serif text-lg">Voulez-vous changer votre mot de passe ?</h2>
          <p class="text-sm text-app-text/60">
            Votre mot de passe actuel a été généré automatiquement. Pour plus de sécurité,
            nous vous recommandons d'en choisir un que vous seul connaissez.
          </p>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" class="btn btn-outline btn-md" @click="snooze">
              Plus tard
            </button>
            <button type="button" class="btn btn-gold btn-md" @click="mode = 'form'">
              Changer maintenant
            </button>
          </div>
          <p class="text-[11px] text-app-text/35">
            Ce rappel réapparaîtra dans {{ SNOOZE_DAYS }} jours si vous ne changez pas votre mot de passe.
          </p>
        </template>

        <template v-else>
          <h2 class="font-serif text-lg">Nouveau mot de passe</h2>
          <div class="space-y-3">
            <div>
              <label class="f-label">Mot de passe actuel *</label>
              <input v-model="form.current_password" type="password" class="f-input" />
            </div>
            <div>
              <label class="f-label">Nouveau mot de passe *</label>
              <input v-model="form.password" type="password" class="f-input" placeholder="Min. 8 caractères" />
            </div>
            <div>
              <label class="f-label">Confirmer *</label>
              <input v-model="form.password_confirmation" type="password" class="f-input" />
            </div>
          </div>
          <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" class="btn btn-outline btn-md" @click="mode = 'ask'">Retour</button>
            <button type="button" class="btn btn-gold btn-md" :disabled="saving" @click="submitChange">
              {{ saving ? 'Enregistrement...' : 'Confirmer' }}
            </button>
          </div>
        </template>

      </div>
    </div>
  </Teleport>
</template>