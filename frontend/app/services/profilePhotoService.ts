// app/services/profilePhotoService.ts
// Calls profile photo upload and deletion endpoints.

import { apiBase } from '~/services/http'
import { useAuthStore } from '~/stores/auth'

export interface PhotoResponse {
  success: boolean
  message: string
  data: {
    photo_url: string | null
    initials: string
  }
}

function authHeaders(): Record<string, string> {
  const auth = useAuthStore()
  return auth.token ? { Authorization: `Bearer ${auth.token}` } : {}
}

export async function uploadProfilePhoto(file: File): Promise<PhotoResponse> {
  const formData = new FormData()
  formData.append('photo', file)

  return await $fetch<PhotoResponse>(`${apiBase()}/api/profile/photo`, {
    method: 'POST',
    headers: authHeaders(),
    body: formData,
  })
}

export async function deleteProfilePhoto(): Promise<PhotoResponse> {
  return await $fetch<PhotoResponse>(`${apiBase()}/api/profile/photo`, {
    method: 'DELETE',
    headers: authHeaders(),
  })
}
