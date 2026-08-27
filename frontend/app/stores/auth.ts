// stores/auth.ts
// Central authentication store.
// Manages login, register, logout, and session restoration.

import { defineStore } from 'pinia'
import { useNotificationsStore } from '~/stores/notifs'

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
    photoUrl: string | null
    // Drives the "change your password?" prompt shown after login.
    mustChangePassword: boolean
    unreadNotificationsCount: number
    color: string
}

export const useAuthStore = defineStore('auth', () => {

    const user = ref<AuthUser | null>(null)
    const token = ref<string>('')
    const loading = ref<boolean>(false)
    const error = ref<string>('')
    const isPendingApproval = ref<boolean>(false)

    // Bumped on every confirmed photo upload/removal so components like
    // UserAvatar.vue can reset their load-failure state even when the
    // photo URL string itself doesn't change (it's stable per-user —
    // see User::getPhotoUrlAttribute() on the backend).
    const photoVersion = ref(0)

    const isAuthenticated = computed(() => !!user.value && !!token.value)
    const isAdmin = computed(() => user.value?.role === 'admin')
    const isDomiciliataire = computed(() => user.value?.role === 'domiciliataire')
    const isClient = computed(() => user.value?.role === 'client')
    const isInternal = computed(() => isAdmin.value || isDomiciliataire.value)

    function isClientRuntime(): boolean {
        return import.meta.client || typeof window !== 'undefined'
    }

    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    function getStorageKey(): string {
        const config = useRuntimeConfig()
        return (config.public.authStorageKey as string) ?? 'app_auth'
    }

    function getMaxAgeMs(): number {
        const config = useRuntimeConfig()
        const days = parseInt(config.public.sessionMaxAgeDays as string ?? '7', 10)
        return days * 24 * 60 * 60 * 1000
    }

    function buildInitialsFallback(u: any): string {
        const first = (u.nom ?? '').trim().charAt(0).toUpperCase()
        const second = (u.prenom ?? '').trim().charAt(0).toUpperCase()
        const initials = `${first}${second}`
        return initials || (u.email ?? 'U').charAt(0).toUpperCase()
    }

    function buildUser(u: any): AuthUser {
        return {
            id: u.id,
            name: `${u.nom ?? ''} ${u.prenom ?? ''}`.trim() || u.email,
            email: u.email,
            role: u.role ?? 'client',
            status: u.status ?? 'active',
            company: u.company ?? '',
            avatar: u.initials ?? buildInitialsFallback(u),
            photoUrl: u.photo_url ?? null,
            mustChangePassword: u.must_change_password ?? false,
            unreadNotificationsCount: Number(u.unread_notifications_count ?? 0),
            color:
                u.role === 'admin' ? '#ef4444' :
                    u.role === 'domiciliataire' ? '#c8a96e' : '#60a5fa',
        }
    }

    function saveToStorage(): void {
        if (!isClientRuntime()) return
        localStorage.setItem(getStorageKey(), JSON.stringify({
            user: user.value,
            token: token.value,
            savedAt: Date.now(),
        }))
    }

    async function login(payload: { email: string; password: string }): Promise<boolean> {
        const notifs = useNotificationsStore()
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<{ success: boolean; data: { user: any; token: string } }>(
                `${getApiBase()}/api/auth/login`,
                { method: 'POST', body: payload }
            )
            user.value = buildUser(res.data.user)
            token.value = res.data.token
            notifs.setUnreadCount(res.data.user?.unread_notifications_count ?? 0)
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

    function logout(): void {
        const notifs = useNotificationsStore()
        user.value = null
        token.value = ''
        error.value = ''
        isPendingApproval.value = false
        notifs.reset()
        if (isClientRuntime()) {
            localStorage.removeItem(getStorageKey())
        }
    }

    async function restoreSession(): Promise<void> {
        const notifs = useNotificationsStore()
        if (!isClientRuntime()) return
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

            // Fast path: paint instantly with the cached snapshot so there's
            // no blank/skeleton flash on reload.
            user.value = parsed.user ?? null
            token.value = parsed.token ?? ''
            notifs.setUnreadCount(parsed.user?.unread_notifications_count ?? 0)

            // Then silently revalidate against the server. Any field that
            // drifted since the cache was written — photoUrl being the
            // concrete case that bit us, but this guards role/status/name
            // too — gets corrected here instead of staying stale until the
            // next login or explicit mutation.
            if (token.value) {
                const res = await $fetch<{ success: boolean; data: any }>(
                    `${getApiBase()}/api/auth/me`,
                    { headers: { Authorization: `Bearer ${token.value}` } }
                )
                user.value = buildUser(res.data)
                notifs.setUnreadCount(res.data?.unread_notifications_count ?? 0)
                saveToStorage()
            }
        } catch {
            // If revalidation fails (expired/invalid token), drop the stale
            // session entirely rather than keep serving cached-but-wrong data.
            user.value = null
            token.value = ''
            localStorage.removeItem(getStorageKey())
        }
    }

    function setPhoto(photoUrl: string | null): void {
        if (!user.value) return
        user.value = { ...user.value, photoUrl }
        photoVersion.value++   // forces every UserAvatar instance to reset and retry
        saveToStorage()
    }

    /**
     * Called right after a successful password change so the "change
     * your password?" prompt never shows again for this account until
     * a future reset sets must_change_password back to true server-side.
     */
    function clearMustChangePassword(): void {
        if (!user.value) return
        user.value = { ...user.value, mustChangePassword: false }
        saveToStorage()
    }

    return {
        user, token, loading, error, isPendingApproval, photoVersion,
        isAuthenticated, isAdmin, isDomiciliataire, isClient, isInternal,
        login, register, logout, restoreSession, saveToStorage,
        setPhoto, clearMustChangePassword,
    }
})
