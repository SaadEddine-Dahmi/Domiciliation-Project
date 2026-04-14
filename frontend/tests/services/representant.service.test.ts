// tests/services/representant.service.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { representantService } from '~/services/representant.service'

const mockRep = (overrides = {}) => ({
  id:            1,
  entreprise_id: 10,
  nom:           'El Jadiani',
  prenom:        'Youssef',
  cin:           'BJ422176',
  nationalite:   'Marocaine',
  ...overrides,
})

describe('Representant Service', () => {

  beforeEach(() => {
    localStorage.clear()
    localStorage.setItem('astfisc_auth', JSON.stringify({
      token: 'test-token', savedAt: Date.now(),
    }))
    vi.clearAllMocks()
  })

  it('get calls correct endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      success: true, data: mockRep(),
    })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.get(10)

    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/entreprises/10/representant')
  })

  it('create calls POST on correct endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      success: true, data: mockRep(),
    })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.create(10, { nom: 'El Jadiani', cin: 'BJ422176' })

    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/entreprises/10/representant')
    expect(opts.method).toBe('POST')
  })

  it('update calls PUT on correct endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      success: true, data: mockRep(),
    })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.update(10, { nom: 'Updated' })

    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/entreprises/10/representant')
    expect(opts.method).toBe('PUT')
  })

  it('remove calls DELETE on correct endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      success: true, data: { message: 'Supprimé.' },
    })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.remove(10)

    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/entreprises/10/representant')
    expect(opts.method).toBe('DELETE')
  })

  it('includes Bearer token in all requests', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ success: true, data: null })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.get(1)

    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.headers?.Authorization).toBe('Bearer test-token')
  })

  it('returns empty headers when no token in localStorage', async () => {
    localStorage.clear()

    const fetchMock = vi.fn().mockResolvedValue({ success: true, data: null })
    vi.stubGlobal('$fetch', fetchMock)

    await representantService.get(1)

    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.headers?.Authorization).toBeUndefined()
  })
})