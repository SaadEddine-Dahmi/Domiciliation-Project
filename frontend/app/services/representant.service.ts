// app/services/representant.service.ts
// Handles all API calls for the single representant per entreprise.
// Backend routes:
//   GET    /api/entreprises/{id}/representant
//   POST   /api/entreprises/{id}/representant
//   PUT    /api/entreprises/{id}/representant
//   DELETE /api/entreprises/{id}/representant

import type { Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

// Read token from localStorage — same pattern as other services
function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
        const raw = localStorage.getItem('astfisc_auth')
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

export const representantService = {
    /**
     * GET /api/entreprises/{entrepriseId}/representant
     * Returns the single representant or null if not created yet.
     */
    get: (entrepriseId: number) =>
        $fetch<ApiSuccess<Representant | null>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { headers: authHeaders() }
        ),

    /**
     * POST /api/entreprises/{entrepriseId}/representant
     * Creates the representant — backend returns 422 if one already exists.
     */
    create: (entrepriseId: number, data: Omit<Representant, 'id' | 'entreprise_id' | 'created_at' | 'updated_at'>) =>
        $fetch<ApiSuccess<Representant>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'POST', headers: authHeaders(), body: data }
        ),

    /**
     * PUT /api/entreprises/{entrepriseId}/representant
     * Updates the existing representant — no {id} needed.
     */
    update: (entrepriseId: number, data: Partial<Representant>) =>
        $fetch<ApiSuccess<Representant>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'PUT', headers: authHeaders(), body: data }
        ),

    /**
     * DELETE /api/entreprises/{entrepriseId}/representant
     */
    remove: (entrepriseId: number) =>
        $fetch<ApiSuccess<{ message: string }>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'DELETE', headers: authHeaders() }
        ),
}