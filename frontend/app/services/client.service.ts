// app/services/client.service.ts
// Calls admin pending-user approval endpoints.

import { apiBase, authHeaders } from '~/services/http'
import type { PendingUser } from '~/types/user'

interface ApiSuccess<T> {
  success: boolean
  data?: T
  message?: string
}

export const activationService = {
  getPending: () =>
    $fetch<ApiSuccess<PendingUser[]>>(`${apiBase()}/api/admin/users/pending`, {
      headers: authHeaders(),
    }),

  approve: (userId: number, activationDate: string) =>
    $fetch<ApiSuccess<{ message: string }>>(`${apiBase()}/api/admin/users/${userId}/approve`, {
      method: 'POST',
      headers: authHeaders(),
      body: { activation_date: activationDate },
    }),

  reject: (userId: number, reason: string) =>
    $fetch<ApiSuccess<{ message: string }>>(`${apiBase()}/api/admin/users/${userId}/reject`, {
      method: 'POST',
      headers: authHeaders(),
      body: { reason },
    }),
}
