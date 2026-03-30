// ============================================================
// middleware/auth.ts
// Gestion des accès selon les 3 rôles :
//   admin          → /admin/*  (lecture seule, pas données sensibles)
//   domiciliataire → /admin/*  (création, édition, suppression)
//   client         → /client/* (ses documents et contrat uniquement)
// SSR-safe : restauration session uniquement côté client
// ============================================================
import { useAuthStore } from '~/stores/auth'

export default defineNuxtRouteMiddleware((to) => {
    const auth = useAuthStore()

    // Pages publiques — aucune vérification
    if (['/login', '/register', '/'].includes(to.path)) return

    // SSR : impossible de lire localStorage, on laisse passer
    // app.vue gère la restauration côté client via onMounted
    if (!import.meta.client) return

    // Restaurer la session si pas encore fait
    auth.restoreSession()

    // Non authentifié → page de login
    if (!auth.isAuthenticated) return navigateTo('/login')

    // Client essaie d'accéder à /admin/* → son espace
    if (to.path.startsWith('/admin') && auth.isClient) {
        return navigateTo('/client/dashboard')
    }

    // Admin/Domiciliataire essaie d'accéder à /client/* → dashboard admin
    if (to.path.startsWith('/client') && auth.isInternal) {
        return navigateTo('/admin/dashboard')
    }
})