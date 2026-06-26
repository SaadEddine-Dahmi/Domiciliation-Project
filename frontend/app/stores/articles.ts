// stores/articles.ts
//
// Pinia store for the article (contract clause) library.
//
// ID discipline:
//   Article primary keys are integer auto-increment values in PostgreSQL.
//   Laravel serialises them as strings in JSON responses.
//   We keep IDs as strings throughout the frontend and never cast with Number().
//   PHP casts to (int) in syncArticles() when writing to the pivot table.
//   This avoids silent corruption if IDs ever exceed Number.MAX_SAFE_INTEGER
//   or if the schema is migrated to UUIDs in the future.

import { defineStore } from 'pinia'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface Article {
    id: string    // integer PK serialised as string by Laravel JSON
    title: string
    body: string
    is_active: boolean
    created_at?: string
    updated_at?: string
}

interface ApiSuccess<T> {
    success: boolean
    data: T
    message?: string
}

// ── Store ─────────────────────────────────────────────────────────────────────

export const useArticlesStore = defineStore('articles', () => {

    // ── State ─────────────────────────────────────────────────────────────────
    const items = ref<Article[]>([])
    const loading = ref(false)
    const error = ref('')

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Read the configured API base URL from Nuxt runtime config */
    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    /**
     * Build the Authorization header from localStorage.
     * Key: 'app_auth' — written by the auth controller on successful login.
     * Returns an empty object when called server-side (no window object).
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

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /api/articles
     * Load all article clause templates for the authenticated tenant.
     */
    async function fetchAll(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<ApiSuccess<Article[]>>(
                `${getApiBase()}/api/articles`,
                { headers: authHeaders() }
            )
            items.value = res.data ?? []
        } catch (e: any) {
            error.value = e?.data?.message ?? 'Erreur lors du chargement des articles'
        } finally {
            loading.value = false
        }
    }

    /**
     * POST /api/articles
     * Create a new article clause template.
     * Prepends the new item to the local list for immediate UI feedback.
     */
    async function create(title: string, body: string): Promise<Article> {
        const res = await $fetch<ApiSuccess<Article>>(
            `${getApiBase()}/api/articles`,
            {
                method: 'POST',
                headers: authHeaders(),
                body: { title, body, is_active: true },
            }
        )
        items.value.unshift(res.data)
        return res.data
    }

    /**
     * PUT /api/articles/{id}
     * Update an existing article's title, body, or active state.
     * Updates the matching item in the local list in-place.
     */
    async function update(
        id: string,
        title: string,
        body: string,
        is_active: boolean
    ): Promise<Article> {
        const res = await $fetch<ApiSuccess<Article>>(
            `${getApiBase()}/api/articles/${id}`,
            {
                method: 'PUT',
                headers: authHeaders(),
                body: { title, body, is_active },
            }
        )
        const idx = items.value.findIndex(a => a.id === id)
        if (idx !== -1) items.value[idx] = res.data
        return res.data
    }

    /**
     * DELETE /api/articles/{id}
     * Permanently delete an article clause template.
     * Removes the item from the local list immediately.
     */
    async function remove(id: string): Promise<void> {
        await $fetch(`${getApiBase()}/api/articles/${id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        })
        items.value = items.value.filter(a => a.id !== id)
    }

    return { items, loading, error, fetchAll, create, update, remove }
})