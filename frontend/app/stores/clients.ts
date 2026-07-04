// stores/clients.ts
//
// Pinia store for the clients (Entreprise) list.
// Used by the contract wizard (step 2) and the clients management page.
//
// Data shape from GET /api/clients (ClientController::index()):
//   Entreprise {
//     representant  (hasOne)  — nom, prenom, cin, telephone, email, adresse,
//                               date_naissance, nom_complet (accessor)
//     clientUser    (hasOne)  — id, nom, prenom, email, telephone, role
//     documents[]             — with documentType
//   }
//
// Two-step client creation flow (wizard step 2 "nouveau client"):
//   1. create()           → POST /api/clients         → creates Entreprise + User
//   2. createRepresentant() → POST /api/entreprises/{id}/representant
//                                                     → creates Representant
//   Both must succeed for the PDF to render gérant fields. If step 2 fails,
//   the wizard shows a toast but continues (the representant can be added later
//   from the Clients management page).

import { defineStore } from 'pinia'
import type { Entreprise } from '~/types/entreprise'

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

// Shape expected by POST /api/clients
interface ClientCreatePayload {
    raison_sociale: string
    forme_juridique?: string
    adresse?: string
    ville?: string
    pays?: string
    capital?: number
    date_creation?: string
    statut?: string
    client_nom?: string
    client_prenom?: string
    client_email?: string
    client_password?: string
    client_telephone?: string
}

// Shape expected by POST /api/entreprises/{id}/representant
interface RepresentantCreatePayload {
    nom: string
    prenom?: string
    cin: string
    nationalite?: string
    date_naissance?: string
    adresse?: string
    telephone?: string
    email?: string
}

export const useClientsStore = defineStore('clients', () => {

    // ── State ─────────────────────────────────────────────────────────────────
    const items = ref<Entreprise[]>([])
    const loading = ref(false)
    const error = ref('')

    // ── Private helpers ───────────────────────────────────────────────────────

    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    /**
     * Authorization header from localStorage.
     * Key: 'app_auth' — written by the auth store on login.
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

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /api/clients
     *
     * Load all entreprises for the authenticated domiciliataire.
     * Called on wizard mount so data is available immediately for step 2.
     */
    async function fetchAll(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<ApiSuccess<Entreprise[]>>(
                `${getApiBase()}/api/clients`,
                { headers: authHeaders() }
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
     * POST /api/clients
     *
     * Create a new client (Entreprise + optional portal User account).
     * Does NOT create the representant — call createRepresentant() after.
     *
     * Prepends the new entreprise to the local list for immediate UI feedback.
     * Returns the full Entreprise object with representant = null initially.
     */
    async function create(payload: ClientCreatePayload): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: payload,
            }
        )
        const newEntreprise = res.data
        items.value.unshift(newEntreprise)
        return newEntreprise
    }

    /**
     * POST /api/entreprises/{id}/representant
     *
     * Create the gérant (representant) for a freshly created client.
     * Called immediately after create() in the wizard step 2 "nouveau client" flow.
     *
     * WHY this is separate from create():
     *   The representant endpoint is also used by the Clients management page
     *   to add or edit gérant details for existing clients. Keeping it separate
     *   avoids duplicating that logic.
     *
     * On success, updates the matching item in the local list so the wizard's
     * fillFromClient() can immediately read the representant data without
     * a full re-fetch.
     */
    async function createRepresentant(
        entrepriseId: number,
        payload: RepresentantCreatePayload
    ): Promise<void> {
        const res = await $fetch<ApiSuccess<any>>(
            `${getApiBase()}/api/entreprises/${entrepriseId}/representant`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: payload,
            }
        )
        // Update the local list so the wizard can read the representant immediately.
        const idx = items.value.findIndex(e => e.id === entrepriseId)
        if (idx !== -1) {
            items.value[idx] = { ...items.value[idx], representant: res.data }
        }
    }

    /**
     * PUT /api/clients/{id}
     *
     * Update an existing entreprise's fields and optionally the linked User.
     */
    async function update(
        id: number,
        payload: Partial<ClientCreatePayload> & { client_user?: Record<string, string> }
    ): Promise<Entreprise> {
        const res = await $fetch<ApiSuccess<Entreprise>>(
            `${getApiBase()}/api/clients/${id}`,
            {
                method: 'PUT',
                headers: authHeaders(),
                body: payload,
            }
        )
        const idx = items.value.findIndex(e => e.id === id)
        if (idx !== -1) items.value[idx] = res.data
        return res.data
    }

    /**
     * PUT /api/clients/{id}/password
     *
     * Reset the portal account password for the linked client user.
     * Sends both password and password_confirmation (required by backend).
     */
    async function updatePassword(
        id: number,
        password: string,
        passwordConfirmation: string
    ): Promise<void> {
        await $fetch(`${getApiBase()}/api/clients/${id}/password`, {
            method: 'PUT',
            headers: authHeaders(),
            body: { password, password_confirmation: passwordConfirmation },
        })
    }

    return { items, loading, error, fetchAll, create, createRepresentant, update, updatePassword }
})