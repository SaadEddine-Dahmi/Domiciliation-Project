// app/types/entreprise.ts

export interface Representant {
  id: number
  entreprise_id: number
  nom: string
  prenom?: string
  cin: string
  nationalite?: string
  date_naissance?: string
  adresse?: string
  telephone?: string
  email?: string
  nom_complet?: string // accessor from backend
  created_at?: string
  updated_at?: string
}

export interface Entreprise {
  id: number
  domiciliataire_id: number
  client_user_id?: number | null
  raison_sociale: string
  forme_juridique?: string
  adresse?: string
  ville?: string
  pays?: string
  capital?: string | number
  date_creation?: string
  statut?: string
  representant?: Representant | null  // hasOne — single representant
  client_user?: {
    id: number
    nom: string
    prenom?: string
    email: string
    telephone?: string
  } | null
  created_at?: string
  updated_at?: string
}