<!-- app/app.vue -->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'
import { h, defineComponent } from 'vue'

const auth   = useAuthStore()
const router = useRouter()

onMounted(() => {
  auth.restoreSession()
  // useTheme().init() is still called here to keep the reactive ref in sync.
  // The actual class is applied earlier by the theme.client.ts plugin to
  // prevent the flash of wrong theme on refresh.
  useTheme().init()
})

// ── Icons ─────────────────────────────────────────────────
const IcoDashboard  = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('rect',{x:'3',y:'3',width:'7',height:'7'}),h('rect',{x:'14',y:'3',width:'7',height:'7'}),h('rect',{x:'3',y:'14',width:'7',height:'7'}),h('rect',{x:'14',y:'14',width:'7',height:'7'})]) })
const IcoClients    = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'}),h('circle',{cx:'9',cy:'7',r:'4'}),h('path',{d:'M23 21v-2a4 4 0 0 0-3-3.87'}),h('path',{d:'M16 3.13a4 4 0 0 1 0 7.75'})]) })
const IcoDocuments  = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'}),h('polyline',{points:'14 2 14 8 20 8'}),h('line',{x1:'16',y1:'13',x2:'8',y2:'13'}),h('line',{x1:'16',y1:'17',x2:'8',y2:'17'}),h('polyline',{points:'10 9 9 9 8 9'})]) })
const IcoInvoice    = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('rect',{x:'1',y:'4',width:'22',height:'16',rx:'2',ry:'2'}),h('line',{x1:'1',y1:'10',x2:'23',y2:'10'})]) })
const IcoReceipt    = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z'}),h('line',{x1:'9',y1:'9',x2:'15',y2:'9'}),h('line',{x1:'9',y1:'13',x2:'15',y2:'13'})]) })
const IcoContract   = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'}),h('polyline',{points:'14 2 14 8 20 8'}),h('path',{d:'M9 15l2 2 4-4'})]) })
const IcoContracts  = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'})]) })
const IcoScan       = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M3 7V5a2 2 0 0 1 2-2h2'}),h('path',{d:'M17 3h2a2 2 0 0 1 2 2v2'}),h('path',{d:'M21 17v2a2 2 0 0 1-2 2h-2'}),h('path',{d:'M7 21H5a2 2 0 0 1-2-2v-2'}),h('rect',{x:'7',y:'7',width:'10',height:'10'})]) })
const IcoArticles   = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7'}),h('path',{d:'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z'})]) })
const IcoMessages   = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'})]) })
const IcoNotifs     = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9'}),h('path',{d:'M13.73 21a2 2 0 0 1-3.46 0'})]) })
const IcoSettings   = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('circle',{cx:'12',cy:'12',r:'3'}),h('path',{d:'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'})]) })
const IcoDomici     = defineComponent({ render: () => h('svg', { width:16,height:16,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'1.8','stroke-linecap':'round','stroke-linejoin':'round' }, [h('path',{d:'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'}),h('polyline',{points:'9 22 9 12 15 12 15 22'})]) })

// ── Nav ───────────────────────────────────────────────────
const domiciNav = computed(() => [
  { section: 'Principal' },
  { icon: IcoDashboard, label: 'Tableau de bord',      to: '/admin/dashboard' },
  { icon: IcoClients,   label: 'Mes Clients',           to: '/admin/clients' },
  { icon: IcoDocuments, label: 'Documents',             to: '/admin/documents' },
  { icon: IcoInvoice,   label: 'Factures & Paiements',  to: '/admin/paiements' },
  { icon: IcoReceipt,   label: 'Toutes les Factures',   to: '/admin/factures' },

  { section: 'Outils' },
  { icon: IcoContract,  label: 'Nouveau Contrat', highlight: true, action: () => router.push('/admin/contrat?new=1') },
  { icon: IcoContracts, label: 'Mes Contrats',          to: '/admin/contrats' },
  { icon: IcoScan,      label: 'Scanner / Importer',    action: () => router.push('/admin/scan') },
  { icon: IcoArticles,  label: 'Articles',              to: '/admin/articles' },

  { section: 'Communication' },
  { icon: IcoMessages,  label: 'Messages clients',      to: '/admin/messages' },
  { icon: IcoNotifs,    label: 'Notifications',         to: '/admin/notifs' },
  { icon: IcoSettings,  label: 'Paramètres',            to: '/admin/settings' },
])

const adminNav = computed(() => [
  { section: 'Principal' },
  { icon: IcoDashboard, label: 'Tableau de bord',   to: '/admin/dashboard' },
  { icon: IcoDomici,    label: 'Domiciliataires',   to: '/admin/domiciliataires' },

  { section: 'Compte' },
  { icon: IcoNotifs,    label: 'Notifications',     to: '/admin/notifs' },
  { icon: IcoSettings,  label: 'Paramètres',        to: '/admin/parametres' },
])

const clientNav = computed(() => [
  { section: 'Mon Espace' },
  { icon: IcoDashboard, label: 'Tableau de bord',   to: '/client/dashboard' },
  { icon: IcoDocuments, label: 'Mes Documents',     to: '/client/documents' },
  { icon: IcoContracts, label: 'Mon Contrat',       to: '/client/contrat' },

  { section: 'Communication' },
  { icon: IcoMessages,  label: 'Messages',          to: '/client/messages' },
  { icon: IcoNotifs,    label: 'Notifications',     to: '/client/notifs' },
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