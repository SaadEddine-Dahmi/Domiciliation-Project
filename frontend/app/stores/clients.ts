// app/stores/clients.ts
// Stores tenant client company state and client account actions.

import { defineStore } from 'pinia'
import { apiBase, authHeaders } from '~/services/http'
import type { Entreprise, Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

interface ClientCreatePayload {
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
}

export const useClientsStore = defineStore('clients', () => {
  const items = ref<Entreprise[]>([])
  const loading = ref(false)
  const error = ref('')

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = ''

    try {
      const res = await $fetch<ApiSuccess<Entreprise[]>>(`${apiBase()}/api/clients`, {
        headers: authHeaders(),
        query: { per_page: 100 },
      })

      items.value = res.data ?? []
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Erreur chargement clients'
      items.value = []
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number): Promise<Entreprise> {
    const res = await $fetch<ApiSuccess<Entreprise>>(`${apiBase()}/api/clients/${id}`, {
      headers: authHeaders(),
    })

    return res.data
  }

  async function create(payload: ClientCreatePayload): Promise<{ entreprise: Entreprise; generatedPassword?: string }> {
    const body: Record<string, unknown> = { ...payload }
    if (!body.client_password) delete body.client_password

    const res = await $fetch<ApiSuccess<Entreprise> & { generated_password?: string }>(`${apiBase()}/api/clients`, {
      method: 'POST',
      headers: authHeaders(),
      body,
    })

    items.value.unshift(res.data)

    return { entreprise: res.data, generatedPassword: res.generated_password }
  }

  async function update(id: number, payload: Partial<Entreprise>): Promise<Entreprise> {
    const res = await $fetch<ApiSuccess<Entreprise>>(`${apiBase()}/api/clients/${id}`, {
      method: 'PUT',
      headers: authHeaders(),
      body: payload,
    })

    const index = items.value.findIndex(client => client.id === id)
    if (index !== -1) items.value[index] = res.data

    return res.data
  }

  async function toggleStatus(id: number, statut: 'actif' | 'inactif'): Promise<Entreprise> {
    const res = await $fetch<ApiSuccess<Entreprise>>(`${apiBase()}/api/clients/${id}/status`, {
      method: 'PATCH',
      headers: authHeaders(),
      body: { statut },
    })

    const index = items.value.findIndex(client => client.id === id)
    if (index !== -1) items.value[index] = res.data

    return res.data
  }

  async function resetPassword(id: number): Promise<string> {
    const res = await $fetch<{ success: boolean; message: string; generated_password: string }>(
      `${apiBase()}/api/clients/${id}/regenerate-password`,
      { method: 'POST', headers: authHeaders() },
    )

    return res.generated_password
  }

  async function createRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
    const res = await $fetch<ApiSuccess<Representant>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      method: 'POST',
      headers: authHeaders(),
      body: data,
    })

    return res.data
  }

  async function updateRepresentant(entrepriseId: number, data: Partial<Representant>): Promise<Representant> {
    const res = await $fetch<ApiSuccess<Representant>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      method: 'PUT',
      headers: authHeaders(),
      body: data,
    })

    return res.data
  }

  return {
    items,
    loading,
    error,
    fetchAll,
    fetchOne,
    create,
    update,
    toggleStatus,
    resetPassword,
    createRepresentant,
    updateRepresentant,
  }
})
