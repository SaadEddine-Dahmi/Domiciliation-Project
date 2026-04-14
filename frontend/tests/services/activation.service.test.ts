// tests/services/activation.service.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { activationService } from '~/services/activation.service'

describe('Activation Service', () => {

    beforeEach(() => {
        localStorage.setItem('astfisc_auth', JSON.stringify({
            token: 'admin-token', savedAt: Date.now(),
        }))
        vi.clearAllMocks()
    })

    it('getPending calls correct endpoint', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ success: true, data: [] })
        vi.stubGlobal('$fetch', fetchMock)

        await activationService.getPending()

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/admin/users/pending'),
            expect.anything(),
        )
    })

    it('approve calls POST with activation_date', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            success: true, data: { message: 'Approuvé.' },
        })
        vi.stubGlobal('$fetch', fetchMock)

        await activationService.approve(5, '2026-05-01')

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/admin/users/5/approve'),
            expect.objectContaining({
                method: 'POST',
                body: { activation_date: '2026-05-01' },
            }),
        )
    })

    it('reject calls POST with reason', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            success: true, data: { message: 'Rejeté.' },
        })
        vi.stubGlobal('$fetch', fetchMock)

        await activationService.reject(5, 'Dossier incomplet.')

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/admin/users/5/reject'),
            expect.objectContaining({
                method: 'POST',
                body: { reason: 'Dossier incomplet.' },
            }),
        )
    })
})