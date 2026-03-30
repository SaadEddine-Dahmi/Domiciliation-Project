<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: '', password: '' })

async function submit() {
  const ok = await auth.login(form)
  if (!ok) return
  await router.push(auth.isAdmin ? '/admin/dashboard' : '/client/dashboard')
}
</script>

<template>
  <div class="min-h-screen grid md:grid-cols-2">
    <div class="hidden md:flex flex-col justify-between p-12" style="border-right:1px solid rgba(200,169,110,0.2)">
      <div class="font-serif text-2xl">AST-FISC <span class="text-gold">Domiciliation</span></div>
      <h2 class="font-serif text-5xl leading-tight">Gérez vos contrats<br><em class="text-gold italic">en toute simplicité.</em></h2>
      <div class="space-y-3">
        <div class="card p-4">1 200+ Contrats générés</div>
        <div class="card p-4">340+ Sociétés domiciliées</div>
        <div class="card p-4">100% Conforme RC & IF</div>
      </div>
    </div>

    <div class="flex items-center justify-center p-8">
      <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
        <h1 class="font-serif text-3xl">Bienvenue</h1>
        <p class="text-app-text/50 text-sm">Connectez-vous à votre espace de gestion</p>

        <div>
          <label class="f-label">Adresse email</label>
          <input v-model="form.email" class="f-input" type="email" placeholder="admin@astfisc.ma" />
        </div>
        <div>
          <label class="f-label">Mot de passe</label>
          <input v-model="form.password" class="f-input" type="password" placeholder="••••••••" />
        </div>

        <p v-if="auth.error" class="text-red-400 text-sm">{{ auth.error }}</p>
        <button class="btn btn-gold btn-lg w-full justify-center" :disabled="auth.loading">
          {{ auth.loading ? 'Connexion...' : 'Se connecter' }}
        </button>

        <p class="text-xs text-app-text/40">
          Pas encore de compte ? <NuxtLink to="/register" class="text-gold underline">Créer un compte domiciliataire</NuxtLink>
        </p>
      </form> 
    </div>
  </div>
</template>