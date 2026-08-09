// stores/clients.ts
//
// Pinia store for the clients (Entreprise) list and single-client CRUD.
// Used by the clients list page, the client detail page, and the contract
// wizard (which reads representant fields for auto-fill).
//
// Data shape (from ClientController):
//   Entreprise with:
//     - representant  (hasOne Representant) — nom, prenom, cin, telephone, email, adresse
//     - clientUser    (belongsTo User)      — id, nom, prenom, email, telephone, role
//     - documents     (hasMany Document)    — with documentType

import { defineStore } from 'pinia'
import type { Entreprise, Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

export const useClientsStore = defineStore('clients', () => {

    // ── State ─────────────────────────────────────────────
    const items = ref<Entreprise[]>([])
    const loading = ref(false)
    const error = ref('')

    // ── Private helpers ───────────────────────────────────

    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    /**
     * Build the Authorization header from localStorage.
     * Key: 'app_auth' — the single storage key used across the whole app.
     */
    function authHeaders(): Record<string, string> {
        if (!import.meta.client) return {}
        try {
            const raw = localStorage.getItem('app_auth')
            if (!raw) return {}
            const parsed = JSON.parse(raw)
            return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
        } catch { return {} }
    }

    // ── Actions ───────────────────────────────────────────

    /**
     * GET /api/clients
     * Loads all entreprises for the authenticated domiciliataire.
     */
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

    /**
     * GET /api/clients/{id}
     * Loads a single client — used by the detail page after an edit,
     * to refresh with the latest representant data.
     */
    async function fetchOne(id: number): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients/${id}`,
            { headers: authHeaders() },
        )
        return res.data
    }

    /**
     * POST /api/clients
     * Creates a new entreprise. The representant is NOT included here —
     * call createRepresentant() right after with the returned id.
     */
    async function create(payload: Partial<Entreprise>): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients`,
            { method: 'POST', headers: authHeaders(), body: payload },
        )
        items.value.unshift(res.data)
        return res.data
    }

    /**
     * PUT /api/clients/{id}
     * Updates entreprise-level fields (currently just raison_sociale from
     * the edit modals, but accepts any Entreprise field the backend allows).
     */
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
     * PUT /api/clients/{id}/password
     * Resets the password of the linked client portal account.
     */
    async function updatePassword(id: number, password: string, password_confirmation: string): Promise<void> {
        await $fetch(`${getApiBase()}/api/clients/${id}/password`, {
            method: 'PUT',
            headers: authHeaders(),
            body: { password, password_confirmation },
        })
    }

    /**
     * POST /api/entreprises/{id}/representant
     * Creates the (single) representant for an entreprise. Fails with 422
     * if one already exists — callers should check client.representant first.
     */
    async function createRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
        const res = await $fetch<ApiSuccess<Representant>>(
            `${getApiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'POST', headers: authHeaders(), body: data },
        )
        return res.data
    }

    /**
     * PUT /api/entreprises/{id}/representant
     * Updates the existing representant. No {id} needed — it's 1-to-1.
     */
    async function updateRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
        const res = await $fetch<ApiSuccess<Representant>>(
            `${getApiBase()}/api/entreprises/${entrepriseId}/representant`,
            { method: 'PUT', headers: authHeaders(), body: data },
        )
        return res.data
    }

    return {
        items, loading, error,
        fetchAll, fetchOne, create, update, updatePassword,
        createRepresentant, updateRepresentant,
    }
})