<!-- ============================================================
  layouts/dashboard.vue
  FIX : utilise la nav passée depuis app.vue via defineProps
  au lieu de recalculer sa propre navItems
============================================================ -->
<template>
  <div class="flex min-h-screen" style="background:#0b0d13">
    <AppSidebar :nav="nav" />

    <div class="flex flex-col flex-1" style="margin-left:220px;min-height:100vh">
      <AppTopbar :title="pageTitle" />
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>

    <AppToast />
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
import { computed } from 'vue'

// Reçoit la nav depuis app.vue via NuxtLayout
const props = defineProps<{ nav?: any[] }>()

// Accès direct à la prop nav (fournie par app.vue)
const nav = computed(() => props.nav ?? [])

const route = useRoute()

const pageTitle = computed(() => {
  const map: Record<string, string> = {
    '/admin/dashboard':       'Tableau de bord',
    '/admin/clients':         'Mes Clients',
    '/admin/documents':       'Documents',
    '/admin/paiements':       'Factures & Paiements',
    '/admin/factures':          'Toutes les Factures',
    '/admin/contrat':         'Nouveau Contrat',
    '/admin/contrats':        'Mes Contrats',
    '/admin/scanner':         'Scanner / Importer',
    '/admin/scan':            'Scanner / Importer',
    '/admin/articles':        'Articles',
    '/admin/messages':        'Messages clients',
    // '/admin/notifs':   'Notifications',
    '/admin/notifs':          'Notifications',
    '/admin/parametres':      'Paramètres',
    '/admin/settings':        'Paramètres',
    '/admin/domiciliataires': 'Domiciliataires',
    '/client/dashboard':      'Tableau de bord',
    '/client/documents':      'Mes Documents',
    '/client/contrat':        'Mon Contrat',
    '/client/messages':       'Messages',
    // '/client/notifications':  'Notifications',
    '/client/notifs':         'Notifications',
  }
  return map[route.path] ?? 'AST-FISC'
})
</script>