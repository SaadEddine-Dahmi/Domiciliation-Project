// app/services/profilePhotoService.ts
//
// Handles upload/removal of the domiciliataire's profile photo. Kept
// separate from the general profile service so any component that needs
// to change the photo (settings page, onboarding wizard, etc.) can reuse
// it without pulling in unrelated profile fields.

import { useAuthStore } from '~/stores/auth'

function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
    const auth = useAuthStore()
    return auth.token ? { Authorization: `Bearer ${auth.token}` } : {}
}

export interface PhotoResponse {
    success: boolean
    message: string
    data: {
        photo_url: string | null
        initials: string
    }
}

/**
 * Uploads a new profile photo (replaces the existing one, if any).
 * Accepts a File straight from an <input type="file"> change event.
 */
export async function uploadProfilePhoto(file: File): Promise<PhotoResponse> {
    const formData = new FormData()
    formData.append('photo', file)

    return await $fetch<PhotoResponse>(`${getApiBase()}/api/profile/photo`, {
        method: 'POST',
        headers: authHeaders(),
        body: formData,
    })
}

/** Removes the current profile photo — UI falls back to initials. */
export async function deleteProfilePhoto(): Promise<PhotoResponse> {
    return await $fetch<PhotoResponse>(`${getApiBase()}/api/profile/photo`, {
        method: 'DELETE',
        headers: authHeaders(),
    })
}