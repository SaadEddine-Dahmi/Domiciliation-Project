// stores/clients.ts
//
// Pinia store for the clients (Entreprise) list and single-client CRUD.
//
// Password handling on create(): client_password is optional in the
// payload. If provided, the backend uses it as-is; if omitted, the
// backend generates one and returns it once via generatedPassword —
// undefined when the domiciliataire supplied their own, since it's
// already known to them and shouldn't be echoed back.

import { defineStore } from 'pinia'
import type { Entreprise, Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

export const useClientsStore = defineStore('clients', () => {

    const items = ref<Entreprise[]>([])
    const loading = ref(false)
    const error = ref('')

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

    async function fetchAll(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<ApiSuccess<Entreprise[]>>(
                `${getApiBase()}/api/clients`,
                { headers: authHeaders() },
            )
            items.value = res.data ?? []
        } catch (e: any) {
            error.value = e?.data?.message ?? 'Erreur chargement clients'
            items.value = []
        } finally {
            loading.value = false
        }
    }

    async function fetchOne(id: number): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients/${id}`,
            { headers: authHeaders() },
        )
        return res.data
    }

    /**
     * POST /api/clients
     * Creates a new entreprise + its client portal account.
     *
     * client_password is optional — pass it to let the domiciliataire
     * choose the password themselves; omit it (or pass an empty string)
     * to let the backend generate one.
     *
     * Returns generatedPassword only when the backend actually generated
     * it — undefined when the caller supplied their own. The UI should
     * only show the "here's the password, copy it" modal when this is
     * defined.
     *
     * Note: the backend now always sets the new client's status to
     * 'actif' on creation (see ClientController@store) — nothing needs
     * to be sent from here for that.
     */
    async function create(payload: {
        raison_sociale: string
        forme_juridique?: string
        adresse?: string
        ville?: string
        pays?: string
        capital?: number
        date_creation?: string
        client_nom: string
        client_prenom?: string
        client_email: string
        client_telephone?: string
        client_password?: string
    }): Promise<{ entreprise: Entreprise; generatedPassword?: string }> {
        // Never send an empty string explicitly if it's blank — let the
        // key be absent so Laravel's 'nullable' rule treats it as unset
        // rather than as an empty-string value to validate against 'min:8'.
        const body: Record<string, unknown> = { ...payload }
        if (!body.client_password) delete body.client_password

        const res = await $fetch<ApiSuccess<Entreprise> & { generated_password?: string }>(
            `${getApiBase()}/api/clients`,
            { method: 'POST', headers: authHeaders(), body },
        )
        items.value.unshift(res.data)
        return { entreprise: res.data, generatedPassword: res.generated_password }
    }

    async function update(id: number, payload: Partial<Entreprise>): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients/${id}`,
            { method: 'PUT', headers: authHeaders(), body: payload },
        )
        const idx = items.value.findIndex(c => c.id === id)
        if (idx !== -1) items.value[idx] = res.data
        return res.data
    }

    /**
     * PATCH /api/clients/{id}/status
     * Toggles a client's portal access between 'actif' and 'inactif'.
     * Wired to the new status button on the client detail page.
     */
    async function toggleStatus(id: number, statut: 'actif' | 'inactif'): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients/${id}/status`,
            { method: 'PATCH', headers: authHeaders(), body: { statut } },
        )
        const idx = items.value.findIndex(c => c.id === id)
        if (idx !== -1) items.value[idx] = res.data
        return res.data
    }

    /**
     * POST /api/clients/{id}/regenerate-password
     * Always generates a new password server-side — used for a client
     * who's locked out. Returns it once for display in a copy modal.
     */
    async function resetPassword(id: number): Promise<string> {
        const res = await $fetch<{ success: boolean; message: string; generated_password: string }>(
            `${getApiBase()}/api/clients/${id}/regenerate-password`,
            { method: 'POST', headers: authHeaders() },
        )
        return res.generated_password
    }

    async function createRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
        const res = await $fetch<ApiSuccess<Representant>>(
            `${getApiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'POST', headers: authHeaders(), body: data },
        )
        return res.data
    }

    async function updateRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
        const res = await $fetch<ApiSuccess<Representant>>(
            `${getApiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'PUT', headers: authHeaders(), body: data },
        )
        return res.data
    }

    return {
        items, loading, error,
        fetchAll, fetchOne, create, update, toggleStatus, resetPassword,
        createRepresentant, updateRepresentant,
    }
})
