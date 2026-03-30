export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface NotificationEntity {
  id: number
  user_id: number
  alert_id: number
  contrat_id: number
  message: string
  is_read: boolean
  created_at: string
  updated_at: string
}

export const notificationService = {
  list: () => $fetch<ApiSuccess<NotificationEntity[]>>('/api/notifications'),

  markRead: (id: number) =>
    $fetch<ApiSuccess<NotificationEntity>>(`/api/notifications/${id}/read`, {
      method: 'POST',
    }),
}