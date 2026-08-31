// app/services/document.service.ts
// Calls document metadata, upload, preview, and download endpoints.

import { apiBase } from '~/services/http'

export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface DocumentEntity {
  id: number
  entreprise_id: number
  document_type_id: number
  file_path: string
  date_expiration?: string
  uploaded_by_user: number
  previous_version_id?: number
}

function getToken(): string {
  if (!import.meta.client && typeof window === 'undefined') return ''

  try {
    return JSON.parse(localStorage.getItem('app_auth') ?? '{}')?.token ?? ''
  } catch {
    return ''
  }
}

function streamUrl(id: number, action: 'preview' | 'download'): string {
  return `${apiBase()}/api/documents/${id}/${action}?token=${encodeURIComponent(getToken())}`
}

export const documentService = {
  list: (entreprise_id?: number) =>
    $fetch<ApiSuccess<DocumentEntity[]>>('/api/documents', {
      query: { ...(entreprise_id ? { entreprise_id } : {}), per_page: 100 },
    }),

  previewUrl: (id: number): string => streamUrl(id, 'preview'),

  downloadUrl: (id: number): string => streamUrl(id, 'download'),

  upload: async (payload: {
    entreprise_id: number
    document_type_id: number
    date_expiration?: string
    previous_version_id?: number
    file: File
  }) => {
    const formData = new FormData()
    formData.append('entreprise_id', String(payload.entreprise_id))
    formData.append('document_type_id', String(payload.document_type_id))
    if (payload.date_expiration) formData.append('date_expiration', payload.date_expiration)
    if (payload.previous_version_id) formData.append('previous_version_id', String(payload.previous_version_id))
    formData.append('file', payload.file)

    return await $fetch<ApiSuccess<DocumentEntity>>('/api/documents', {
      method: 'POST',
      body: formData,
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
