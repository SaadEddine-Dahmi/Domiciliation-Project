// app/services/notification.service.ts
// Calls notification listing and read-state endpoints.

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
    $fetch<ApiSuccess<NotificationEntity[]>>('/api/notifications', {
      query: { per_page: 100 },
    }),

  markRead: (id: number) =>
    $fetch<ApiSuccess<NotificationEntity>>(`/api/notifications/${id}/read`, {
      method: 'POST',
    }),

  markAllRead: () =>
    $fetch<ApiSuccess<null>>('/api/notifications/read-all', {
      method: 'POST',
    }),
}
