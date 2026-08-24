import { defineStore } from 'pinia'

interface CountResponse {
    success: boolean
    data?: { unread_notifications_count?: number }
}

function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
        const raw = localStorage.getItem('app_auth')
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
    } catch {
        return {}
    }
}

function apiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

export const useNotificationsStore = defineStore('notifications', () => {
    const unreadCount = ref(0)
    const loading = ref(false)
    const error = ref('')

    async function refreshUnreadCount(): Promise<void> {
        if (!import.meta.client) return

        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<CountResponse>(
                `${apiBase()}/api/notifications/unread-count`,
                { headers: authHeaders() }
            )
            unreadCount.value = Number(res.data?.unread_notifications_count ?? 0)
        } catch (e: any) {
            error.value = e?.data?.message ?? 'Erreur de chargement des notifications'
            unreadCount.value = 0
        } finally {
            loading.value = false
        }
    }

    function markOneRead(): void {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
    }

    function markAllReadLocally(): void {
        unreadCount.value = 0
    }

    function setUnreadCount(count: number): void {
        unreadCount.value = Math.max(0, Number(count) || 0)
    }

    function reset(): void {
        unreadCount.value = 0
        error.value = ''
        loading.value = false
    }

    return {
        unreadCount,
        loading,
        error,
        refreshUnreadCount,
        markOneRead,
        markAllReadLocally,
        setUnreadCount,
        reset,
    }
})
