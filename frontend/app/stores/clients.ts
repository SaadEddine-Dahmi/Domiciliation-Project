// stores/clients.ts
//
// Pinia store for the clients (Entreprise) list.
// Used by the contract wizard and the clients management page.
//
// Data shape (from ClientController::index()):
//   Entreprise with:
//     - representant  (hasOne Representant) — nom, prenom, cin, telephone, email, adresse, nom_complet
//     - clientUser    (belongsTo User)      — id, nom, prenom, email, telephone, role
//     - documents     (hasMany Document)    — with documentType
//
// The contract wizard uses representant fields to auto-fill:
//   gerantNom    ← representant.nom_complet
//   gerantCIN    ← representant.cin
//   tel          ← representant.telephone
//   email        ← representant.email
//   adressePerso ← representant.adresse

import { defineStore } from 'pinia'
import type { Entreprise } from '~/types/entreprise'

// Standard API envelope returned by ClientController
interface ApiSuccess<T> {
    success: boolean
    data:    T
    message?: string
}

export const useClientsStore = defineStore('clients', () => {

    // ── State ─────────────────────────────────────────────
    const items   = ref<Entreprise[]>([])
    const loading = ref(false)
    const error   = ref('')

    // ── Private helpers ───────────────────────────────────

    /** Read the configured API base URL from Nuxt runtime config */
    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    /**
     * Build the Authorization header from localStorage.
     * Key: 'app_auth' — written by the auth store on successful login.
     * Returns {} when called server-side (no window object).
     */
    function authHeaders(): Record<string, string> {
        if (!import.meta.client) return {}
        try {
            const raw = localStorage.getItem('app_auth') // same key as auth store
            if (!raw) return {}
            const parsed = JSON.parse(raw)
            return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
        } catch { return {} }
    }

    // ── Actions ───────────────────────────────────────────

    /**
     * GET /api/clients
     *
     * Loads all entreprises for the authenticated domiciliataire.
     * Each record includes representant + clientUser (set by ClientController).
     *
     * Called on mount in the contract wizard — data is available
     * immediately when the user picks an entreprise from the dropdown.
     */
    async function fetchAll(): Promise<void> {
        loading.value = true
        error.value   = ''
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

    return { items, loading, error, fetchAll }
})