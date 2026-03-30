export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface ClientUser {
  id: number
  nom: string
  prenom?: string
  email: string
  telephone?: string
  role: 'domiciliataire' | 'client' | 'admin'
}

export interface ClientEntity {
  id: number
  raison_sociale: string
  forme_juridique?: string
  adresse?: string
  ville?: string
  pays?: string
  capital?: string
  date_creation?: string
  statut?: string
  client_user_id?: number | null
  client_user?: ClientUser | null
  documents?: any[]
}

export const clientService = {
  list: () => $fetch<ApiSuccess<ClientEntity[]>>('/api/clients'),

  getById: (id: number) =>
    $fetch<ApiSuccess<ClientEntity>>(`/api/clients/${id}`),

  update: (
    id: number,
    payload: Partial<ClientEntity> & {
      client_user?: Partial<Pick<ClientUser, 'nom' | 'prenom' | 'email' | 'telephone'>>
    },
  ) =>
    $fetch<ApiSuccess<ClientEntity>>(`/api/clients/${id}`, {
      method: 'PUT',
      body: payload,
    }),

  updatePassword: (id: number, password: string, password_confirmation: string) =>
    $fetch<ApiSuccess<{ message: string }>>(`/api/clients/${id}/password`, {
      method: 'PUT',
      body: { password, password_confirmation },
    }),
}