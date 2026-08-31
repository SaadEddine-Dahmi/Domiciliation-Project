// app/types/contrat-api.ts
// Defines API shapes for contracts and renewals.

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
  archived_at: string | null
  pdf_path: string | null
  scanned_pdf_path: string | null
  renewed_from_id: number | null
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
