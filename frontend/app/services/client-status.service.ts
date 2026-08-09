// services/client-status.service.ts
//
// Dedicated service for toggling a client's portal access on/off, kept
// separate from the general client profile update to avoid the status
// ever being overwritten unintentionally by an unrelated edit.

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
        const raw = localStorage.getItem('app_auth')
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
    } catch { return {} }
}

export const clientStatusService = {
    /** PATCH /api/clients/{id}/status — body: { statut: 'actif' | 'inactif' } */
    toggle(clientId: number, statut: 'actif' | 'inactif') {
        return $fetch<ApiSuccess<any>>(`${getApiBase()}/api/clients/${clientId}/status`, {
            method: 'PATCH',
            headers: authHeaders(),
            body: { statut },
        })
    },

    /** GET /api/clients/{id}/history — full audit trail, newest first */
    history(clientId: number) {
        return $fetch<ApiSuccess<any[]>>(`${getApiBase()}/api/clients/${clientId}/history`, {
            headers: authHeaders(),
        })
    },
}