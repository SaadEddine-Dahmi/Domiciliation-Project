// tests/stores/articles.store.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useArticlesStore } from '~/stores/articles'

const mockArticle = (overrides = {}) => ({
    id: 'uuid-1',
    title: 'ARTICLE 1 — DURÉE',
    body: 'Le contrat dure {{duree_mois}} mois.',
    is_active: true,
    ...overrides,
})

describe('Articles Store', () => {

    beforeEach(() => {
        setActivePinia(createPinia())
        localStorage.setItem('astfisc_auth', JSON.stringify({
            token: 'test-token', savedAt: Date.now(),
        }))
        vi.clearAllMocks()
    })

    // ── fetchAll ─────────────────────────────────────────────

    it('fetchAll populates items from API', async () => {
        const store = useArticlesStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: [mockArticle(), mockArticle({ id: 'uuid-2', title: 'Article 2' })],
        }))

        await store.fetchAll()

        expect(store.items).toHaveLength(2)
        expect(store.loading).toBe(false)
        expect(store.error).toBe('')
    })

    it('fetchAll sets loading true then false', async () => {
        const store = useArticlesStore()
        let wasLoading = false

        vi.stubGlobal('$fetch', vi.fn().mockImplementation(async () => {
            wasLoading = store.loading
            return { success: true, data: [] }
        }))

        await store.fetchAll()

        expect(wasLoading).toBe(true)
        expect(store.loading).toBe(false)
    })

    it('fetchAll sets error on API failure', async () => {
        const store = useArticlesStore()
        vi.stubGlobal('$fetch', vi.fn().mockRejectedValue({
            data: { message: 'Erreur serveur' },
        }))

        await store.fetchAll()

        expect(store.error).toBe('Erreur serveur')
        expect(store.items).toHaveLength(0)
    })

    it('fetchAll uses default error message on unknown error', async () => {
        const store = useArticlesStore()
        vi.stubGlobal('$fetch', vi.fn().mockRejectedValue(new Error('network')))

        await store.fetchAll()

        expect(store.error).toBe('Erreur chargement articles')
    })

    // ── create ───────────────────────────────────────────────

    it('create adds article to beginning of items', async () => {
        const store = useArticlesStore()
        store.items = [mockArticle({ id: 'existing' })]

        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: mockArticle({ id: 'new-uuid', title: 'New Article' }),
        }))

        const created = await store.create('New Article', 'Body text')

        expect(store.items[0].id).toBe('new-uuid')
        expect(store.items).toHaveLength(2)
        expect(created.title).toBe('New Article')
    })

    it('create sends correct payload to API', async () => {
        const store = useArticlesStore()
        const fetchMock = vi.fn().mockResolvedValue({
            success: true,
            data: mockArticle(),
        })
        vi.stubGlobal('$fetch', fetchMock)

        await store.create('My Title', 'My Body')

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/articles'),
            expect.objectContaining({
                method: 'POST',
                body: expect.objectContaining({
                    title: 'My Title',
                    body: 'My Body',
                    is_active: true,
                }),
            })
        )
    })

    // ── update ───────────────────────────────────────────────

    it('update replaces article in items array', async () => {
        const store = useArticlesStore()
        store.items = [mockArticle({ id: 'uuid-1', title: 'Old Title' })]

        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: mockArticle({ id: 'uuid-1', title: 'Updated Title' }),
        }))

        await store.update('uuid-1', 'Updated Title', 'New body', true)

        expect(store.items[0].title).toBe('Updated Title')
        expect(store.items).toHaveLength(1)
    })

    it('update does not affect other items', async () => {
        const store = useArticlesStore()
        store.items = [
            mockArticle({ id: 'uuid-1', title: 'First' }),
            mockArticle({ id: 'uuid-2', title: 'Second' }),
        ]

        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: mockArticle({ id: 'uuid-1', title: 'Updated First' }),
        }))

        await store.update('uuid-1', 'Updated First', 'body', true)

        expect(store.items[1].title).toBe('Second')
    })

    // ── remove ───────────────────────────────────────────────

    it('remove deletes article from items', async () => {
        const store = useArticlesStore()
        store.items = [
            mockArticle({ id: 'uuid-1' }),
            mockArticle({ id: 'uuid-2' }),
        ]

        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({}))

        await store.remove('uuid-1')

        expect(store.items).toHaveLength(1)
        expect(store.items[0].id).toBe('uuid-2')
    })

    it('remove sends DELETE request to correct URL', async () => {
        const store = useArticlesStore()
        store.items = [mockArticle({ id: 'uuid-1' })]
        const fetchMock = vi.fn().mockResolvedValue({})
        vi.stubGlobal('$fetch', fetchMock)

        await store.remove('uuid-1')

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/articles/uuid-1'),
            expect.objectContaining({ method: 'DELETE' })
        )
    })
})