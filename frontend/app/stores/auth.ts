// app/stores/auth.ts
// Auth store with 3 roles: admin, domiciliataire, client
// Added: pending status handling on register
import { defineStore } from 'pinia'

export type Role = 'admin' | 'domiciliataire' | 'client'
export type Status = 'pending' | 'approved' | 'active' | 'rejected'

export interface AuthUser {
    id: number
    name: string
    email: string
    role: Role
    status: Status
    company: string
    avatar: string
    color: string
}

export const useAuthStore = defineStore('auth', () => {

    // ── State ──────────────────────────────────────────────
    const user = ref<AuthUser | null>(null)
    const token = ref<string>('')
    const loading = ref<boolean>(false)
    const error = ref<string>('')

    // Set to true after register when account is pending approval
    const isPendingApproval = ref<boolean>(false)

    // ── Getters ────────────────────────────────────────────
    const isAuthenticated = computed(() => !!user.value && !!token.value)
    const isAdmin = computed(() => user.value?.role === 'admin')
    const isDomiciliataire = computed(() => user.value?.role === 'domiciliataire')
    const isClient = computed(() => user.value?.role === 'client')
    const isInternal = computed(() => isAdmin.value || isDomiciliataire.value)

    // ── Helpers ────────────────────────────────────────────
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
            status: u.status ?? 'active',
            company: u.company ?? '',
            avatar: (u.nom ?? u.email ?? 'U').slice(0, 2).toUpperCase(),
            color:
                u.role === 'admin' ? '#ef4444' :
                    u.role === 'domiciliataire' ? '#c8a96e' : '#60a5fa',
        }
    }

    function saveToStorage(): void {
        if (!import.meta.client) return
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: user.value,
            token: token.value,
            savedAt: Date.now(),
        }))
    }

    // ── Actions ────────────────────────────────────────────

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
            // Backend returns 403 with message for pending/approved/rejected
            error.value =
                e?.data?.message ??
                e?.data?.errors?.email?.[0] ??
                'Identifiants invalides'
            return false
        } finally {
            loading.value = false
        }
    }

    async function register(payload: {
        nom: string
        prenom?: string
        email: string
        password: string
        telephone?: string
    }): Promise<boolean> {
        loading.value = true
        error.value = ''
        isPendingApproval.value = false
        try {
            const res = await $fetch<{
                success: boolean
                message?: string
                data: { user: any; token?: string }
            }>(
                `${getApiBase()}/api/auth/register`,
                { method: 'POST', body: { ...payload, role: 'domiciliataire' } }
            )

            // Backend returns no token when account is pending
            if (!res.data?.token) {
                isPendingApproval.value = true
                return true
            }

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

    function logout(): void {
        user.value = null
        token.value = ''
        error.value = ''
        isPendingApproval.value = false
        if (import.meta.client) localStorage.removeItem('astfisc_auth')
    }

    function restoreSession(): void {
        if (!import.meta.client) return
        if (user.value && token.value) return
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
        user, token, loading, error, isPendingApproval,
        isAuthenticated, isAdmin, isDomiciliataire, isClient, isInternal,
        login, register, logout, restoreSession, saveToStorage,
    }
})