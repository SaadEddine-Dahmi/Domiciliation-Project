// stores/articles.ts
//
// Pinia store for the article (contract clause) library.
//
// ID discipline:
//   Article PKs are integer auto-increment (bigint) in PostgreSQL.
//   Laravel serialises them as JSON numbers, but Nuxt's $fetch can coerce
//   large integers incorrectly. We normalise every id to String() immediately
//   after fetch and keep it as a string throughout the frontend.
//   PHP casts String(id) back to (int) in syncArticles() on the backend.
//
//   WHY normalise to string at all:
//   - Avoids the "all chips selected" bug caused by id serialising as 0
//     in nested eager-loads when the Article model was missing the 'id' cast.
//   - Future-proofs against a schema migration to UUIDs.

import { defineStore } from 'pinia'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface Article {
    id: string   // integer PK, normalised to string after fetch
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

    function getApiBase(): string {
        const config = useRuntimeConfig()
        return (config.public.apiBase as string) ?? ''
    }

    /**
     * Authorization header from localStorage.
     * Key: 'app_auth' — written by the auth store on login.
     * Returns {} on the server side (no window / localStorage).
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
     *
     * Load all article clause templates for the authenticated tenant.
     *
     * Normalisation step: every id is converted to String() immediately.
     * This ensures selectedArticleIds.includes(String(id)) works correctly
     * in the chip selector even if the backend serialises the integer as 0
     * in a nested eager-load (a known edge case when the 'id' cast is missing
     * from the Article model).
     */
    async function fetchAll(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            const res = await $fetch<ApiSuccess<Article[]>>(
                `${getApiBase()}/api/articles`,
                { headers: authHeaders() }
            )
            // Normalise ids to strings immediately after fetch.
            items.value = (res.data ?? []).map(a => ({ ...a, id: String(a.id) }))
        } catch (e: any) {
            error.value = e?.data?.message ?? 'Erreur lors du chargement des articles'
        } finally {
            loading.value = false
        }
    }

    /**
     * POST /api/articles
     *
     * Create a new article clause template.
     * Never sends 'id' in the payload — auto-increment, server-assigned.
     * Prepends the new article to the local list for immediate UI feedback.
     */
    async function create(title: string, body: string): Promise<Article> {
        const res = await $fetch<ApiSuccess<Article>>(
            `${getApiBase()}/api/articles`,
            {
                method: 'POST',
                headers: authHeaders(),
                // Intentionally omitting 'id' — the server assigns it.
                body: { title, body, is_active: true },
            }
        )
        const article = { ...res.data, id: String(res.data.id) }
        items.value.unshift(article)
        return article
    }

    /**
     * PUT /api/articles/{id}
     *
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
        const updated = { ...res.data, id: String(res.data.id) }
        const idx = items.value.findIndex(a => a.id === id)
        if (idx !== -1) items.value[idx] = updated
        return updated
    }

    /**
     * DELETE /api/articles/{id}
     *
     * Permanently delete an article clause template.
     * Removes the item from the local list immediately for responsive UI.
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