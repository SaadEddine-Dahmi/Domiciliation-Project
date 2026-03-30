// services/contrat.service.ts
import type { ApiSuccess, ContratEntity, ContratPayload } from '~/types/contrat-api'

function getApiBase(): string {
  const config = useRuntimeConfig()
  return (config.public.apiBase as string) ?? ''
}

function authHeaders(): Record<string, string> {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    const token = parsed?.token ?? ''
    if (!token) return {}
    return { Authorization: `Bearer ${token}` }
  } catch {
    return {}
  }
}

/**
 * Adapte le payload frontend (form + articles) vers ce que Laravel attend.
 * Laravel: { entreprise_id, date_debut, date_fin, duree_mois, prix_mensuel,
 *            prix_total, statut, article_ids[] }
 */
function adaptPayload(payload: ContratPayload): Record<string, unknown> {
  // Les articles custom ont un id string (ex: "c1"), on garde uniquement
  // les ids numériques (articles DB). Les builtins string sont ignorés côté DB
  // car ils sont gérés localement — on envoie les ids numériques uniquement.
  const numericArticleIds = payload.articles
    .map((a) => Number(a.id))
    .filter((n) => !isNaN(n) && n > 0)

  return {
    // entreprise_id est résolu via societe — si tu as l'id, passe-le directement
    // Pour l'instant on stocke tout le payload dans un champ JSON "payload"
    // => voir note ci-dessous
    date_debut: payload.form.dateDebut || null,
    date_fin: payload.form.dateFin || null,
    duree_mois: payload.form.months || null,
    prix_mensuel: payload.totals.monthly || null,
    prix_total: payload.totals.global || null,
    statut: 'draft',
    article_ids: numericArticleIds,
    // On stocke le payload complet pour la regénération PDF
    meta: JSON.stringify({
      form: payload.form,
      selectedServices: payload.selectedServices,
      articles: payload.articles,
    }),
  }
}

export const contratService = {
  async createDraft(
    payload: ContratPayload,
    entrepriseId: number
  ): Promise<ApiSuccess<ContratEntity>> {
    return await $fetch(`${getApiBase()}/api/contrats`, {
      method: 'POST',
      headers: authHeaders(),
      body: { ...adaptPayload(payload), entreprise_id: entrepriseId },
    })
  },

  async updateDraft(
    id: string,
    payload: ContratPayload,
    entrepriseId: number
  ): Promise<ApiSuccess<ContratEntity>> {
    return await $fetch(`${getApiBase()}/api/contrats/${id}`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { ...adaptPayload(payload), entreprise_id: entrepriseId },
    })
  },

  async list(): Promise<ApiSuccess<ContratEntity[]>> {
    return await $fetch(`${getApiBase()}/api/contrats`, {
      headers: authHeaders(),
    })
  },

  async getById(id: string): Promise<ApiSuccess<ContratEntity>> {
    return await $fetch(`${getApiBase()}/api/contrats/${id}`, {
      headers: authHeaders(),
    })
  },

  async generatePdf(id: string): Promise<ApiSuccess<{ url: string; pdf_path: string }>> {
    return await $fetch(`${getApiBase()}/api/contrats/${id}/pdf`, {
      method: 'POST',
      headers: authHeaders(),
    })
  },
}