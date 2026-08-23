export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

// Mirrors AppNotification's $fillable + casts (see app/Models/AppNotification.php).
// `data` and `type`/`subject` were missing here even though
// NotificationController::index() already returns them — the page needs
// `data.document_id` / `data.contrat_id` to know what a notification
// links to, and `type`/`subject` to label it.
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
  list: () => $fetch<ApiSuccess<NotificationEntity[]>>('/api/notifications'),

  markRead: (id: number) =>
    $fetch<ApiSuccess<NotificationEntity>>(`/api/notifications/${id}/read`, {
      method: 'POST',
    }),

  // Matches NotificationController::readAll() / POST /api/notifications/read-all.
  // The page called this already, but it never existed on the service.
  markAllRead: () =>
    $fetch<ApiSuccess<null>>('/api/notifications/read-all', { method: 'POST' }),
}