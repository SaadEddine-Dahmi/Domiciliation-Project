<!-- pages/client/settings.vue -->
<script setup lang="ts">
// pages/client/settings.vue
//
// Client account settings. A client only ever manages two things here:
//   - their own identity (read-only — name/email come from the account
//     created for them by their domiciliataire, not self-editable)
//   - their password
//
// This page isn't in clientNav (see app.vue) — it's reached via the
// user-card link at the top of the sidebar (AppSidebar.vue →
// settingsPath), same as the equivalent admin/domiciliataire pages.
//
// NOTE: no self-service password-update endpoint was provided for the
// client role (same gap as pages/admin/settings.vue). The validation
// below is real and complete; the submit call is a clearly-marked
// placeholder until PUT /api/profile/password (or equivalent) exists.

import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth  = useAuthStore()
const toast = useToast()
const { success, error: toastError } = toast

// ══════════════════════════════════════════════════════════════
// Password change — validated client-side, same rules as the
// admin/domiciliataire settings page for consistency.
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
  // route exists for the client role, e.g. PUT /api/profile/password.
  setTimeout(() => {
    updatingPw.value = false
    success('Mot de passe mis à jour')
    pw.current = ''; pw.new_ = ''; pw.confirm = ''
  }, 300)
}
</script>

<template>
  <div class="space-y-5 animate-fade-up max-w-2xl">

    <div>
      <h1 class="font-serif text-2xl">Paramètres <em class="text-gold italic">du compte</em></h1>
      <p class="text-sm mt-1" style="color: var(--app-text-muted)">Gérez vos informations et votre mot de passe</p>
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