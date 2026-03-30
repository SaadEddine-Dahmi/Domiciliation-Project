
<template>
  <div class="flex min-h-screen" style="background:#0b0d13">
    <AppSidebar :nav="navItems" />

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
import { useAuthStore } from '../stores/auth'
import { useNotificationsStore } from '../stores/notifs'
import { useClientsStore } from '../stores/clients'
import { useDocumentsStore } from '../stores/documents'
import { useRoute } from 'vue-router'
import { computed } from 'vue'

const auth = useAuthStore()
const notifs = useNotificationsStore()
const clients = useClientsStore()
const docs = useDocumentsStore()
const route = useRoute()

// const isAdmin = computed(() => auth.user?.role === 'admin')


// Assuming the correct spelling is 'isDomiciliaire' and it checks for a specific user role
const isDomiciliataire = computed(() => auth.user?.role === 'domiciliataire')

const pageTitle = computed(() => {
  const map: Record<string, string> = {
    '/admin/dashboard': 'Tableau de bord',
    '/admin/clients': 'Mes Clients',
    '/admin/documents': 'Documents',
    '/admin/contrat': 'Nouveau Contrat',
    '/admin/scan': 'Scanner / Importer',
    '/admin/notifs': 'Notifications',
    '/admin/settings': 'Paramètres',
    // '/client/dashboard': "Vue d'ensemble",
    '/client/documents': 'Mes Documents',
    '/client/contrat': 'Mon Contrat',
    '/client/notifs': 'Notifications',
  }
  return map[route.path] ?? 'AST-FISC'
})

const navItems = computed(() => {
  if (isDomiciliataire.value) {
    return [
      { section: 'Principal' },
      { id: 'dashboard', label: 'Tableau de bord', icon: 'chart', to: '/admin/dashboard' },
      { id: 'clients', label: 'Mes Clients', icon: 'users', to: '/admin/clients', badge: clients.pendingCount || 0 },
      { id: 'documents', label: 'Documents', icon: 'docs', to: '/admin/documents', badge: docs.pendingCount || 0 },
      { section: 'Outils' },
      { id: 'contrat', label: 'Nouveau Contrat', icon: 'plus', to: '/admin/contrat' },
      { id: 'scan', label: 'Scanner / Importer', icon: 'scan', to: '/admin/scan' },
      { section: 'Compte' },
      { id: 'notifs', label: 'Notifications', icon: 'bell', to: '/admin/notifs', badge: notifs.unreadCount || 0 },
      { id: 'settings', label: 'Paramètres', icon: 'gear', to: '/admin/settings' },
    ]
  }

  return [
    { section: 'Mon espace' },
    // { id: 'dashboard', label: "Vue d'ensemble", icon: 'chart', to: '/client/dashboard' },
    { id: 'documents', label: 'Mes Documents', icon: 'docs', to: '/client/documents' },
    { id: 'contrat', label: 'Mon Contrat', icon: 'file', to: '/client/contrat' },
    { section: 'Compte' },
    { id: 'notifs', label: 'Notifications', icon: 'bell', to: '/client/notifs', badge: notifs.unreadCount || 0 },
  ]
})

</script>