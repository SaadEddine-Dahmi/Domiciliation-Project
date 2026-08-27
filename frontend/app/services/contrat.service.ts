// services/contrat.service.ts
//
// HTTP client layer for the /api/contrats resource.
//
// PDF/preview strategy: there is no "generate" call for previews.
// streamPdfUrl() builds the URL for the live-rendered document —
// mode=preview returns inline HTML/PDF (used in the <iframe> or preview
// modal), mode=download triggers a file download. Both hit the same
// backend endpoint. See ContratController::streamPdf().
//
// Renewal: renew() creates a brand-new draft Contrat linked back to the
// original via renewed_from_id (see Contrat::renew() on the backend).
// The returned draft is edited through the same wizard as any other
// draft — resume it via getById() + the wizard's ?edit= query param.

import type { ApiSuccess, ContratEntity, ContratPayload } from '~/types/contrat-api'

// ── Private helpers ───────────────────────────────────────────────────────────

function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

function isClientRuntime(): boolean {
    return import.meta.client || typeof window !== 'undefined'
}

/**
 * Build the Authorization header from localStorage.
 * Key: 'app_auth' — written by AuthController on successful login.
 * Returns {} when called server-side or when the token is missing.
 */
function authHeaders(): Record<string, string> {
    if (!isClientRuntime()) return {}
    try {
        const raw = localStorage.getItem('app_auth')
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
 * Extract the raw token string, used as a query param for the stream URL
 * since a browser <iframe>/<a> cannot attach an Authorization header.
 */
function getToken(): string {
    if (!isClientRuntime()) return ''
    try {
        return JSON.parse(localStorage.getItem('app_auth') ?? '{}')?.token ?? ''
    } catch { return '' }
}

/**
 * Map the wizard's ContratPayload to the request body shape Laravel expects.
 */
function adaptPayload(payload: ContratPayload): Record<string, unknown> {
    const articles = payload.articles.map((a, index) => ({
        id: String(a.id),
        ordre: typeof a.ordre === 'number' ? a.ordre : index + 1,
    }))

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
        articles,
    }
}

// ── Service ───────────────────────────────────────────────────────────────────

export const contratService = {

    /** POST /api/contrats — create a new draft contract */
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

    /** PUT /api/contrats/{id} — update an existing draft contract */
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

    /** GET /api/contrats — list all contracts for the authenticated domiciliataire */
    async list(): Promise<ApiSuccess<ContratEntity[]>> {
        return await $fetch(`${getApiBase()}/api/contrats`, {
            headers: authHeaders(),
        })
    },

    /**
     * GET /api/contrats/{id} — fetch one contract with its ordered articles,
     * entreprise/representant, and renewal chain references. Used both for
     * a normal detail view and to resume/edit an existing draft (including
     * renewal drafts) in the wizard.
     */
    async getById(id: string): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}`, {
            headers: authHeaders(),
        })
    },

    /** POST /api/contrats/{id}/activate — draft → active */
    async activate(id: string): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}/activate`, {
            method: 'POST', headers: authHeaders(),
        })
    },

    /** POST /api/contrats/{id}/terminate — active → terminated */
    async terminate(id: string): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}/terminate`, {
            method: 'POST', headers: authHeaders(),
        })
    },

    /**
     * POST /api/contrats/{id}/renew
     *
     * Creates a new draft contract carrying over the given contract's
     * terms (price, duration, articles, payment mode), linked back via
     * renewed_from_id. Throws (via $fetch) with a 422 and a French
     * message if the contract already has an open renewal, or is not
     * in a renewable state — surface e.data.message to the user.
     *
     * Returns the new draft's id, which the caller should route into
     * the wizard via `/admin/contrat?edit=<id>`.
     */
    async renew(id: string): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}/renew`, {
            method: 'POST', headers: authHeaders(),
        })
    },

    /** DELETE /api/contrats/{id} - remove a draft contract */
    async deleteDraft(id: string): Promise<{ success: boolean; message?: string }> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}`, {
            method: 'DELETE', headers: authHeaders(),
        })
    },

    /**
     * Builds the URL for the live-rendered contract document.
     *   mode='preview' (default) → inline, used in <iframe src> or the modal
     *   mode='download'          → triggers a file download
     */
    streamPdfUrl(id: string, mode: 'preview' | 'download' = 'preview'): string {
        const token = encodeURIComponent(getToken())
        return `${getApiBase()}/api/contrats/${id}/pdf/stream?token=${token}&mode=${mode}`
    },
}
