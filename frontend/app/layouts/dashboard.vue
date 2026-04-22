<!-- app/layouts/dashboard.vue -->
<template>
  <div class="flex min-h-screen" style="background: var(--app-bg)">

    <AppSidebar :nav="nav" />

    <!-- Main content -->
    <!-- FIX: blur is purely visual (CSS filter), content is always scrollable and
         pointer-events are never disabled so scrolling always works on mobile.
         The backdrop overlay (z-40) handles tap-to-close independently. -->
    <div
      class="flex flex-col flex-1 min-w-0 transition-[margin,filter] duration-300 ease-in-out"
      :class="[
        'ml-0',
        isOpen ? 'lg:ml-57.5' : 'lg:ml-16',
      ]"
      :style="isMobileOpen ? 'filter: blur(2px) brightness(0.75);' : 'filter: none;'"
    >
      <AppTopbar :title="pageTitle" />
      <main class="flex-1 p-4 sm:p-5 lg:p-6 min-w-0">
        <slot />
      </main>
    </div>

    <AppToast />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ nav?: any[] }>()
const nav   = computed(() => props.nav ?? [])
const route = useRoute()
const { isOpen, isMobileOpen } = useSidebar()

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
  '/admin/settings':        'Paramètres',
  '/admin/domiciliataires': 'Domiciliataires',
  '/client/dashboard':      'Tableau de bord',
  '/client/documents':      'Mes Documents',
  '/client/contrat':        'Mon Contrat',
  '/client/messages':       'Messages',
  '/client/notifs':         'Notifications',
}[route.path] ?? 'AST-FISC'))
</script>