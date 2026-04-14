// tests/stores/clients.store.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useClientsStore } from '~/stores/clients'

const mockClient = (overrides = {}) => ({
    id: 1,
    raison_sociale: 'BRONX IMMOBILIER',
    forme_juridique: 'SARL',
    adresse: '123 Rue Hassan II',
    ville: 'Agadir',
    pays: 'Maroc',
    capital: null,
    date_creation: null,
    statut: 'actif',
    client_user: {
        id: 10,
        nom: 'El Jadiani',
        prenom: 'Youssef',
        email: 'youssef@bronx.ma',
        telephone: '+212711779427',
        role: 'client',
    },
    ...overrides,
})

describe('Clients Store', () => {

    beforeEach(() => {
        setActivePinia(createPinia())
        localStorage.setItem('astfisc_auth', JSON.stringify({
            token: 'test-token', savedAt: Date.now(),
        }))
        vi.clearAllMocks()
    })

    // ── fetchAll ─────────────────────────────────────────────

    it('fetchAll populates items', async () => {
        const store = useClientsStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: [mockClient(), mockClient({ id: 2, raison_sociale: 'SECOND SARL' })],
        }))

        await store.fetchAll()

        expect(store.items).toHaveLength(2)
        expect(store.loading).toBe(false)
    })

    it('fetchAll handles empty response', async () => {
        const store = useClientsStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true, data: [],
        }))

        await store.fetchAll()

        expect(store.items).toHaveLength(0)
        expect(store.error).toBe('')
    })

    it('fetchAll sets error on failure', async () => {
        const store = useClientsStore()
        vi.stubGlobal('$fetch', vi.fn().mockRejectedValue({
            data: { message: 'Non autorisé.' },
        }))

        await store.fetchAll()

        expect(store.error).toBe('Non autorisé.')
        expect(store.items).toHaveLength(0)
    })

    // ── update ───────────────────────────────────────────────

    it('update modifies the correct client in items', async () => {
        const store = useClientsStore()
        store.items = [
            mockClient({ id: 1, raison_sociale: 'OLD NAME' }),
            mockClient({ id: 2, raison_sociale: 'OTHER' }),
        ]

        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: mockClient({ id: 1, raison_sociale: 'NEW NAME' }),
        }))

        await store.update(1, { raison_sociale: 'NEW NAME' })

        expect(store.items[0].raison_sociale).toBe('NEW NAME')
        expect(store.items[1].raison_sociale).toBe('OTHER')
    })

    it('update returns the updated client', async () => {
        const store = useClientsStore()
        store.items = [mockClient()]

        const updated = mockClient({ raison_sociale: 'UPDATED' })
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true, data: updated,
        }))

        const result = await store.update(1, { raison_sociale: 'UPDATED' })

        expect(result.raison_sociale).toBe('UPDATED')
    })

    // ── updatePassword ────────────────────────────────────────

    it('updatePassword calls correct endpoint with PUT method', async () => {
        const store = useClientsStore()
        const fetchMock = vi.fn().mockResolvedValue({})
        vi.stubGlobal('$fetch', fetchMock)

        await store.updatePassword(1, 'newpass123', 'newpass123')

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/clients/1/password'),
            expect.objectContaining({
                method: 'PUT',
                body: {
                    password: 'newpass123',
                    password_confirmation: 'newpass123',
                },
            })
        )
    })
})