<template>
  <div class="animate-fade-up max-w-3xl">
    <h2 class="font-serif text-[22px] mb-5">Paramètres <em class="text-gold italic">du compte</em></h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div class="card p-5">
        <h3 class="font-serif text-[16px] mb-4">Informations AST-FISC</h3>
        <div class="flex flex-col gap-3">
          <UiField label="Raison sociale" v-model="s.nom" />
          <UiField label="RC" v-model="s.rc" />
          <UiField label="IF" v-model="s.if_" />
          <UiField label="Représentant" v-model="s.rep" />
          <UiField label="Adresse" v-model="s.addr" />
          <button class="btn btn-gold btn-md w-full justify-center" @click="toast.success('Paramètres sauvegardés')">
            Sauvegarder
          </button>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="font-serif text-[16px] mb-4">Sécurité</h3>
        <div class="flex flex-col gap-3">
          <UiField label="Mot de passe actuel" type="password" v-model="pw.current" />
          <UiField label="Nouveau mot de passe" type="password" v-model="pw.new_" />
          <UiField label="Confirmer" type="password" v-model="pw.confirm" />
          <button class="btn btn-gold btn-md w-full justify-center" @click="toast.success('Mot de passe mis à jour')">
            Mettre à jour
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '../../composables/useToast'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const toast = useToast()
const s = reactive({ nom: 'AST-FISC SARL AU', rc: '56989', if_: '60102285', rep: 'KHARBOUCHE ANOUAR RACHID', addr: 'AGADIR' })
const pw = reactive({ current: '', new_: '', confirm: '' })
</script>