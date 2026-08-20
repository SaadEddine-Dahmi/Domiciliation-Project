// app/types/contrat-api.ts
//
// Domain types shared across the contract wizard, the contracts list, and
// the renewal feature.

import type { ContratArticle } from '~/types/contrat'

export type ContratStatus = 'draft' | 'active' | 'expired' | 'terminated'

export interface ContratPayload {
  form: {
    companyName: string
    companyRC: string
    companyIF: string
    companyTP: string
    companyRepresentant: string
    companyCIN: string
    companyAdresse: string

    // Fully dynamic — the exact title printed on the PDF, chosen by the
    // person creating the contract. Never hardcoded.
    titreContrat: string

    societe: string
    gerantNom: string
    gerantCIN: string
    tel: string
    email: string
    adressePerso: string

    dateDebut: string
    dateFin: string
    months: number
    redevanceMensuelle: number
    redevanceAnnuelle: number

    instruction_no: string
    ville_signature: string
    date_signature: string
    caution: string
    mode_paiement: string
  }
  selectedServices: string[]
  articles: ContratArticle[]
  totals: {
    monthly: number
    global: number
  }
}

export interface ContratEntity {
  id: string
  entreprise_id: number
  domiciliataire_id: number
  titre_contrat: string | null
  instruction_no: string | null
  date_debut: string | null
  date_fin: string | null
  duree_mois: number | null
  prix_mensuel: string | number | null
  prix_total: string | number | null
  caution: string | number | null
  mode_paiement: string | null
  statut: ContratStatus

  // Deprecated: the backend no longer writes this. Draft/active/expired/
  // terminated contracts are rendered on demand at preview/download time
  // and are never cached to disk, so this stays null going forward. Kept
  // only so any older rows that already have a value don't break existing
  // consumers of this type.
  pdf_path: string | null

  // Path (relative to the "public" disk) to the real, physically signed
  // PDF the domiciliataire uploaded during activate(). Stored per client
  // under contrats/signed/{entreprise_id}/... — mirrors the folder layout
  // already used for other client documents. When set, this is the
  // authoritative document everywhere in the UI (list, preview, download).
  scanned_pdf_path: string | null

  // ── Renewal fields ────────────────────────────────────────────────────
  // renewed_from_id: set when this contract itself is the result of a
  // renewal — points to the source (predecessor) contract's id.
  renewed_from_id: number | null

  // renewed_to: present when THIS contract has already been renewed —
  // points to the successor contract. Null/undefined means it can still
  // be renewed (subject to status rules enforced server-side).
  renewed_to?: { id: number; renewed_from_id: number } | null

  entreprise?: {
    id: number
    raison_sociale: string
    [key: string]: unknown
  }

  createdAt?: string
  updatedAt?: string
}

export interface ApiSuccess<T> {
  success: true
  data: T
  message?: string
}