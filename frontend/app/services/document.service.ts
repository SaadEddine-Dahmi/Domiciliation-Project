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

// Same base-URL / token pattern as contrat.service.ts's streamPdfUrl() —
// duplicated here rather than shared because that's how the existing
// services in this codebase already do it (see contrat.service.ts's own
// comment on authHeaders()/getToken()).
function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function getToken(): string {
  if (!import.meta.client) return ''
  try {
    return JSON.parse(localStorage.getItem('app_auth') ?? '{}')?.token ?? ''
  } catch {
    return ''
  }
}

export const documentService = {
  list: (entreprise_id?: number) =>
    $fetch<ApiSuccess<DocumentEntity[]>>('/api/documents', {
      query: entreprise_id ? { entreprise_id } : undefined,
    }),

  /**
   * Builds the URL for DocumentController::preview() — inline view of the
   * file (PDF renders in the browser's viewer, images render directly).
   * A browser can't attach an Authorization header to a direct
   * navigation, <iframe src>, or window.open() target, so the token
   * travels as a query param instead (see
   * DocumentController::authenticateViaToken()). Safe to use straight in
   * an <iframe>, an <img>, or window.open().
   */
  previewUrl: (id: number): string =>
    `${getApiBase()}/api/documents/${id}/preview?token=${encodeURIComponent(getToken())}`,

  /** Same idea, but hits DocumentController::download() — forces a save-as instead of an inline view. */
  downloadUrl: (id: number): string =>
    `${getApiBase()}/api/documents/${id}/download?token=${encodeURIComponent(getToken())}`,

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