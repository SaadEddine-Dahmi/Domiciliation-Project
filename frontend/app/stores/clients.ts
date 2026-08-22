// stores/clients.ts
//
// Pinia store for the clients (Entreprise) list and single-client CRUD.
//
// Password handling: create() no longer accepts a password field — the
// backend always generates one and returns it once. resetPassword()
// mirrors that for a client who forgot theirs. Both must be surfaced to
// the domiciliataire in a copyable modal since they can't be retrieved
// again after the call returns.

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
     * Creates a new entreprise + its client portal account. No password
     * is sent — the backend generates one and returns it in
     * `generated_password`. Show this once in a modal; it's unrecoverable
     * afterwards.
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
    }): Promise<{ entreprise: Entreprise; generatedPassword: string }> {
        const res = await $fetch<ApiSuccess<Entreprise> & { generated_password: string }>(
            `${getApiBase()}/api/clients`,
            { method: 'POST', headers: authHeaders(), body: payload },
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
     * PATCH /api/clients/{id}/reset-password
     * Issues a brand-new system-generated password for a client who
     * forgot theirs. Returns it once, same handling as create().
     */
    async function resetPassword(id: number): Promise<string> {
        const res = await $fetch<{ success: boolean; message: string; generated_password: string }>(
            `${getApiBase()}/api/clients/${id}/reset-password`,
            { method: 'PATCH', headers: authHeaders() },
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
        fetchAll, fetchOne, create, update, resetPassword,
        createRepresentant, updateRepresentant,
    }
})