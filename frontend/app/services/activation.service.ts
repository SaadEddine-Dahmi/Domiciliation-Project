// services/activation.service.ts
// Super admin endpoints to manage domiciliataire account activation.
//
// Backend routes:
//   GET  /api/admin/users/pending
//   POST /api/admin/users/{id}/approve
//   POST /api/admin/users/{id}/reject

import type { PendingUser } from '~/types/user'

interface ApiSuccess<T> {
    success: boolean
    data?: T
    message?: string
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

function apiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

export const activationService = {
    /** GET /api/admin/users/pending — returns all accounts awaiting approval */
    getPending: () =>
        $fetch<ApiSuccess<PendingUser[]>>(
            `${apiBase()}/api/admin/users/pending`,
            { headers: authHeaders() }
        ),

    /** POST /api/admin/users/{id}/approve — body: { activation_date: 'YYYY-MM-DD' } */
    approve: (userId: number, activationDate: string) =>
        $fetch<ApiSuccess<{ message: string }>>(
            `${apiBase()}/api/admin/users/${userId}/approve`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: { activation_date: activationDate },
            }
        ),

    /** POST /api/admin/users/{id}/reject — body: { reason: string } */
    reject: (userId: number, reason: string) =>
        $fetch<ApiSuccess<{ message: string }>>(
            `${apiBase()}/api/admin/users/${userId}/reject`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: { reason },
            }
        ),
}