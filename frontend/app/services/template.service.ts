// services/template.service.ts
//
// HTTP client for the /api/templates resource — reusable, ordered article
// sets a domiciliataire can build once and load into any new contract.

export interface TemplateArticle {
    id: string
    title: string
    body: string
    pivot?: { ordre: number }
}

export interface TemplateEntity {
    id: number
    name: string
    description: string | null
    articles: TemplateArticle[]
    created_at?: string
    updated_at?: string
}

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
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

export const templateService = {

    /** GET /api/templates — list all templates for the authenticated tenant */
    list(): Promise<ApiSuccess<TemplateEntity[]>> {
        return $fetch(`${getApiBase()}/api/templates`, { headers: authHeaders() })
    },

    /** POST /api/templates — create a new template with an ordered article set */
    create(
        name: string,
        description: string,
        articles: { id: string; ordre: number }[]
    ): Promise<ApiSuccess<TemplateEntity>> {
        return $fetch(`${getApiBase()}/api/templates`, {
            method: 'POST',
            headers: authHeaders(),
            body: { name, description, articles },
        })
    },

    /** PUT /api/templates/{id} — update name, description, or article set */
    update(
        id: number,
        name: string,
        description: string,
        articles: { id: string; ordre: number }[]
    ): Promise<ApiSuccess<TemplateEntity>> {
        return $fetch(`${getApiBase()}/api/templates/${id}`, {
            method: 'PUT',
            headers: authHeaders(),
            body: { name, description, articles },
        })
    },

    /** DELETE /api/templates/{id} — permanently delete a template */
    remove(id: number): Promise<ApiSuccess<{ message: string }>> {
        return $fetch(`${getApiBase()}/api/templates/${id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        })
    },
}