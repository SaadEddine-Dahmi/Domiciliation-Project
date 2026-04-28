// app/stores/auth.ts
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

    const user = ref<AuthUser | null>(null)
    const token = ref<string>('')
    const loading = ref<boolean>(false)
    const error = ref<string>('')
    const isPendingApproval = ref<boolean>(false)

    const isAuthenticated = computed(() => !!user.value && !!token.value)
    const isAdmin = computed(() => user.value?.role === 'admin')
    const isDomiciliataire = computed(() => user.value?.role === 'domiciliataire')
    const isClient = computed(() => user.value?.role === 'client')
    const isInternal = computed(() => isAdmin.value || isDomiciliataire.value)

    // ── Config helpers — read from runtimeConfig, never hardcode ──
    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    function getStorageKey(): string {
        const config = useRuntimeConfig()
        return (config.public.authStorageKey as string) ?? 'astfisc_auth'
    }

    function getMaxAgeMs(): number {
        const config = useRuntimeConfig()
        const days = parseInt(config.public.sessionMaxAgeDays as string ?? '7', 10)
        return days * 24 * 60 * 60 * 1000
    }

    // ── buildUser ──────────────────────────────────────────────
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

    // ── saveToStorage ──────────────────────────────────────────
    function saveToStorage(): void {
        if (!import.meta.client) return
        localStorage.setItem(getStorageKey(), JSON.stringify({
            user: user.value,
            token: token.value,
            savedAt: Date.now(),
        }))
    }

    // ── login ──────────────────────────────────────────────────
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
            error.value =
                e?.data?.message ??
                e?.data?.errors?.email?.[0] ??
                'Identifiants invalides'
            return false
        } finally {
            loading.value = false
        }
    }

    // ── register ───────────────────────────────────────────────
    async function register(payload: {
        nom: string; prenom?: string; email: string
        password: string; telephone?: string
    }): Promise<boolean> {
        loading.value = true
        error.value = ''
        isPendingApproval.value = false
        try {
            const res = await $fetch<{
                success: boolean; message?: string
                data: { user: any; token?: string }
            }>(
                `${getApiBase()}/api/auth/register`,
                { method: 'POST', body: { ...payload, role: 'domiciliataire' } }
            )
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

    // ── logout ─────────────────────────────────────────────────
    function logout(): void {
        user.value = null
        token.value = ''
        error.value = ''
        isPendingApproval.value = false
        if (import.meta.client) {
            localStorage.removeItem(getStorageKey())
        }
    }

    // ── restoreSession ─────────────────────────────────────────
    function restoreSession(): void {
        if (!import.meta.client) return
        if (user.value && token.value) return
        try {
            const raw = localStorage.getItem(getStorageKey())
            if (!raw) return
            const parsed = JSON.parse(raw)
            const ageMs = Date.now() - (parsed.savedAt ?? 0)
            if (ageMs > getMaxAgeMs()) {
                localStorage.removeItem(getStorageKey())
                return
            }
            user.value = parsed.user ?? null
            token.value = parsed.token ?? ''
        } catch {
            localStorage.removeItem(getStorageKey())
        }
    }

    return {
        user, token, loading, error, isPendingApproval,
        isAuthenticated, isAdmin, isDomiciliataire, isClient, isInternal,
        login, register, logout, restoreSession, saveToStorage,
    }
})