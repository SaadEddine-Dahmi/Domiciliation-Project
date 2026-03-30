export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface DashboardStats {
  clients_actifs: number
  en_attente: number
  documents_en_attente: number
  ca_mensuel: string
}

export const dashboardService = {
  stats: () => $fetch<ApiSuccess<DashboardStats>>('/api/dashboard/stats'),
}