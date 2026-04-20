<!-- app/pages/login.vue -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'default' })

const auth   = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })

async function submit() {
  const ok = await auth.login(form)
  if (!ok) return

  // Route based on role
  if (auth.isAdmin)          return router.push('/admin/dashboard')
  if (auth.isDomiciliataire) return router.push('/admin/dashboard')
  if (auth.isClient)         return router.push('/client/dashboard')
}
</script>

<template>
  <div class="min-h-screen grid md:grid-cols-2">

    <!-- Left panel -->
    <div
      class="hidden md:flex flex-col justify-between p-12"
      style="border-right:1px solid rgba(200,169,110,0.2)"
    >
      <div class="font-serif text-2xl">
        AST-FISC <span class="text-gold">Domiciliation</span>
      </div>
      <h2 class="font-serif text-5xl leading-tight">
        Gérez vos contrats<br>
        <em class="text-gold italic">en toute simplicité.</em>
      </h2>
      <div class="space-y-3">
        <div class="card p-4">1 200+ Contrats générés</div>
        <div class="card p-4">340+ Sociétés domiciliées</div>
        <div class="card p-4">100% Conforme RC &amp; IF</div>
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
            placeholder="admin@astfisc.ma"
            required
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
          />
        </div>

        <!-- Error block — shows activation-specific messages from backend -->
        <div v-if="auth.error" class="rounded-xl p-3 text-sm space-y-1"
          :class="{
            'bg-yellow-400/10 text-yellow-300': auth.error.includes('attente') || auth.error.includes('activé le'),
            'bg-red-400/10 text-red-400':       auth.error.includes('rejeté') || auth.error.includes('invalides') || auth.error.includes('refusé'),
          }"
        >
          <!-- Pending -->
          <p v-if="auth.error.includes('attente')">
            ⏳ {{ auth.error }}
          </p>
          <!-- Approved but not yet active -->
          <p v-else-if="auth.error.includes('activé le')">
            📅 {{ auth.error }}
          </p>
          <!-- Rejected -->
          <p v-else-if="auth.error.includes('rejeté')">
            ❌ {{ auth.error }}
          </p>
          <!-- Generic error -->
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