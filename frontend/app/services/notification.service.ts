// app/services/notification.service.ts
// Calls notification listing and read-state endpoints.

import { apiBase, authHeaders } from '~/services/http'

export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface NotificationEntity {
  id: number
  user_id: number
  from_user_id: number | null
  alert_id: number | null
  contrat_id: number | null
  subject: string | null
  message: string
  type: string
  data: Record<string, any> | null
  is_read: boolean
  read_at: string | null
  created_at: string
  updated_at: string
}

export const notificationService = {
  list: () =>
    $fetch<ApiSuccess<NotificationEntity[]>>(`${apiBase()}/api/notifications`, {
      query: { per_page: 100 },
      headers: authHeaders(),
    }),

  markRead: (id: number) =>
    $fetch<ApiSuccess<NotificationEntity>>(`${apiBase()}/api/notifications/${id}/read`, {
      method: 'POST',
      headers: authHeaders(),
    }),

  markAllRead: () =>
    $fetch<ApiSuccess<null>>(`${apiBase()}/api/notifications/read-all`, {
      method: 'POST',
      headers: authHeaders(),
    }),
}
