// stores/clients.ts
import { defineStore } from 'pinia'

export interface ClientUser {
    id: number
    nom: string
    prenom: string
    email: string
    telephone: string
    role: string
}

export interface Client {
    id: number
    raison_sociale: string
    forme_juridique: string | null
    adresse: string | null
    ville: string | null
    pays: string | null
    capital: string | number | null
    date_creation: string | null
    statut: string | null
    client_user: ClientUser | null
}

export interface ClientCreatePayload {
    // Entreprise
    raison_sociale: string
    forme_juridique?: string
    adresse?: string
    ville?: string
    pays?: string
    capital?: number
    date_creation?: string
    statut?: string
    // Compte utilisateur client
    client_nom: string
    client_prenom?: string
    client_email: string
    client_password: string
    client_telephone?: string
}

export const useClientsStore = defineStore('clients', () => {
    const items = ref<Client[]>([])
    const loading = ref(false)
    const error = ref('')

    function getApiBase() {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    function authHeaders(): Record<string, string> {
        if (!import.meta.client) return {}
        try {
            const raw = localStorage.getItem('astfisc_auth')
            if (!raw) return {}
            const parsed = JSON.parse(raw)
            const token = parsed?.token ?? ''
            return token ? { Authorization: `Bearer ${token}` } : {}
        } catch {
            return {}
        }
    }

    async function fetchAll() {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<{ success: boolean; data: Client[] }>(
                `${getApiBase()}/api/clients`,
                { headers: authHeaders() }
            )
            items.value = res.data
        } catch (e: any) {
            error.value = e?.data?.message ?? 'Erreur chargement clients'
        } finally {
            loading.value = false
        }
    }

    async function create(payload: ClientCreatePayload): Promise<Client> {
        // 1. Créer le compte user client
        const userRes = await $fetch<{ success: boolean; data: { user: ClientUser; token: string } }>(
            `${getApiBase()}/api/auth/register`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: {
                    nom: payload.client_nom,
                    prenom: payload.client_prenom ?? '',
                    email: payload.client_email,
                    password: payload.client_password,
                    telephone: payload.client_telephone ?? '',
                    role: 'client',
                },
            }
        )

        const clientUserId = userRes.data.user.id

        // 2. Créer l'entreprise liée
        const entRes = await $fetch<{ data: Client }>(
            `${getApiBase()}/api/entreprises`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: {
                    raison_sociale: payload.raison_sociale,
                    forme_juridique: payload.forme_juridique ?? null,
                    adresse: payload.adresse ?? null,
                    ville: payload.ville ?? null,
                    pays: payload.pays ?? 'Maroc',
                    capital: payload.capital ?? null,
                    date_creation: payload.date_creation ?? null,
                    statut: payload.statut ?? 'actif',
                    client_user_id: clientUserId,
                },
            }
        )

        const newClient = entRes.data
        items.value.unshift(newClient)
        return newClient
    }

    async function update(id: number, payload: Partial<Client> & { client_user?: Partial<ClientUser> }): Promise<Client> {
        const res = await $fetch<{ success: boolean; data: Client }>(
            `${getApiBase()}/api/clients/${id}`,
            {
                method: 'PUT',
                headers: authHeaders(),
                body: payload,
            }
        )
        const idx = items.value.findIndex((c) => c.id === id)
        if (idx !== -1) items.value[idx] = res.data
        return res.data
    }

    async function updatePassword(id: number, password: string, password_confirmation: string) {
        await $fetch(`${getApiBase()}/api/clients/${id}/password`, {
            method: 'PUT',
            headers: authHeaders(),
            body: { password, password_confirmation },
        })
    }

    return { items, loading, error, fetchAll, create, update, updatePassword }
})