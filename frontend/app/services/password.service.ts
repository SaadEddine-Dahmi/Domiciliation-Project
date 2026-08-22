// services/password.service.ts
// Lets the currently authenticated user change their own password.
// Backend route: PUT /api/account/password

interface ApiSuccess<T> {
    success: boolean
    data?: T
    message?: string
}

function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
        const raw = localStorage.getItem('app_auth')
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
    } catch { return {} }
}

function apiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

export const passwordService = {
    /** PUT /api/account/password */
    change: (currentPassword: string, password: string, passwordConfirmation: string) =>
        $fetch<ApiSuccess<{ message: string }>>(
            `${apiBase()}/api/account/password`,
            {
                method: 'PUT',
                headers: authHeaders(),
                body: {
                    current_password: currentPassword,
                    password,
                    password_confirmation: passwordConfirmation,
                },
            }
        ),
}