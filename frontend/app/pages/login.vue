<!-- app/pages/login.vue
  Login page.
  Left panel no longer shows fabricated stats ("1200+ contrats générés" etc.)
  — those numbers didn't come from anywhere real. Instead it shows a short,
  honest value proposition. If real platform-wide stats become available
  via a public endpoint later, they can be wired in here instead.
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'default' })

const auth   = useAuthStore()
const router = useRouter()

// Guard: an already-authenticated user shouldn't see the login form.
if (auth.isAuthenticated) {
  await navigateTo(auth.isAdmin || auth.isDomiciliataire ? '/admin/dashboard' : '/client/dashboard')
}

function getAppName(): string {
  const config = useRuntimeConfig()
  return (config.public.appName as string) ?? 'Domiciliation Manager'
}
const appName = getAppName()

const form = reactive({ email: '', password: '' })

async function submit() {
  const ok = await auth.login(form)
  if (!ok) return

  if (auth.isAdmin)          return router.push('/admin/dashboard')
  if (auth.isDomiciliataire) return router.push('/admin/dashboard')
  if (auth.isClient)         return router.push('/client/dashboard')
}

// Real, non-numeric value points instead of invented statistics
const valueProps = [
  { icon: '📄', text: 'Génération de contrats PDF en quelques clics' },
  { icon: '🔔', text: 'Alertes automatiques avant expiration' },
  { icon: '🔒', text: 'Données isolées par domiciliataire' },
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
        Gérez vos contrats<br>
        <em class="text-gold italic">en toute simplicité.</em>
      </h2>

      <div class="space-y-3">
        <div v-for="v in valueProps" :key="v.text" class="card p-4 flex items-center gap-3">
          <span class="text-xl">{{ v.icon }}</span>
          <span class="text-sm" style="color:var(--app-text-muted)">{{ v.text }}</span>
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="flex items-center justify-center p-8">
      <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
        <h1 class="font-serif text-3xl">Bienvenue</h1>
        <p class="text-app-text/50 text-sm">
          Connectez-vous à votre espace de gestion
        </p>

        <div>
          <label class="f-label">Adresse email</label>
          <input
            v-model="form.email"
            class="f-input"
            type="email"
            placeholder="vous@exemple.com"
            required
            autocomplete="email"
          />
        </div>
        <div>
          <label class="f-label">Mot de passe</label>
          <input
            v-model="form.password"
            class="f-input"
            type="password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
        </div>

        <!-- Error block — shows activation-specific messages from backend -->
        <div v-if="auth.error" class="rounded-xl p-3 text-sm space-y-1"
          :class="{
            'bg-yellow-400/10 text-yellow-300': auth.error.includes('attente') || auth.error.includes('activé le'),
            'bg-red-400/10 text-red-400':       auth.error.includes('rejeté') || auth.error.includes('invalides') || auth.error.includes('refusé'),
          }"
        >
          <p v-if="auth.error.includes('attente')">⏳ {{ auth.error }}</p>
          <p v-else-if="auth.error.includes('activé le')">📅 {{ auth.error }}</p>
          <p v-else-if="auth.error.includes('rejeté')">❌ {{ auth.error }}</p>
          <p v-else>{{ auth.error }}</p>
        </div>

        <button
          class="btn btn-gold btn-lg w-full justify-center"
          :disabled="auth.loading"
        >
          {{ auth.loading ? 'Connexion...' : 'Se connecter' }}
        </button>

        <p class="text-xs text-app-text/40">
          Pas encore de compte ?
          <NuxtLink to="/register" class="text-gold underline">
            Créer un compte domiciliataire
          </NuxtLink>
        </p>
      </form>
    </div>

  </div>
</template>