import type { ContratArticle } from '~/types/contrat'

export type ContratStatus = 'draft' | 'validated' | 'signed' | 'archived'

export interface ContratPayload {
  form: {
    astNom: string
    astRC: string
    astIF: string
    astRepresentant: string
    astCIN: string
    astAdresse: string

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
  ref: string
  status: ContratStatus
  payload: ContratPayload
  createdAt: string
  updatedAt: string
}

export interface ApiSuccess<T> {
  success: true
  data: T
  message?: string
}