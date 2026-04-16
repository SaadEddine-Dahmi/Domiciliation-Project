
<script setup lang="ts">
definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const auth = useAuthStore()

const toast = useToast()
const pw = reactive({ current:'', new_:'', confirm:'' })
</script>

<template>

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
</template>