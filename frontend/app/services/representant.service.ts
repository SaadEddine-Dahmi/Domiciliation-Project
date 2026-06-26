// services/representant.service.ts
// HTTP client for the single representant per entreprise.
//
// Backend routes:
//   GET    /api/entreprises/{id}/representant
//   POST   /api/entreprises/{id}/representant
//   PUT    /api/entreprises/{id}/representant
//   DELETE /api/entreprises/{id}/representant

import type { Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
    success:  boolean
    data:     T
    message?: string
}

// typeof window check works in both Nuxt SSR and Vitest (jsdom) environments.
// import.meta.client is undefined in Vitest, so we avoid it here.
const isBrowser = () => typeof window !== 'undefined'

function authHeaders(): Record<string, string> {
    if (!isBrowser()) return {}
    try {
        const raw = localStorage.getItem('app_auth')
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
    } catch { return {} }
}

function apiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

export const representantService = {
    /** GET /api/entreprises/{id}/representant — returns null if not yet created */
    get: (entrepriseId: number) =>
        $fetch<ApiSuccess<Representant | null>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { headers: authHeaders() }
        ),

    /** POST /api/entreprises/{id}/representant — 422 if one already exists */
    create: (
        entrepriseId: number,
        data: Omit<Representant, 'id' | 'entreprise_id' | 'created_at' | 'updated_at'>
    ) =>
        $fetch<ApiSuccess<Representant>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'POST', headers: authHeaders(), body: data }
        ),

    /** PUT /api/entreprises/{id}/representant — no {id} needed, 1-to-1 */
    update: (entrepriseId: number, data: Partial<Representant>) =>
        $fetch<ApiSuccess<Representant>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'PUT', headers: authHeaders(), body: data }
        ),

    /** DELETE /api/entreprises/{id}/representant */
    remove: (entrepriseId: number) =>
        $fetch<ApiSuccess<{ message: string }>>(
            `${apiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'DELETE', headers: authHeaders() }
        ),
}