// app/services/password.service.ts
// Calls the authenticated password update endpoint.

import { apiBase, authHeaders } from '~/services/http'

interface ApiSuccess<T> {
  success: boolean
  data?: T
  message?: string
}

export const passwordService = {
  change: (currentPassword: string, password: string, passwordConfirmation: string) =>
    $fetch<ApiSuccess<{ message: string }>>(`${apiBase()}/api/account/password`, {
      method: 'PUT',
      headers: authHeaders(),
      body: {
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      },
    }),
}
