// ============================================================
// stores/auth.ts
// Store d'authentification avec 3 rôles :
//   - admin        : voit tous les domiciliataires et clients
//   - domiciliataire : gère ses propres clients et contrats
//   - client       : voit uniquement ses documents et contrat
// ============================================================
import { defineStore } from 'pinia'

export type Role = 'admin' | 'domiciliataire' | 'client'

export interface AuthUser {
    id: number
    name: string
    email: string
    role: Role
    company: string
    avatar: string
    color: string
}

export const useAuthStore = defineStore('auth', () => {

    // ── State ────────────────────────────────────────────────
    const user = ref<AuthUser | null>(null)
    const token = ref<string>('')
    const loading = ref<boolean>(false)
    const error = ref<string>('')

    // ── Getters ──────────────────────────────────────────────

    /** Authentifié si user ET token présents */
    const isAuthenticated = computed(() => !!user.value && !!token.value)

    /** Super admin — voit tout, sans données sensibles */
    const isAdmin = computed(() => user.value?.role === 'admin')

    /** Domiciliataire — gère ses clients, contrats, documents */
    const isDomiciliataire = computed(() => user.value?.role === 'domiciliataire')

    /** Client — voit uniquement ses propres documents et contrat */
    const isClient = computed(() => user.value?.role === 'client')

    /** Admin OU domiciliataire — accès au dashboard /admin */
    const isInternal = computed(() => isAdmin.value || isDomiciliataire.value)

    // Alias pour compatibilité avec les pages existantes
    const isAdmin2 = computed(() => isInternal.value)

    // ── Helpers ──────────────────────────────────────────────
    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    function buildUser(u: any): AuthUser {
        return {
            id: u.id,
            name: `${u.nom ?? ''} ${u.prenom ?? ''}`.trim() || u.email,
            email: u.email,
            role: u.role ?? 'client',
            company: u.company ?? '',
            avatar: (u.nom ?? u.email ?? 'U').slice(0, 2).toUpperCase(),
            color: u.role === 'admin'
                ? '#ef4444'
                : u.role === 'domiciliataire'
                    ? '#c8a96e'
                    : '#60a5fa',
        }
    }

    /** Persiste la session dans localStorage avec timestamp */
    function saveToStorage(): void {
        if (!import.meta.client) return
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: user.value,
            token: token.value,
            savedAt: Date.now(),
        }))
    }

    // ── Actions ──────────────────────────────────────────────

    /** Login via POST /api/auth/login */
    async function login(payload: { email: string; password: string }): Promise<boolean> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<{ success: boolean; data: { user: any; token: string } }>(
                `${getApiBase()}/api/auth/login`,
                { method: 'POST', body: payload }
            )
            user.value = buildUser(res.data.user)
            token.value = res.data.token
            saveToStorage()
            return true
        } catch (e: any) {
            error.value = e?.data?.errors?.email?.[0] ?? e?.data?.message ?? 'Identifiants invalides'
            return false
        } finally {
            loading.value = false
        }
    }

    /** Register domiciliataire via POST /api/auth/register */
    async function register(payload: {
        nom: string; prenom?: string; email: string; password: string; telephone?: string
    }): Promise<boolean> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<{ success: boolean; data: { user: any; token: string } }>(
                `${getApiBase()}/api/auth/register`,
                { method: 'POST', body: { ...payload, role: 'domiciliataire' } }
            )
            user.value = buildUser(res.data.user)
            token.value = res.data.token
            saveToStorage()
            return true
        } catch (e: any) {
            error.value = e?.data?.errors
                ? Object.values(e.data.errors).flat().join(' · ')
                : e?.data?.message ?? "Erreur lors de l'inscription"
            return false
        } finally {
            loading.value = false
        }
    }

    /** Déconnexion — vide state et localStorage */
    function logout(): void {
        user.value = null
        token.value = ''
        error.value = ''
        if (import.meta.client) localStorage.removeItem('astfisc_auth')
    }

    /**
     * Restaure la session depuis localStorage
     * - SSR-safe (import.meta.client guard)
     * - Expiration 7 jours
     */
    function restoreSession(): void {
        if (!import.meta.client) return
        if (user.value && token.value) return  // déjà restauré
        try {
            const raw = localStorage.getItem('astfisc_auth')
            if (!raw) return
            const parsed = JSON.parse(raw)
            const ageMs = Date.now() - (parsed.savedAt ?? 0)
            if (ageMs > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem('astfisc_auth')
                return
            }
            user.value = parsed.user ?? null
            token.value = parsed.token ?? ''
        } catch {
            localStorage.removeItem('astfisc_auth')
        }
    }

    return {
        // state
        user, token, loading, error,
        // getters
        isAuthenticated, isAdmin, isDomiciliataire, isClient, isInternal,
        // actions
        login, register, logout, restoreSession, saveToStorage,
    }
})