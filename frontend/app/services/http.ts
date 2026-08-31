// app/services/http.ts
// Provides shared API base URL and auth header helpers.

export function apiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

export function authHeaders(): Record<string, string> {
  if (!import.meta.client && typeof window === 'undefined') return {}

  try {
    const raw = localStorage.getItem('app_auth')
    if (!raw) return {}

    const parsed = JSON.parse(raw)
    return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
  } catch {
    return {}
  }
}
