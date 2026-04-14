// tests/middleware/auth.middleware.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'

describe('Auth Middleware Logic', () => {

    beforeEach(() => {
        setActivePinia(createPinia())
        localStorage.clear()
        vi.clearAllMocks()
        vi.stubGlobal('$fetch', vi.fn())
    })

    it('unauthenticated user has no access', () => {
        const auth = useAuthStore()
        expect(auth.isAuthenticated).toBe(false)
    })

    it('client is redirected away from /admin paths', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: {
                user: { id: 1, nom: 'Test', email: 't@t.ma', role: 'client', status: 'active' },
                token: 'tok',
            },
        }))

        await auth.login({ email: 't@t.ma', password: 'p' })

        expect(auth.isClient).toBe(true)
        expect(auth.isInternal).toBe(false)
    })

    it('domiciliataire has internal access', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: {
                user: { id: 1, nom: 'Test', email: 't@t.ma', role: 'domiciliataire', status: 'active' },
                token: 'tok',
            },
        }))

        await auth.login({ email: 't@t.ma', password: 'p' })

        expect(auth.isInternal).toBe(true)
        expect(auth.isClient).toBe(false)
    })

    it('session restored from valid localStorage grants access', () => {
        // FIX: set localStorage FIRST, then create fresh pinia + store
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: {
                id: 1, name: 'Test', email: 't@t.ma',
                role: 'domiciliataire', status: 'active',
                company: '', avatar: 'TE', color: '#c8a96e',
            },
            token: 'valid-token',
            savedAt: Date.now(),
        }))

        // Fresh pinia ensures no cached state
        setActivePinia(createPinia())
        const auth = useAuthStore()
        auth.restoreSession()

        expect(auth.isAuthenticated).toBe(true)
        expect(auth.isDomiciliataire).toBe(true)
    })

    it('expired session does not grant access', () => {
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: { id: 1, email: 't@t.ma', role: 'domiciliataire' },
            token: 'old-token',
            savedAt: Date.now() - 8 * 24 * 60 * 60 * 1000,
        }))

        setActivePinia(createPinia())
        const auth = useAuthStore()
        auth.restoreSession()

        expect(auth.isAuthenticated).toBe(false)
    })
})