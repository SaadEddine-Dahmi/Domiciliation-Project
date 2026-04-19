<!-- app/app.vue -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

onMounted(() => {
  auth.restoreSession()
  useTheme().init()
})

// ── SVG icon strings ─────────────────────────────────────────────────────────
const icons = {

  dashboard: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
  </svg>`,

  clients: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="9" cy="7" r="3"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
  </svg>`,

  documents: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
    <polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/>
    <line x1="8" y1="17" x2="13" y2="17"/>
  </svg>`,

  // Image 1 — receipt/list with $ coin badge
  facturesPaiements: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 3h10l2 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3z"/>
    <path d="M4 19c0 1.1.9 2 2 2h10"/>
    <circle cx="17" cy="8" r="3.5"/>
    <line x1="17" y1="6.5" x2="17" y2="9.5"/>
    <line x1="7" y1="9"  x2="12" y2="9"/>
    <line x1="7" y1="12" x2="12" y2="12"/>
    <line x1="7" y1="15" x2="10" y2="15"/>
    <circle cx="6.5" cy="9"  r="0.6" fill="currentColor" stroke="none"/>
    <circle cx="6.5" cy="12" r="0.6" fill="currentColor" stroke="none"/>
    <circle cx="6.5" cy="15" r="0.6" fill="currentColor" stroke="none"/>
  </svg>`,

  // Image 2 — document with pencil + signature
  toutesFactures: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
    <polyline points="14 2 14 8 20 8"/>
    <line x1="8" y1="11" x2="15" y2="11"/>
    <line x1="8" y1="14" x2="15" y2="14"/>
    <path d="M8 17.5 q1-1 2 0 t2 0" stroke-width="1.5"/>
    <path d="M17.5 14.5 l1.5-1.5-1-1-1.5 1.5z" stroke-width="1.5"/>
    <line x1="17" y1="15" x2="19" y2="13" stroke-width="1.5"/>
  </svg>`,

  // Image 3 — sparkle stars (Nouveau Contrat)
  nouveauContrat: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9 2 L10.2 6.8 L15 8 L10.2 9.2 L9 14 L7.8 9.2 L3 8 L7.8 6.8 Z"/>
    <path d="M18 12 L18.8 14.8 L21.5 15.5 L18.8 16.2 L18 19 L17.2 16.2 L14.5 15.5 L17.2 14.8 Z"/>
    <path d="M17 2 L17.5 3.8 L19.5 4.3 L17.5 4.8 L17 6.5 L16.5 4.8 L14.5 4.3 L16.5 3.8 Z"/>
  </svg>`,

  contrats: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
    <polyline points="14 2 14 8 20 8"/>
    <line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/>
    <polyline points="10 9 9 9 8 9"/>
  </svg>`,

  scanner: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/>
    <path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/>
    <line x1="3" y1="12" x2="21" y2="12" stroke-width="2"/>
  </svg>`,

  articles: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <line x1="3" y1="6"  x2="21" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
    <line x1="3" y1="14" x2="15" y2="14"/><line x1="3" y1="18" x2="12" y2="18"/>
  </svg>`,

  messages: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>`,

  notifications: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
  </svg>`,

  settings: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="3"/>
    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
  </svg>`,

  domiciliataires: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
    <polyline points="9 22 9 12 15 12 15 22"/>
  </svg>`,

  contratClient: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
    <polyline points="14 2 14 8 20 8"/>
    <line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="11" y2="17"/>
    <path d="M11 17 q1-1 2 0" stroke-width="1.5"/>
  </svg>`,
}

// ── Nav definitions ───────────────────────────────────────────────────────────
const domiciNav = computed(() => [
  { section: 'Principal' },
  { label: 'Tableau de bord',      to: '/admin/dashboard',  icon: icons.dashboard },
  { label: 'Mes Clients',          to: '/admin/clients',    icon: icons.clients },
  { label: 'Documents',            to: '/admin/documents',  icon: icons.documents },
  { label: 'Factures & Paiements', to: '/admin/paiements',  icon: icons.facturesPaiements },
  { label: 'Toutes les Factures',  to: '/admin/factures',   icon: icons.toutesFactures },
  { section: 'Outils' },
  { label: 'Nouveau Contrat', highlight: true, action: () => router.push('/admin/contrat?new=1'), icon: icons.nouveauContrat },
  { label: 'Mes Contrats',         to: '/admin/contrats',   icon: icons.contrats },
  { label: 'Scanner / Importer',   action: () => router.push('/admin/scan'), icon: icons.scanner },
  { label: 'Articles',             to: '/admin/articles',   icon: icons.articles },
  { section: 'Communication' },
  { label: 'Messages clients',     to: '/admin/messages',   icon: icons.messages },
  { label: 'Notifications',        to: '/admin/notifs',     icon: icons.notifications },
  { label: 'Paramètres',           to: '/admin/settings',   icon: icons.settings },
])

const adminNav = computed(() => [
  { section: 'Principal' },
  { label: 'Tableau de bord', to: '/admin/dashboard',       icon: icons.dashboard },
  { label: 'Domiciliataires', to: '/admin/domiciliataires', icon: icons.domiciliataires },
  { section: 'Compte' },
  { label: 'Notifications',   to: '/admin/notifs',          icon: icons.notifications },
  { label: 'Paramètres',      to: '/admin/settings',        icon: icons.settings },
])

const clientNav = computed(() => [
  { section: 'Mon Espace' },
  { label: 'Tableau de bord', to: '/client/dashboard',  icon: icons.dashboard },
  { label: 'Mes Documents',   to: '/client/documents',  icon: icons.documents },
  { label: 'Mon Contrat',     to: '/client/contrat',    icon: icons.contratClient },
  { section: 'Communication' },
  { label: 'Messages',        to: '/client/messages',   icon: icons.messages },
  { label: 'Notifications',   to: '/client/notifs',     icon: icons.notifications },
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