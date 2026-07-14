<!-- app/pages/register.vue
  Registration page.
  Left panel copy is now a feature list rather than invented numbers.
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'default' })

const auth   = useAuthStore()
const router = useRouter()

if (auth.isAuthenticated) {
  await navigateTo(auth.isAdmin || auth.isDomiciliataire ? '/admin/dashboard' : '/client/dashboard')
}

function getAppName(): string {
  const config = useRuntimeConfig()
  return (config.public.appName as string) ?? 'Domiciliation Manager'
}
const appName = getAppName()

const form = reactive({
  nom:                   '',
  prenom:                '',
  email:                 '',
  password:              '',
  password_confirmation: '',
  telephone:             '',
})

async function submit() {
  if (form.password !== form.password_confirmation) {
    auth.error = 'Les mots de passe ne correspondent pas.'
    return
  }
  const ok = await auth.register({
    nom:       form.nom,
    prenom:    form.prenom    || undefined,
    email:     form.email,
    password:  form.password,
    telephone: form.telephone || undefined,
  })

  if (ok && !auth.isPendingApproval) {
    await router.push('/admin/dashboard')
  }
}

const features = [
  { icon: '📄', text: 'Génération automatique de contrats PDF' },
  { icon: '🏢', text: 'Gestion centralisée de vos clients' },
  { icon: '📁', text: 'Suivi de vos documents et échéances' },
]
</script>

<template>
  <div class="min-h-screen grid md:grid-cols-2">

    <!-- Left panel -->
    <div
      class="hidden md:flex flex-col justify-between p-12"
      style="border-right:1px solid var(--app-border-2)"
    >
      <NuxtLink to="/" class="font-serif text-2xl">
        {{ appName }}
      </NuxtLink>

      <h2 class="font-serif text-5xl leading-tight">
        Créez votre<br>
        <em class="text-gold italic">espace domiciliataire.</em>
      </h2>

      <div class="space-y-3">
        <div v-for="f in features" :key="f.text" class="card p-4 flex items-center gap-3">
          <span class="text-xl">{{ f.icon }}</span>
          <span class="text-sm" style="color:var(--app-text-muted)">{{ f.text }}</span>
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="flex items-center justify-center p-8">

      <!-- Pending approval screen -->
      <div
        v-if="auth.isPendingApproval"
        class="w-full max-w-sm text-center space-y-5"
      >
        <div class="text-6xl">⏳</div>
        <h1 class="font-serif text-3xl">Demande envoyée</h1>
        <p class="text-app-text/60 text-sm leading-relaxed">
          Votre compte est en attente de validation par un administrateur.
          Vous recevrez une confirmation dès que votre compte sera activé.
        </p>
        <div class="card p-4 text-left space-y-2 text-sm">
          <p class="text-gold font-semibold">Prochaines étapes :</p>
          <p class="text-app-text/60">1. L'administrateur examine votre demande</p>
          <p class="text-app-text/60">2. Votre compte est approuvé avec une date d'activation</p>
          <p class="text-app-text/60">3. Vous pouvez vous connecter à partir de cette date</p>
        </div>
        <NuxtLink to="/login" class="btn btn-gold btn-md w-full justify-center block">
          Retour à la connexion
        </NuxtLink>
      </div>

      <!-- Registration form -->
      <form
        v-else
        class="w-full max-w-sm space-y-4"
        @submit.prevent="submit"
      >
        <h1 class="font-serif text-3xl">Créer un compte</h1>
        <p class="text-app-text/50 text-sm">Inscription domiciliataire</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="f-label">Nom *</label>
            <input v-model="form.nom" class="f-input" type="text" placeholder="Nom" required />
          </div>
          <div>
            <label class="f-label">Prénom</label>
            <input v-model="form.prenom" class="f-input" type="text" placeholder="Prénom" />
          </div>
        </div>

        <div>
          <label class="f-label">Email *</label>
          <input v-model="form.email" class="f-input" type="email" placeholder="vous@exemple.com" required autocomplete="email" />
        </div>

        <div>
          <label class="f-label">Téléphone</label>
          <input v-model="form.telephone" class="f-input" type="tel" placeholder="+212 6XX XXX XXX" />
        </div>

        <div>
          <label class="f-label">Mot de passe *</label>
          <input v-model="form.password" class="f-input" type="password" placeholder="Min. 8 caractères" required autocomplete="new-password" />
        </div>

        <div>
          <label class="f-label">Confirmer le mot de passe *</label>
          <input v-model="form.password_confirmation" class="f-input" type="password" placeholder="••••••••" required autocomplete="new-password" />
        </div>

        <p v-if="auth.error" class="text-red-400 text-sm">{{ auth.error }}</p>

        <button class="btn btn-gold btn-lg w-full justify-center" :disabled="auth.loading">
          {{ auth.loading ? 'Création...' : 'Créer mon compte' }}
        </button>

        <p class="text-xs text-app-text/40 text-center">
          Déjà un compte ?
          <NuxtLink to="/login" class="text-gold underline">Se connecter</NuxtLink>
        </p>
      </form>

    </div>
  </div>
</template>