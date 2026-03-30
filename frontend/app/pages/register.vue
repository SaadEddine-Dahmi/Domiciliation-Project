<script setup lang="ts">
definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  nom: '',
  prenom: '',
  email: '',
  password: '',
  password_confirmation: '',
  telephone: '',
})

async function submit() {
  if (form.password !== form.password_confirmation) {
    auth.error = 'Les mots de passe ne correspondent pas.'
    return
  }
  const ok = await auth.register({
    nom: form.nom,
    prenom: form.prenom || undefined,
    email: form.email,
    password: form.password,
    telephone: form.telephone || undefined,
  })
  if (ok) await router.push('/admin/dashboard')
}
</script>

<template>
  <div class="min-h-screen grid md:grid-cols-2">
    <div class="hidden md:flex flex-col justify-between p-12" style="border-right:1px solid rgba(200,169,110,0.2)">
      <div class="font-serif text-2xl">AST-FISC <span class="text-gold">Domiciliation</span></div>
      <h2 class="font-serif text-5xl leading-tight">
        Créez votre<br><em class="text-gold italic">espace domiciliataire.</em>
      </h2>
      <div class="space-y-3">
        <div class="card p-4">Gestion complète des contrats</div>
        <div class="card p-4">Suivi des entreprises clientes</div>
        <div class="card p-4">Génération PDF automatique</div>
      </div>
    </div>

    <div class="flex items-center justify-center p-8">
      <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
        <h1 class="font-serif text-3xl">Créer un compte</h1>
        <p class="text-app-text/50 text-sm">Inscription domiciliataire</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="f-label">Nom *</label>
            <input v-model="form.nom" class="f-input" type="text" placeholder="Dahmi" required />
          </div>
          <div>
            <label class="f-label">Prénom</label>
            <input v-model="form.prenom" class="f-input" type="text" placeholder="Saad" />
          </div>
        </div>

        <div>
          <label class="f-label">Email *</label>
          <input v-model="form.email" class="f-input" type="email" placeholder="vous@astfisc.ma" required />
        </div>

        <div>
          <label class="f-label">Téléphone</label>
          <input v-model="form.telephone" class="f-input" type="tel" placeholder="+212 6XX XXX XXX" />
        </div>

        <div>
          <label class="f-label">Mot de passe *</label>
          <input v-model="form.password" class="f-input" type="password" placeholder="Min. 8 caractères" required />
        </div>

        <div>
          <label class="f-label">Confirmer le mot de passe *</label>
          <input v-model="form.password_confirmation" class="f-input" type="password" placeholder="••••••••" required />
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