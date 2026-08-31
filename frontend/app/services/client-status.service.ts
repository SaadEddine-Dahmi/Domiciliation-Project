// app/services/client-status.service.ts
// Calls client portal status and history endpoints.

import { apiBase, authHeaders } from '~/services/http'

interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export const clientStatusService = {
  toggle(clientId: number, statut: 'actif' | 'inactif') {
    return $fetch<ApiSuccess<any>>(`${apiBase()}/api/clients/${clientId}/status`, {
      method: 'PATCH',
      headers: authHeaders(),
      body: { statut },
    })
  },

  history(clientId: number) {
    return $fetch<ApiSuccess<any[]>>(`${apiBase()}/api/clients/${clientId}/history`, {
      headers: authHeaders(),
    })
  },
}
