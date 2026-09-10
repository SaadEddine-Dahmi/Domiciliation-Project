import { defineStore } from 'pinia'

interface CountResponse {
  success: boolean
  data?: { unread_messages_count?: number }
}

function apiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}

  try {
    const config = useRuntimeConfig()
    const storageKey = (config.public.authStorageKey as string) ?? 'app_auth'
    const raw = localStorage.getItem(storageKey)
    if (!raw) return {}

    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch {
    return {}
  }
}

export const useMessagesStore = defineStore('messages', () => {
  const unreadCount = ref(0)
  const loading = ref(false)
  const error = ref('')

  async function refreshUnreadCount(): Promise<void> {
    if (!import.meta.client) return

    loading.value = true
    error.value = ''
    try {
      const res = await $fetch<CountResponse>(
        `${apiBase()}/api/messages/unread-count`,
        { headers: authHeaders() }
      )
      unreadCount.value = Number(res.data?.unread_messages_count ?? 0)
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Erreur de chargement des messages'
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
