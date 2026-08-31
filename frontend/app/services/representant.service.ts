// app/services/representant.service.ts
// Calls client-company representative endpoints.

import { apiBase, authHeaders } from '~/services/http'
import type { Representant } from '~/types/entreprise'

interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

type RepresentantPayload = Omit<Representant, 'id' | 'entreprise_id' | 'created_at' | 'updated_at'>

export const representantService = {
  get: (entrepriseId: number) =>
    $fetch<ApiSuccess<Representant | null>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      headers: authHeaders(),
    }),

  create: (entrepriseId: number, data: RepresentantPayload) =>
    $fetch<ApiSuccess<Representant>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      method: 'POST',
      headers: authHeaders(),
      body: data,
    }),

  update: (entrepriseId: number, data: Partial<Representant>) =>
    $fetch<ApiSuccess<Representant>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      method: 'PUT',
      headers: authHeaders(),
      body: data,
    }),

  remove: (entrepriseId: number) =>
    $fetch<ApiSuccess<{ message: string }>>(`${apiBase()}/api/entreprises/${entrepriseId}/representant`, {
      method: 'DELETE',
      headers: authHeaders(),
    }),
}
