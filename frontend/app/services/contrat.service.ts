// app/services/contrat.service.ts
// Calls contract creation, lifecycle, and PDF streaming endpoints.

import { apiBase, authHeaders } from '~/services/http'
import type { ApiSuccess, ContratEntity, ContratPayload } from '~/types/contrat-api'

function getToken(): string {
  if (!import.meta.client && typeof window === 'undefined') return ''

  try {
    return JSON.parse(localStorage.getItem((useRuntimeConfig().public.authStorageKey as string) ?? 'app_auth') ?? '{}')?.token ?? ''
  } catch {
    return ''
  }
}

function adaptPayload(payload: ContratPayload): Record<string, unknown> {
  return {
    titre_contrat: payload.form.titreContrat || null,
    company_name: payload.form.companyName,
    company_rc: payload.form.companyRC,
    company_if: payload.form.companyIF,
    company_tp: payload.form.companyTP,
    company_representant: payload.form.companyRepresentant,
    company_cin: payload.form.companyCIN,
    company_adresse: payload.form.companyAdresse,
    societe: payload.form.societe,
    gerant_nom: payload.form.gerantNom,
    gerant_cin: payload.form.gerantCIN,
    tel: payload.form.tel,
    email: payload.form.email,
    adresse_perso: payload.form.adressePerso,
    date_debut: payload.form.dateDebut || null,
    date_fin: payload.form.dateFin || null,
    duree_mois: payload.form.months || null,
    prix_mensuel: payload.totals.monthly || null,
    prix_total: payload.totals.global || null,
    instruction_no: payload.form.instruction_no || null,
    ville_signature: payload.form.ville_signature || null,
    date_signature: payload.form.date_signature || null,
    caution: payload.form.caution || null,
    mode_paiement: payload.form.mode_paiement || null,
    statut: 'draft',
    articles: payload.articles.map((article, index) => ({
      id: String(article.id),
      ordre: typeof article.ordre === 'number' ? article.ordre : index + 1,
    })),
  }
}

function contractUrl(path = ''): string {
  return `${apiBase()}/api/contrats${path}`
}

export const contratService = {
  createDraft: (payload: ContratPayload, entrepriseId: number): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(), {
      method: 'POST',
      headers: authHeaders(),
      body: { ...adaptPayload(payload), entreprise_id: entrepriseId },
    }),

  updateDraft: (id: string, payload: ContratPayload, entrepriseId: number): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(`/${id}`), {
      method: 'PUT',
      headers: authHeaders(),
      body: { ...adaptPayload(payload), entreprise_id: entrepriseId },
    }),

  list: (): Promise<ApiSuccess<ContratEntity[]>> =>
    $fetch(contractUrl(), {
      headers: authHeaders(),
      query: { per_page: 100 },
    }),

  getById: (id: string): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(`/${id}`), { headers: authHeaders() }),

  activate: (id: string): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(`/${id}/activate`), {
      method: 'POST',
      headers: authHeaders(),
    }),

  terminate: (id: string): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(`/${id}/terminate`), {
      method: 'POST',
      headers: authHeaders(),
    }),

  renew: (id: string): Promise<ApiSuccess<ContratEntity>> =>
    $fetch(contractUrl(`/${id}/renew`), {
      method: 'POST',
      headers: authHeaders(),
    }),

  deleteDraft: (id: string): Promise<{ success: boolean; message?: string }> =>
    $fetch(contractUrl(`/${id}`), {
      method: 'DELETE',
      headers: authHeaders(),
    }),

  streamPdfUrl(id: string, mode: 'preview' | 'download' = 'preview'): string {
    return `${contractUrl(`/${id}/pdf/stream`)}?token=${encodeURIComponent(getToken())}&mode=${mode}`
  },
}
