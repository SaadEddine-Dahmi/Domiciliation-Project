<!-- app/layouts/dashboard.vue -->
<template>
  <div class="flex min-h-screen transition-colors" style="background: var(--app-bg)">
    <AppSidebar :nav="nav" />
    <div class="flex flex-col flex-1" style="margin-left: 230px; min-height: 100vh">
      <AppTopbar :title="pageTitle" />
      <main class="flex-1 p-6"><slot /></main>
    </div>
    <AppToast />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ nav?: any[] }>()
const nav   = computed(() => props.nav ?? [])
const route = useRoute()

const pageTitle = computed(() => ({
  '/admin/dashboard':       'Tableau de bord',
  '/admin/clients':         'Mes Clients',
  '/admin/documents':       'Documents',
  '/admin/paiements':       'Factures & Paiements',
  '/admin/factures':        'Toutes les Factures',
  '/admin/contrat':         'Nouveau Contrat',
  '/admin/contrats':        'Mes Contrats',
  '/admin/scanner':         'Scanner / Importer',
  '/admin/scan':            'Scanner / Importer',
  '/admin/articles':        'Articles',
  '/admin/messages':        'Messages clients',
  '/admin/notifs':          'Notifications',
  '/admin/parametres':      'Paramètres',
  '/admin/settings':        'Paramètres',
  '/admin/domiciliataires': 'Domiciliataires',
  '/client/dashboard':      'Tableau de bord',
  '/client/documents':      'Mes Documents',
  '/client/contrat':        'Mon Contrat',
  '/client/messages':       'Messages',
  '/client/notifs':         'Notifications',
}[route.path] ?? 'AST-FISC'))
</script>