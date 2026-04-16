<!-- app/app.vue -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

onMounted(() => {
  auth.restoreSession()
  useTheme().init() // restores saved theme — default light
})

const domiciNav = computed(() => [
  { section: 'Principal' },
  { label: 'Tableau de bord',      to: '/admin/dashboard' },
  { label: 'Mes Clients',          to: '/admin/clients' },
  { label: 'Documents',            to: '/admin/documents' },
  { label: 'Factures & Paiements', to: '/admin/paiements' },
  { label: 'Toutes les Factures',  to: '/admin/factures' },
  { section: 'Outils' },
  { label: 'Nouveau Contrat', highlight: true, action: () => router.push('/admin/contrat?new=1') },
  { label: 'Mes Contrats',         to: '/admin/contrats' },
  { label: 'Scanner / Importer',   action: () => router.push('/admin/scan') },
  { label: 'Articles',             to: '/admin/articles' },
  { section: 'Communication' },
  { label: 'Messages clients',     to: '/admin/messages' },
  { label: 'Notifications',        to: '/admin/notifs' },
  { label: 'Paramètres',           to: '/admin/settings' },
])

const adminNav = computed(() => [
  { section: 'Principal' },
  { label: 'Tableau de bord', to: '/admin/dashboard' },
  { label: 'Domiciliataires', to: '/admin/domiciliataires' },
  { section: 'Compte' },
  { label: 'Notifications',   to: '/admin/notifs' },
  { label: 'Paramètres',      to: '/admin/parametres' },
])

const clientNav = computed(() => [
  { section: 'Mon Espace' },
  { label: 'Tableau de bord', to: '/client/dashboard' },
  { label: 'Mes Documents',   to: '/client/documents' },
  { label: 'Mon Contrat',     to: '/client/contrat' },
  { section: 'Communication' },
  { label: 'Messages',        to: '/client/messages' },
  { label: 'Notifications',   to: '/client/notifs' },
])

const navigation = computed(() =>
  auth.isAdmin          ? adminNav.value  :
  auth.isDomiciliataire ? domiciNav.value :
  auth.isClient         ? clientNav.value : []
)
</script>

<template>
  <NuxtLayout :nav="navigation">
    <NuxtPage />
  </NuxtLayout>
</template>