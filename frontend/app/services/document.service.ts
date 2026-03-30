// Documents service: upload/import and assign to entreprise
export interface ApiSuccess<T> { success: boolean; data: T; message?: string }

export interface DocumentEntity {
  id: number
  entreprise_id: number
  document_type_id: number
  file_path: string
  date_expiration?: string
  uploaded_by_user: number
  previous_version_id?: number
}

export const documentService = {
  list: (entreprise_id?: number) =>
    $fetch<ApiSuccess<DocumentEntity[]>>('/api/documents', {
      query: entreprise_id ? { entreprise_id } : undefined,
    }),

  upload: async (payload: {
    entreprise_id: number
    document_type_id: number
    date_expiration?: string
    previous_version_id?: number
    file: File
  }) => {
    const fd = new FormData()
    fd.append('entreprise_id', String(payload.entreprise_id))
    fd.append('document_type_id', String(payload.document_type_id))
    if (payload.date_expiration) fd.append('date_expiration', payload.date_expiration)
    if (payload.previous_version_id) fd.append('previous_version_id', String(payload.previous_version_id))
    fd.append('file', payload.file)

    return await $fetch<ApiSuccess<DocumentEntity>>('/api/documents', {
      method: 'POST',
      body: fd,
    })
  },

  update: (id: number, payload: {
    entreprise_id: number
    document_type_id: number
    date_expiration?: string
    previous_version_id?: number
  }) => $fetch<ApiSuccess<DocumentEntity>>(`/api/documents/${id}`, { method: 'PUT', body: payload }),

  remove: (id: number) =>
    $fetch<ApiSuccess<{ message: string }>>(`/api/documents/${id}`, { method: 'DELETE' }),
}