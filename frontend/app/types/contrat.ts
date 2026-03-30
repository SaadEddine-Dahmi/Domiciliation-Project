export interface AstFiscInfo {
    astNom: string
    astRC: string
    astIF: string
    astRepresentant: string
    astCIN: string
    astAdresse: string
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
    months: number
    monthlyFee: number
    legalFees: number
}

export interface ContratArticle {
    id: string
    label?: string
    title?: string
    body?: string
    builtIn?: boolean
}

export interface ContratForm extends AstFiscInfo, ClientInfo, ContratTerms { }