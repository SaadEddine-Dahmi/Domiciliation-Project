// services/contrat.service.ts
//
// HTTP client layer for the /api/contrats resource.
//
// Responsibilities:
//   - Build the Authorization header from localStorage ('app_auth' key)
//   - Map the wizard's ContratPayload to the request body Laravel expects
//   - Expose one typed method per /api/contrats endpoint, including the
//     renewal endpoint added in this feature
//   - Provide the PDF stream URL for use in <iframe src="...">
//
// Article ID handling:
//   IDs come from the wizard as strings ("3", "14"). adaptPayload() always
//   passes them as String(a.id), never Number(). String() is safe for
//   both integer primary keys and UUID primary keys. The backend's
//   syncArticles() casts to (int) for integer-key tables.
//
// titreContrat:
//   Sent exactly as typed by the person creating the contract — no
//   frontend default is applied here. The backend applies a neutral
//   fallback only when the field arrives null or empty. This keeps the
//   default logic in a single place and lets the contract title stay
//   fully dynamic per document.

import type { ApiSuccess, ContratEntity, ContratPayload } from '~/types/contrat-api'

// ── Private helpers ──────────────────────────────────────────────────────

/** Read the configured API base URL from Nuxt runtime config. */
function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
}

/**
 * Build the Authorization header from localStorage.
 *
 * Key: 'app_auth' — written by AuthController on successful login.
 * Expected value shape: { token: "...", user: { ... } }
 *
 * Returns {} when called server-side (no window / localStorage), and
 * when the token is missing or the JSON is malformed.
 */
function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
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
 * Map the wizard's ContratPayload to the request body shape Laravel
 * expects for creation/update.
 *
 * Key decisions:
 *   - titre_contrat is sent as-is from the store. The person typed this
 *     value in step 1. null is sent when the field is empty so the
 *     backend can apply its default in one place.
 *   - Articles are [{id: "3", ordre: 1}, ...]. Always string IDs, never
 *     filtered by isNaN() since that would silently drop UUID-keyed
 *     articles.
 *   - Empty optional strings are sent as null to avoid failing Laravel's
 *     'date' or 'numeric' validation rules on empty strings.
 *   - ordre falls back to array index + 1 when not explicitly provided.
 */
function adaptPayload(payload: ContratPayload): Record<string, unknown> {
    const articles = payload.articles.map((a, index) => ({
        id: String(a.id),
        ordre: typeof a.ordre === 'number' ? a.ordre : index + 1,
    }))

    return {
        // ── Contract title — fully dynamic, chosen by the person ─────────
        titre_contrat: payload.form.titreContrat || null,

        // ── Domiciliataire company fields ─────────────────────────────────
        company_name: payload.form.companyName,
        company_rc: payload.form.companyRC,
        company_if: payload.form.companyIF,
        company_tp: payload.form.companyTP,
        company_representant: payload.form.companyRepresentant,
        company_cin: payload.form.companyCIN,
        company_adresse: payload.form.companyAdresse,

        // ── Client / domicilié fields ─────────────────────────────────────
        societe: payload.form.societe,
        gerant_nom: payload.form.gerantNom,
        gerant_cin: payload.form.gerantCIN,
        tel: payload.form.tel,
        email: payload.form.email,
        adresse_perso: payload.form.adressePerso,

        // ── Duration and amounts ──────────────────────────────────────────
        date_debut: payload.form.dateDebut || null,
        date_fin: payload.form.dateFin || null,
        duree_mois: payload.form.months || null,
        prix_mensuel: payload.totals.monthly || null,
        prix_total: payload.totals.global || null,

        // ── Optional metadata fields ──────────────────────────────────────
        instruction_no: payload.form.instruction_no || null,
        ville_signature: payload.form.ville_signature || null,
        date_signature: payload.form.date_signature || null,
        caution: payload.form.caution || null,
        mode_paiement: payload.form.mode_paiement || null,

        statut: 'draft',

        // ── Articles with display order ───────────────────────────────────
        articles,
    }
}

// ── Service ───────────────────────────────────────────────────────────────

export const contratService = {

    /**
     * POST /api/contrats
     * Create a new draft contract with all wizard data.
     */
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

    /**
     * PUT /api/contrats/{id}
     * Update an existing draft contract.
     */
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

    /**
     * GET /api/contrats
     * List all contracts for the authenticated domiciliataire.
     */
    async list(): Promise<ApiSuccess<ContratEntity[]>> {
        return await $fetch(`${getApiBase()}/api/contrats`, {
            headers: authHeaders(),
        })
    },

    /**
     * GET /api/contrats/{id}
     * Fetch one contract by ID with its ordered articles.
     */
    async getById(id: string): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}`, {
            headers: authHeaders(),
        })
    },

    /**
     * POST /api/contrats/{id}/pdf
     * Generate the PDF and save it to disk on the server.
     * Returns the public storage URL and the relative storage path.
     */
    async generatePdf(id: string): Promise<ApiSuccess<{ url: string; pdf_path: string }>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}/pdf`, {
            method: 'POST',
            headers: authHeaders(),
        })
    },

    /**
     * POST /api/contrats/{id}/renew
     *
     * Renews an expired or active contract by creating a new draft
     * contract linked to it. All fields are optional — omit them to
     * accept the server's computed defaults (continuity dates, same
     * price, same title, same articles).
     *
     * @param id         The source contract's id.
     * @param overrides  Optional fields to override the computed defaults.
     */
    async renew(
        id: string,
        overrides?: {
            date_debut?: string
            duree_mois?: number
            prix_mensuel?: number
            prix_total?: number
            titre_contrat?: string
            carry_articles?: boolean
        }
    ): Promise<ApiSuccess<ContratEntity>> {
        return await $fetch(`${getApiBase()}/api/contrats/${id}/renew`, {
            method: 'POST',
            headers: authHeaders(),
            body: overrides ?? {},
        })
    },

    /**
     * Returns the URL for the inline PDF stream endpoint.
     *
     * Usage:
     *   const url = contratService.streamPdfUrl(id)
     *   // <iframe :src="url" />
     *
     * Why no Authorization header:
     *   A browser <iframe src="..."> cannot attach custom HTTP headers.
     *   The stream route is therefore registered OUTSIDE auth:sanctum in
     *   routes/api.php. See ContratController::streamPdf().
     */
    streamPdfUrl(id: string): string {
        return `${getApiBase()}/api/contrats/${id}/pdf/stream`
    },
}