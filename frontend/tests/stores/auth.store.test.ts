// tests/stores/auth.store.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Import AFTER setup has run so import.meta.client is patched
import { useAuthStore } from '~/stores/auth'

const mockApiUser = (overrides = {}) => ({
    id: 1,
    nom: 'Dahmi',
    prenom: 'Saad',
    email: 'saad@astfisc.ma',
    role: 'domiciliataire',
    status: 'active',
    ...overrides,
})

describe('Auth Store', () => {

    beforeEach(() => {
        setActivePinia(createPinia())
        localStorage.clear()
        vi.clearAllMocks()
        vi.stubGlobal('$fetch', vi.fn())
    })

    // ── Initial state ────────────────────────────────────────

    it('starts unauthenticated', () => {
        const auth = useAuthStore()
        expect(auth.isAuthenticated).toBe(false)
        expect(auth.user).toBeNull()
        expect(auth.token).toBe('')
    })

    it('all role flags start as false', () => {
        const auth = useAuthStore()
        expect(auth.isAdmin).toBe(false)
        expect(auth.isDomiciliataire).toBe(false)
        expect(auth.isClient).toBe(false)
        expect(auth.isInternal).toBe(false)
    })

    // ── Login ────────────────────────────────────────────────

    it('login sets user and token on success', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser(), token: 'test-token-123' },
        }))

        const result = await auth.login({ email: 'saad@astfisc.ma', password: 'password' })

        expect(result).toBe(true)
        expect(auth.isAuthenticated).toBe(true)
        expect(auth.token).toBe('test-token-123')
        expect(auth.user?.email).toBe('saad@astfisc.ma')
    })

    it('login sets correct role flags for domiciliataire', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser({ role: 'domiciliataire' }), token: 'tok' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })

        expect(auth.isDomiciliataire).toBe(true)
        expect(auth.isAdmin).toBe(false)
        expect(auth.isClient).toBe(false)
        expect(auth.isInternal).toBe(true)
    })

    it('login sets correct role flags for admin', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser({ role: 'admin' }), token: 'tok' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })

        expect(auth.isAdmin).toBe(true)
        expect(auth.isInternal).toBe(true)
        expect(auth.isDomiciliataire).toBe(false)
    })

    it('login sets correct role flags for client', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser({ role: 'client' }), token: 'tok' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })

        expect(auth.isClient).toBe(true)
        expect(auth.isInternal).toBe(false)
    })

    it('login returns false and sets error on failure', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockRejectedValue({
            data: { message: 'Identifiants invalides' },
        }))

        const result = await auth.login({ email: 'x@x.ma', password: 'wrong' })

        expect(result).toBe(false)
        expect(auth.isAuthenticated).toBe(false)
        expect(auth.error).toBe('Identifiants invalides')
    })

    it('login persists session to localStorage', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser(), token: 'my-token' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })

        const raw = localStorage.getItem('astfisc_auth')
        expect(raw).not.toBeNull()
        const stored = JSON.parse(raw!)
        expect(stored.token).toBe('my-token')
        expect(stored.user).toBeDefined()
        expect(stored.savedAt).toBeDefined()
    })

    // ── Register ─────────────────────────────────────────────

    it('register sets isPendingApproval when no token returned', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            message: 'En attente de validation.',
            data: { user: mockApiUser({ status: 'pending' }) },
        }))

        const result = await auth.register({
            nom: 'Dahmi',
            email: 'new@test.ma',
            password: 'password123',
        })

        expect(result).toBe(true)
        expect(auth.isPendingApproval).toBe(true)
        expect(auth.isAuthenticated).toBe(false)
    })

    it('register authenticates immediately when token returned', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser({ role: 'client' }), token: 'tok' },
        }))

        await auth.register({
            nom: 'Test',
            email: 'test@test.ma',
            password: 'password123',
        })

        expect(auth.isAuthenticated).toBe(true)
        expect(auth.isPendingApproval).toBe(false)
    })

    // ── Logout ───────────────────────────────────────────────

    it('logout clears user, token and localStorage', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser(), token: 'tok' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })
        expect(auth.isAuthenticated).toBe(true)

        auth.logout()

        expect(auth.isAuthenticated).toBe(false)
        expect(auth.user).toBeNull()
        expect(auth.token).toBe('')
        expect(localStorage.getItem('astfisc_auth')).toBeNull()
    })

    // ── restoreSession ───────────────────────────────────────

    it('restoreSession restores user and token from localStorage', () => {
        // Write directly to mock localStorage BEFORE creating store
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: mockApiUser(),
            token: 'restored-token',
            savedAt: Date.now(),
        }))

        // Create fresh store — no existing state
        setActivePinia(createPinia())
        const auth = useAuthStore()

        auth.restoreSession()

        expect(auth.token).toBe('restored-token')
        expect(auth.user?.email).toBe('saad@astfisc.ma')
    })

    it('restoreSession clears expired session', () => {
        const eightDaysAgo = Date.now() - 8 * 24 * 60 * 60 * 1000
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: mockApiUser(),
            token: 'old-token',
            savedAt: eightDaysAgo,
        }))

        setActivePinia(createPinia())
        const auth = useAuthStore()
        auth.restoreSession()

        expect(auth.token).toBe('')
        expect(auth.user).toBeNull()
        expect(localStorage.getItem('astfisc_auth')).toBeNull()
    })

    it('restoreSession clears corrupted localStorage data', () => {
        localStorage.setItem('astfisc_auth', 'not-valid-json{{{')

        setActivePinia(createPinia())
        const auth = useAuthStore()
        auth.restoreSession()

        expect(auth.token).toBe('')
        expect(localStorage.getItem('astfisc_auth')).toBeNull()
    })

    it('restoreSession does nothing when already authenticated', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser(), token: 'live-token' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })

        // Overwrite localStorage with different token
        localStorage.setItem('astfisc_auth', JSON.stringify({
            user: mockApiUser(), token: 'stale-token', savedAt: Date.now(),
        }))

        auth.restoreSession()

        // Should keep live-token, not overwrite with stale
        expect(auth.token).toBe('live-token')
    })

    // ── buildUser ─────────────────────────────────────────────

    it('builds correct avatar from nom', async () => {
        const auth = useAuthStore()
        vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
            success: true,
            data: { user: mockApiUser({ nom: 'Dahmi', prenom: 'Saad' }), token: 'tok' },
        }))

        await auth.login({ email: 'x@x.ma', password: 'p' })
        expect(auth.user?.avatar).toBe('DA')
    })

    it('assigns correct color per role', async () => {
        for (const [role, expectedColor] of [
            ['admin', '#ef4444'],
            ['domiciliataire', '#c8a96e'],
            ['client', '#60a5fa'],
        ] as const) {
            setActivePinia(createPinia())
            const freshAuth = useAuthStore()
            vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({
                success: true,
                data: { user: mockApiUser({ role }), token: 'tok' },
            }))
            await freshAuth.login({ email: 'x@x.ma', password: 'p' })
            expect(freshAuth.user?.color).toBe(expectedColor)
        }
    })
})