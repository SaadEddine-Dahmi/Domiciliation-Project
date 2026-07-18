// types/contrat.ts
//
// Domain types shared across the contract wizard components and stores.
//
// Naming conventions:
//   - All domiciliataire-side fields use generic names: company*
//   - No application-specific brand names appear anywhere in this codebase
//   - The application name will be decided separately — no hardcoded names here

// ── Domiciliataire identity ────────────────────────────────────────────────────
// These fields are autofilled from GET /api/profile when the wizard mounts.

export interface CompanyInfo {
    companyName: string   // Raison sociale / nom_societe
    companyRC: string   // Registre de commerce
    companyIF: string   // Identifiant fiscal
    companyTP: string   // Taxe professionnelle
    companyRepresentant: string   // Représentant légal
    companyCIN: string   // Pièce d'identité du représentant
    companyAdresse: string   // Adresse choisie dans le sélecteur de step 1
}

// ── Client (domicilié) identity ───────────────────────────────────────────────
// Filled in wizard step 2 by the domiciliataire.

export interface ClientInfo {
    societe: string   // Raison sociale du client
    gerantNom: string   // Nom du gérant
    gerantCIN: string   // CIN du gérant
    tel: string
    email: string
    adressePerso: string   // Adresse personnelle du gérant
}

// ── Duration and pricing ───────────────────────────────────────────────────────
// Filled in wizard step 2.

export interface ContratTerms {
    dateDebut: string   // ISO date string
    dateFin: string   // ISO date string
    months: number
    redevanceMensuelle: number
    redevanceAnnuelle: number
}

// ── A single article clause attached to the wizard ───────────────────────────
// IDs are always treated as strings on the frontend.
// PHP casts them to (int) when writing to the contrat_articles pivot table.

export interface ContratArticle {
    id: string   // string representation of the integer PK
    ordre: number   // drag-and-drop display order from wizard step 3
    title?: string
    body?: string
}

// ── Full wizard form shape ────────────────────────────────────────────────────

export interface ContratForm extends CompanyInfo, ClientInfo, ContratTerms {
    // The exact title string that appears centred at the top of the generated PDF.
    // Editable by the domiciliataire in wizard step 1.
    // The domiciliataire chooses this value — it is not hardcoded anywhere.
    // Examples: "Contrat de Domiciliation", "Contrat de Sous-location", etc.
    titreContrat: string

    // Contract metadata
    instruction_no: string   // Numéro de référence (imprimé sur le PDF)
    ville_signature: string
    date_signature: string
    caution: string
    mode_paiement: string
}