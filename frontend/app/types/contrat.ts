// app/types/contrat.ts
// Defines contract wizard domain types.

export interface CompanyInfo {
  companyName: string
  companyRC: string
  companyIF: string
  companyTP: string
  companyRepresentant: string
  companyCIN: string
  companyAdresse: string
}

export interface ClientInfo {
  societe: string
  gerantNom: string
  gerantCIN: string
  tel: string
  email: string
  adressePerso: string
}

export interface ContratTerms {
  dateDebut: string
  dateFin: string
  months: number
  redevanceMensuelle: number
  redevanceAnnuelle: number
}

export interface ContratArticle {
  id: string
  ordre: number
  title?: string
  body?: string
}

export interface ContratForm extends CompanyInfo, ClientInfo, ContratTerms {
  titreContrat: string
  instruction_no: string
  ville_signature: string
  date_signature: string
  caution: string
  mode_paiement: string
}
