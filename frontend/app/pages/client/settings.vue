<!-- pages/client/settings.vue -->
<script setup lang="ts">
// pages/client/settings.vue
//
// Client account settings. A client manages three things here:
//   - their profile photo (avatar shown in the sidebar/topbar)
//   - their own identity (read-only — name/email come from the account
//     created for them by their domiciliataire, not self-editable)
//   - their password
//
// This page isn't in clientNav (see app.vue) — it's reached via the
// user-card link at the top of the sidebar (AppSidebar.vue →
// settingsPath), same as the equivalent admin/domiciliataire pages.
//
// SECURITY FIX: the password form used to call setTimeout() and show a
// fake "Mot de passe mis à jour" toast without ever contacting the
// backend — any input in "Mot de passe actuel" was silently accepted
// and nothing changed in the database. This now calls the real
// PUT /api/account/password endpoint (AuthController::changePassword),
// which verifies current_password with Hash::check() server-side
// before allowing the update.

import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth  = useAuthStore()
const toast = useToast()
const { success, error: toastError } = toast

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  return auth.token ? { Authorization: `Bearer ${auth.token}` } : {}
}

// ══════════════════════════════════════════════════════════════
// Password change
// ══════════════════════════════════════════════════════════════

const pw = reactive({ current: '', new_: '', confirm: '' })
const pwError = ref('')
const updatingPw = ref(false)

/**
 * Client-side pre-checks only cover format (length, match) — they are
 * NOT a substitute for the server verifying the current password.
 * The actual authorization decision always happens in
 * AuthController::changePassword() via Hash::check().
 */
async function submitPasswordUpdate(): Promise<void> {
  pwError.value = ''

  if (!pw.current) {
    pwError.value = 'Renseignez votre mot de passe actuel'
    return
  }
  if (pw.new_.length < 8) {
    pwError.value = 'Le nouveau mot de passe doit contenir au moins 8 caractères'
    return
  }
  if (pw.new_ !== pw.confirm) {
    pwError.value = 'La confirmation ne correspond pas au nouveau mot de passe'
    return
  }

  updatingPw.value = true
  try {
    await $fetch(`${getApiBase()}/api/account/password`, {
      method: 'PUT',
      headers: authHeaders(),
      body: {
        current_password: pw.current,
        // Laravel's 'confirmed' rule requires this exact key name —
        // it checks 'password' against 'password_confirmation'.
        password: pw.new_,
        password_confirmation: pw.confirm,
      },
    })

    success('Mot de passe mis à jour')
    auth.clearMustChangePassword()
    pw.current = ''
    pw.new_ = ''
    pw.confirm = ''
  } catch (e: any) {
    // 422 from a failed 'current_password' check, or validation errors
    // on the new password (min length / confirmation mismatch caught
    // again server-side as a safety net).
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
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-2xl">

    <div>
      <h1 class="font-serif text-2xl">Paramètres <em class="text-gold italic">du compte</em></h1>
      <p class="text-sm mt-1" style="color: var(--app-text-muted)">Gérez vos informations et votre mot de passe</p>
    </div>

    <!-- ══════════ Photo de profil ═══════════════════════════
         Self-contained upload/remove control — reads/writes the auth
         store directly, so the sidebar/topbar avatar updates the
         instant a change succeeds. Backend now accepts this for both
         domiciliataire and client roles — see
         DomiciliataireProfileController::uploadPhoto()/deletePhoto(). -->
    <div class="card p-5 space-y-3">
      <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Photo de profil</p>
      <ProfilePhotoUpload />
    </div>

    <!-- ══════════ Identité — read-only ══════════════════════
         A client's name/email are set up by their domiciliataire when
         the account is created; there's no self-edit endpoint for
         these, so they're shown for reference only. -->
    <div class="card p-5 space-y-3">
      <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Compte</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
          <p class="text-xs" style="color: var(--app-text-faint)">Nom</p>
          <p class="font-semibold mt-0.5" style="color: var(--app-text)">{{ auth.user?.name ?? '-' }}</p>
        </div>
        <div>
          <p class="text-xs" style="color: var(--app-text-faint)">Email</p>
          <p class="font-semibold mt-0.5" style="color: var(--app-text)">{{ auth.user?.email ?? '-' }}</p>
        </div>
      </div>
      <p class="text-xs pt-1" style="color: var(--app-text-faint)">
        Pour modifier ces informations, contactez votre domiciliataire.
      </p>
    </div>

    <!-- ══════════ Sécurité — password change ════════════════ -->
    <div class="card p-5 space-y-4">
      <div>
        <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">Sécurité</p>
        <p class="text-xs mt-1" style="color: var(--app-text-faint)">
          Modifiez le mot de passe utilisé pour vous connecter à ce compte.
        </p>
      </div>

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

  </div>
</template>