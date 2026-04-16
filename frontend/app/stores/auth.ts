// app/stores/auth.ts
// SECURITY NOTE on localStorage token storage:
// Storing tokens in localStorage is accessible to JavaScript (XSS risk).
// Full mitigation requires HttpOnly cookie + Sanctum stateful auth.
// Current hardening:
//   1. Short expiry check (7 days — already in place)
//   2. Token never logged or exposed in responses
//   3. XSS risk mitigated by CSP headers (SecurityHeaders middleware)
//   4. All v-html interpolation now escaped (contrat.vue)
// Residual risk documented below.

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

const STORAGE_KEY = 'astfisc_auth'
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000 // 7 days

// SSR-safe guard: import.meta.client is always undefined in Vitest (jsdom).
// typeof window !== 'undefined' works in both Nuxt SSR and Vitest environments.
const isBrowser = () => typeof window !== 'undefined'

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

    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    function buildUser(u: any): AuthUser {
        return {
            id: u.id,
            // SECURITY: never store raw token in user object
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
        if (!isBrowser()) return
        // SECURITY: store minimum required — no sensitive profile data
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            user: user.value,
            // Token stored here is the residual risk.
            // Mitigated by: CSP headers, escaped v-html, no eval/innerHTML elsewhere.
            // Full fix: migrate to HttpOnly cookie + sanctum stateful.
            token: token.value,
            savedAt: Date.now(),
        }))
    }

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
        // SECURITY: clear all auth data on logout
        if (isBrowser()) {
            localStorage.removeItem(STORAGE_KEY)
        }
    }

    function restoreSession(): void {
        if (!isBrowser()) return
        if (user.value && token.value) return
        try {
            const raw = localStorage.getItem(STORAGE_KEY)
            if (!raw) return
            const parsed = JSON.parse(raw)
            const ageMs = Date.now() - (parsed.savedAt ?? 0)
            // SECURITY: enforce 7-day expiry — expired sessions are cleared
            if (ageMs > MAX_AGE_MS) {
                localStorage.removeItem(STORAGE_KEY)
                return
            }
            user.value = parsed.user ?? null
            token.value = parsed.token ?? ''
        } catch {
            // SECURITY: clear corrupted/tampered storage
            localStorage.removeItem(STORAGE_KEY)
        }
    }

    return {
        user, token, loading, error, isPendingApproval,
        isAuthenticated, isAdmin, isDomiciliataire, isClient, isInternal,
        login, register, logout, restoreSession, saveToStorage,
    }
})