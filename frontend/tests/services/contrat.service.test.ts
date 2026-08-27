import { beforeEach, describe, expect, it, vi } from 'vitest'
import { contratService } from '~/services/contrat.service'
import type { ContratPayload } from '~/types/contrat-api'

const fetchMock = vi.mocked(globalThis.$fetch)

function makePayload(overrides: Partial<ContratPayload['form']> = {}): ContratPayload {
  return {
    form: {
      companyName: 'AST-FISC',
      companyRC: 'RC-1',
      companyIF: 'IF-1',
      companyTP: 'TP-1',
      companyRepresentant: 'Owner',
      companyCIN: 'AB123456',
      companyAdresse: 'Agadir',
      titreContrat: 'Contrat de Domiciliation',
      societe: 'CLIENT SARL',
      gerantNom: 'Client Owner',
      gerantCIN: 'CD123456',
      tel: '+212600000000',
      email: 'client@example.com',
      adressePerso: 'Client address',
      dateDebut: '2026-01-01',
      dateFin: '2026-12-31',
      months: 12,
      redevanceMensuelle: 500,
      redevanceAnnuelle: 6000,
      instruction_no: 'INST-1',
      ville_signature: 'Agadir',
      date_signature: '2026-01-01',
      caution: '1000',
      mode_paiement: 'virement',
      ...overrides,
    },
    selectedServices: [],
    articles: [
      { id: '9', title: 'Article 9', body: 'Body', ordre: 3 },
      { id: '4', title: 'Article 4', body: 'Body' },
    ],
    totals: {
      monthly: 500,
      global: 6000,
    },
  }
}

describe('contratService', () => {
  beforeEach(() => {
    fetchMock.mockReset()
    localStorage.clear()
    localStorage.setItem('app_auth', JSON.stringify({ token: 'token 123' }))
  })

  it('creates a draft with the Laravel payload shape and auth header', async () => {
    fetchMock.mockResolvedValueOnce({ success: true, data: { id: '10' } })

    await contratService.createDraft(makePayload(), 7)

    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8000/api/contrats', {
      method: 'POST',
      headers: { Authorization: 'Bearer token 123' },
      body: expect.objectContaining({
        entreprise_id: 7,
        titre_contrat: 'Contrat de Domiciliation',
        statut: 'draft',
        prix_mensuel: 500,
        prix_total: 6000,
        articles: [
          { id: '9', ordre: 3 },
          { id: '4', ordre: 2 },
        ],
      }),
    })
  })

  it('sends nulls for optional empty contract fields on update', async () => {
    fetchMock.mockResolvedValueOnce({ success: true, data: { id: '10' } })

    await contratService.updateDraft('10', makePayload({
      titreContrat: '',
      dateDebut: '',
      dateFin: '',
      instruction_no: '',
      ville_signature: '',
      date_signature: '',
      caution: '',
      mode_paiement: '',
    }), 7)

    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8000/api/contrats/10', expect.objectContaining({
      method: 'PUT',
      body: expect.objectContaining({
        entreprise_id: 7,
        titre_contrat: null,
        date_debut: null,
        date_fin: null,
        instruction_no: null,
        ville_signature: null,
        date_signature: null,
        caution: null,
        mode_paiement: null,
      }),
    }))
  })

  it('calls the contract state and deletion endpoints', async () => {
    fetchMock.mockResolvedValue({ success: true, data: { id: '10' } })

    await contratService.activate('10')
    await contratService.terminate('10')
    await contratService.renew('10')
    await contratService.deleteDraft('10')

    expect(fetchMock).toHaveBeenNthCalledWith(1, 'http://localhost:8000/api/contrats/10/activate', {
      method: 'POST',
      headers: { Authorization: 'Bearer token 123' },
    })
    expect(fetchMock).toHaveBeenNthCalledWith(2, 'http://localhost:8000/api/contrats/10/terminate', {
      method: 'POST',
      headers: { Authorization: 'Bearer token 123' },
    })
    expect(fetchMock).toHaveBeenNthCalledWith(3, 'http://localhost:8000/api/contrats/10/renew', {
      method: 'POST',
      headers: { Authorization: 'Bearer token 123' },
    })
    expect(fetchMock).toHaveBeenNthCalledWith(4, 'http://localhost:8000/api/contrats/10', {
      method: 'DELETE',
      headers: { Authorization: 'Bearer token 123' },
    })
  })

  it('builds encoded preview and download URLs with the stored token', () => {
    expect(contratService.streamPdfUrl('10')).toBe(
      'http://localhost:8000/api/contrats/10/pdf/stream?token=token%20123&mode=preview',
    )
    expect(contratService.streamPdfUrl('10', 'download')).toBe(
      'http://localhost:8000/api/contrats/10/pdf/stream?token=token%20123&mode=download',
    )
  })

  it('omits authorization when local auth state is missing or invalid', async () => {
    localStorage.setItem('app_auth', '{bad json')
    fetchMock.mockResolvedValueOnce({ success: true, data: [] })

    await contratService.list()

    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8000/api/contrats', {
      headers: {},
    })
  })
})
