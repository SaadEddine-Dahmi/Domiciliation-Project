// services/article.service.ts
//
// Thin HTTP client for the /api/articles resource.
// Use this for one-off reads in components that do not need to subscribe
// to the reactive Pinia store state.
//
// ID discipline: IDs are typed as string. Cast to int only in PHP.

// ── Types ─────────────────────────────────────────────────────────────────────

/** Standard API envelope returned by every Laravel endpoint */
export interface ApiSuccess<T> {
    success: boolean
    data:    T
    message?: string
}

/** Shape of an Article as returned by the backend */
export interface ArticleEntity {
    id:         string    // integer PK serialised as string by Laravel
    title:      string
    body:       string
    is_active:  boolean
    created_at: string
    updated_at: string
}

type ArticlePayload = Pick<ArticleEntity, 'title' | 'body' | 'is_active'>

// ── Private helpers ───────────────────────────────────────────────────────────

function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

/**
 * Build the Authorization header from localStorage.
 * Key: 'app_auth' — written by the auth controller on login.
 * Returns {} when called server-side.
 */
function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
        const raw = localStorage.getItem('app_auth')
        if (!raw) return {}
        const parsed = JSON.parse(raw)
        return parsed?.token ? { Authorization: `Bearer ${parsed.token}` } : {}
    } catch {
        return {}
    }
}

// ── Service ───────────────────────────────────────────────────────────────────

export const articleService = {

    /** GET /api/articles — list all articles for the authenticated tenant */
    list(): Promise<ApiSuccess<ArticleEntity[]>> {
        return $fetch<ApiSuccess<ArticleEntity[]>>(`${getApiBase()}/api/articles`, {
            headers: authHeaders(),
        })
    },

    /** POST /api/articles — create a new article clause template */
    create(payload: ArticlePayload): Promise<ApiSuccess<ArticleEntity>> {
        return $fetch<ApiSuccess<ArticleEntity>>(`${getApiBase()}/api/articles`, {
            method:  'POST',
            headers: authHeaders(),
            body:    payload,
        })
    },

    /** PUT /api/articles/{id} — update title, body, or active state */
    update(id: string, payload: ArticlePayload): Promise<ApiSuccess<ArticleEntity>> {
        return $fetch<ApiSuccess<ArticleEntity>>(`${getApiBase()}/api/articles/${id}`, {
            method:  'PUT',
            headers: authHeaders(),
            body:    payload,
        })
    },

    /** DELETE /api/articles/{id} — permanently delete an article */
    remove(id: string): Promise<ApiSuccess<{ message: string }>> {
        return $fetch<ApiSuccess<{ message: string }>>(`${getApiBase()}/api/articles/${id}`, {
            method:  'DELETE',
            headers: authHeaders(),
        })
    },
}