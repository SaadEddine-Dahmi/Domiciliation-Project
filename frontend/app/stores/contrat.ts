// ============================================================
// stores/contrat.ts
//
// Pinia store for the 4-step contract creation wizard.
//
// NAMING CONVENTIONS:
//   company*     → fields describing the domiciliataire (service provider)
//   societe      → raison sociale of the client company
//   gerant*      → fields describing the client's legal representative (gérant)
//   No brand-specific names appear in this file.
//
// WIZARD STEPS → FORM FIELDS:
//   Step 1: companyName, companyRC, companyIF, companyTP, companyRepresentant,
//           companyCIN, companyAdresse, companyEmail, companyTelephone, titreContrat
//   Step 2: societe, gerantNom, gerantPrenom, gerantCIN, nationalite,
//           dateNaissance, adressePerso, tel, email
//   Step 3: dateDebut, dateFin, months, redevanceMensuelle, redevanceAnnuelle,
//           instruction_no, ville_signature, date_signature, caution, mode_paiement
//   Step 4: read-only confirmation + PDF preview
//
// KEY DESIGN DECISIONS:
//   fillFromProfile(): reads the GET /api/profile response and populates step-1
//     fields. Called once on wizard mount. Does NOT set companyAdresse — that
//     is chosen via the address chip selector in step 1.
//
//   fillFromClient(): reads an Entreprise object (with eager-loaded representant)
//     and populates all step-2 fields including the new gerantPrenom, nationalite.
//     Called when the user selects an existing client in step 2.
//
//   monthlyTotal / grandTotal getters: computed from redevanceMensuelle + add-on
//     services total, used to calculate prix_mensuel and prix_total sent to the API.
//
//   setDateDebut / setMonths / setMonthly / setAnnual: keep dates and amounts
//     in sync automatically so the user only needs to change one field.
// ============================================================

import { defineStore } from 'pinia'

// ── Types ─────────────────────────────────────────────────────────────────────

/**
 * When the user changes the number of months, we need to know which amount
 * field (monthly or annual) to keep fixed and which to recompute.
 * 'monthly' → keep prix_mensuel fixed, recompute prix_total
 * 'annual'  → keep prix_total fixed, recompute prix_mensuel
 * ''        → default; monthly is the source of truth
 */
type LastEdited = '' | 'monthly' | 'annual'

/** An optional add-on service the domiciliataire can bundle with the contract. */
interface ServiceOption {
    id: string
    label: string
    price: number     // monthly price in DH
}

// ── Pure date helpers ─────────────────────────────────────────────────────────

/**
 * Returns today's date as YYYY-MM-DD using the LOCAL timezone.
 *
 * WHY NOT new Date().toISOString().split('T')[0]:
 *   toISOString() always returns UTC time. For users west of UTC (e.g. UTC-1),
 *   calling this after local midnight but before UTC midnight returns yesterday's
 *   date, making the wizard's date inputs show the wrong day on first render.
 *   This helper builds the string from local year/month/day values instead.
 */
function todayISO(): string {
    const d = new Date()
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
}

/**
 * Add a number of calendar months to a YYYY-MM-DD date string.
 *
 * Month-end clamping is handled automatically by Date.setMonth():
 *   addMonths('2026-01-31', 1) → '2026-02-28'  (Feb 31 does not exist)
 *
 * Returns '' on invalid or empty input so callers can safely assign the result
 * to a date input value without triggering the browser's 'Invalid Date' display.
 */
function addMonths(isoDate: string, months: number): string {
    if (!isoDate) return ''
    const d = new Date(isoDate)
    if (isNaN(d.getTime())) return ''
    d.setMonth(d.getMonth() + months)
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
}

// ── Numeric helpers ───────────────────────────────────────────────────────────

/** Convert any value to a finite number; returns 0 for NaN or Infinity. */
const toNum = (v: unknown): number => {
    const n = Number(v)
    return Number.isFinite(n) ? n : 0
}

/** Round a number to exactly 2 decimal places without floating-point drift. */
const round2 = (v: number): number => Math.round(v * 100) / 100

// ── Store ─────────────────────────────────────────────────────────────────────

export const useContractStore = defineStore('contrat', {

    // ── State ───────────────────────────────────────────────────────────────────

    state: () => ({
        form: {

            // ── Step 1: Domiciliataire fields ────────────────────────────────────
            // All set by fillFromProfile() on wizard mount from GET /api/profile.
            // companyAdresse is NOT set from profile — picked via address chip selector.

            companyName: '',   // nom_societe
            companyRC: '',   // rc (registre du commerce)
            companyIF: '',   // if_fiscal
            companyTP: '',   // tp (taxe professionnelle)
            companyRepresentant: '',   // representant_legal
            companyCIN: '',   // identite_representant (CIN du représentant légal)
            companyAdresse: '',   // chosen via chip selector in wizard step 1
            companyEmail: '',   // email — FIX: was missing in previous version
            companyTelephone: '',   // telephone — FIX: was missing in previous version

            // Contract title: typed freely by the domiciliataire in wizard step 1.
            // Printed centred at the top of the PDF.
            // Empty string → backend applies 'Contrat de Domiciliation' as default.
            titreContrat: '',

            // ── Step 2: Client / domicilié fields ────────────────────────────────
            // Set either by fillFromClient() (existing client) or by watches on
            // newClientForm (new client creation).

            societe: '',   // raison_sociale of the client company
            gerantNom: '',   // NOM (surname) of the legal representative
            gerantPrenom: '',   // Prénom of the legal representative — FIX: new field
            gerantCIN: '',   // CIN or passport number
            nationalite: '',   // nationality — FIX: new field
            dateNaissance: '',   // date of birth (YYYY-MM-DD, from representant)
            tel: '',   // telephone
            email: '',   // email (portal account email)
            adressePerso: '',   // personal address of the gérant

            // ── Step 3: Duration and amounts ─────────────────────────────────────
            // dateDebut and dateFin are initialised to real dates so the HTML date
            // inputs never show the browser's "mm/dd/yyyy" placeholder on first render.
            dateDebut: todayISO(),
            dateFin: addMonths(todayISO(), 12),
            months: 12,
            redevanceMensuelle: 0,
            redevanceAnnuelle: 0,
            lastEdited: '' as LastEdited,

            // Contract metadata
            instruction_no: '',   // reference number (e.g. "1923")
            ville_signature: '',   // city where the contract is signed
            date_signature: '',   // date of signing
            caution: '',   // security deposit amount
            mode_paiement: '',   // "Virement", "Espèces", "Chèque", etc.
        },

        // ── Add-on services (optional bundles) ──────────────────────────────────
        // Shown alongside the financial fields in step 3.
        // Selecting these increases monthlyTotal and grandTotal.
        serviceOptions: [
            { id: 'scan', label: 'Scan courrier', price: 100 },
            { id: 'forward', label: 'Réexpédition', price: 150 },
            { id: 'assist', label: 'Assistance admin', price: 200 },
        ] as ServiceOption[],

        /** IDs of currently selected add-on services. */
        selectedServices: [] as string[],
    }),

    // ── Getters ─────────────────────────────────────────────────────────────────

    getters: {
        /**
         * Total monthly cost of all selected add-on services.
         * Example: scan(100) + forward(150) = 250 DH/month.
         */
        monthlyServicesTotal(state): number {
            return state.selectedServices.reduce((sum, id) => {
                const s = state.serviceOptions.find(x => x.id === id)
                return sum + (s?.price ?? 0)
            }, 0)
        },

        /**
         * Total monthly amount = redevance mensuelle + add-on services.
         * This is the value sent as prix_mensuel to the API.
         */
        monthlyTotal(): number {
            return round2(toNum(this.form.redevanceMensuelle) + this.monthlyServicesTotal)
        },

        /**
         * Total contract value = monthlyTotal × duration in months.
         * This is the value sent as prix_total to the API.
         * Uses Math.max(1, months) to prevent division-by-zero and zero totals.
         */
        grandTotal(): number {
            return round2(this.monthlyTotal * Math.max(1, toNum(this.form.months)))
        },
    },

    // ── Actions ─────────────────────────────────────────────────────────────────

    actions: {
        /**
         * fillFromProfile()
         *
         * Populates all wizard step-1 domiciliataire fields from the GET /api/profile
         * response. Called once on wizard mount after the profile fetch completes.
         *
         * EXCLUDED: companyAdresse — the user selects this from the address chip
         * selector in step 1 so the address appears in the PDF. If we pre-filled it
         * from the profile, the user might not notice which branch is selected.
         *
         * FIXED: companyEmail and companyTelephone are now populated. Previously these
         * were missing, meaning the {{domiciliataire_email}} and {{domiciliataire_telephone}}
         * tokens always resolved to empty strings in article bodies.
         */
        fillFromProfile(profile: {
            nom_societe?: string
            rc?: string
            if_fiscal?: string
            tp?: string
            representant_legal?: string
            identite_representant?: string
            email?: string
            telephone?: string
        }): void {
            this.form.companyName = profile.nom_societe ?? ''
            this.form.companyRC = profile.rc ?? ''
            this.form.companyIF = profile.if_fiscal ?? ''
            this.form.companyTP = profile.tp ?? ''
            this.form.companyRepresentant = profile.representant_legal ?? ''
            this.form.companyCIN = profile.identite_representant ?? ''
            this.form.companyEmail = profile.email ?? ''   // FIX
            this.form.companyTelephone = profile.telephone ?? ''   // FIX
        },

        /**
         * fillFromClient()
         *
         * Populates all wizard step-2 client fields from a selected Entreprise object.
         * The Entreprise must have its `representant` relation eager-loaded (done by
         * ClientController::index() and ClientController::show()).
         *
         * FIELD SOURCES:
         *   societe      → entreprise.raison_sociale
         *   gerantNom    → representant.nom_complet accessor, or nom+prenom joined,
         *                  falling back to clientUser.nom if no representant exists
         *   gerantPrenom → representant.prenom   (NEW — for PDF D'AUTRE PART)
         *   gerantCIN    → representant.cin
         *   nationalite  → representant.nationalite   (NEW)
         *   dateNaissance→ representant.date_naissance (YYYY-MM-DD string)
         *   tel          → representant.telephone, fallback to clientUser.telephone
         *   email        → representant.email, fallback to clientUser.email
         *   adressePerso → representant.adresse
         */
        fillFromClient(client: {
            raison_sociale?: string
            representant?: {
                nom?: string
                prenom?: string
                nom_complet?: string
                cin?: string
                nationalite?: string
                date_naissance?: string
                adresse?: string
                telephone?: string
                email?: string
            } | null
            client_user?: {
                nom?: string
                prenom?: string
                email?: string
                telephone?: string
            } | null
        }): void {
            const rep = client.representant

            this.form.societe = client.raison_sociale ?? ''

            // Full name: prefer nom_complet accessor if available, otherwise join parts.
            // Falls back to clientUser name when no representant record exists.
            this.form.gerantNom = (
                rep?.nom_complet
                ?? (rep ? `${rep.prenom ?? ''} ${rep.nom ?? ''}`.trim() : '')
            ) || `${client.client_user?.nom ?? ''} ${client.client_user?.prenom ?? ''}`.trim()

            // NEW: prénom separately for PDF articles that need just the first name.
            this.form.gerantPrenom = rep?.prenom ?? ''

            this.form.gerantCIN = rep?.cin ?? ''

            // NEW: nationality for the D'AUTRE PART section.
            this.form.nationalite = rep?.nationalite ?? ''

            // date_naissance: YYYY-MM-DD string compatible with HTML date input.
            this.form.dateNaissance = rep?.date_naissance ?? ''

            // Telephone: prefer representant, fall back to portal account telephone.
            this.form.tel = rep?.telephone ?? client.client_user?.telephone ?? ''

            // Email: prefer representant, fall back to portal account email.
            this.form.email = rep?.email ?? client.client_user?.email ?? ''

            // Personal address of the gérant ("Demeurant à" line in the PDF).
            this.form.adressePerso = rep?.adresse ?? ''
        },

        /**
         * setDateDebut()
         *
         * Updates the contract start date and auto-recomputes the end date.
         * Called by the date-début input's @change handler in wizard step 3.
         *
         * Always keeps dateFin = dateDebut + months so the two fields stay in sync.
         * The user only needs to change one field; the other updates automatically.
         */
        setDateDebut(isoDate: string): void {
            this.form.dateDebut = isoDate
            if (isoDate && this.form.months > 0) {
                this.form.dateFin = addMonths(isoDate, this.form.months)
            }
        },

        /**
         * setMonths()
         *
         * Updates the contract duration and recomputes both dateFin and the
         * redevance amounts to stay consistent with the new duration.
         *
         * Uses Math.max(1, ...) to prevent a duration of 0 months.
         * Uses Math.round to prevent fractional months from a programmatic call.
         */
        setMonths(months: number): void {
            const m = Math.max(1, Math.round(toNum(months)))
            this.form.months = m
            if (this.form.dateDebut) {
                this.form.dateFin = addMonths(this.form.dateDebut, m)
            }
            this.syncFromMonths()
        },

        /**
         * setMonthly()
         *
         * Sets the monthly redevance and recomputes the annual total.
         * Marks lastEdited = 'monthly' so syncFromMonths() knows to keep the
         * monthly amount fixed if the user later changes the duration.
         */
        setMonthly(v: number): void {
            this.form.redevanceMensuelle = round2(toNum(v))
            this.form.lastEdited = 'monthly'
            this.form.redevanceAnnuelle = round2(
                this.form.redevanceMensuelle * Math.max(1, toNum(this.form.months))
            )
        },

        /**
         * setAnnual()
         *
         * Sets the annual redevance and back-calculates the monthly amount.
         * Marks lastEdited = 'annual' so syncFromMonths() knows to keep the
         * annual amount fixed if the user later changes the duration.
         */
        setAnnual(v: number): void {
            this.form.redevanceAnnuelle = round2(toNum(v))
            this.form.lastEdited = 'annual'
            this.form.redevanceMensuelle = round2(
                this.form.redevanceAnnuelle / Math.max(1, toNum(this.form.months))
            )
        },

        /**
         * syncFromMonths()
         *
         * Called whenever the duration changes. Recomputes the "other" amount field
         * while keeping the "last edited" field fixed.
         *
         * Example: user typed the annual amount first (lastEdited = 'annual'),
         * then changed months from 12 to 24. syncFromMonths() keeps the annual
         * total the same and divides by 24 to get the new monthly amount.
         */
        syncFromMonths(): void {
            if (this.form.lastEdited === 'annual') {
                this.setAnnual(this.form.redevanceAnnuelle)
            } else {
                this.setMonthly(this.form.redevanceMensuelle)
            }
        },

        /**
         * toggleService()
         *
         * Adds or removes a service add-on from the selected set, then recomputes
         * the redevance amounts so monthlyTotal and grandTotal reflect the change.
         */
        toggleService(id: string): void {
            if (this.selectedServices.includes(id)) {
                this.selectedServices = this.selectedServices.filter(x => x !== id)
            } else {
                this.selectedServices.push(id)
            }
            this.syncFromMonths()
        },

        /**
         * resetForm()
         *
         * Resets all wizard fields to their empty/default state.
         * Used when navigating back to the wizard to create a second contract.
         *
         * Dates are reset to today / today+12months (not blank) so the date inputs
         * never show the browser placeholder "mm/dd/yyyy" on a fresh wizard.
         */
        resetForm(): void {
            const today = todayISO()
            const in12m = addMonths(today, 12)

            Object.assign(this.form, {
                companyName: '',
                companyRC: '',
                companyIF: '',
                companyTP: '',
                companyRepresentant: '',
                companyCIN: '',
                companyAdresse: '',
                companyEmail: '',        // FIX: included in reset
                companyTelephone: '',        // FIX: included in reset
                titreContrat: '',
                societe: '',
                gerantNom: '',
                gerantPrenom: '',        // FIX: included in reset
                gerantCIN: '',
                nationalite: '',        // FIX: included in reset
                dateNaissance: '',
                tel: '',
                email: '',
                adressePerso: '',
                dateDebut: today,
                dateFin: in12m,
                months: 12,
                redevanceMensuelle: 0,
                redevanceAnnuelle: 0,
                lastEdited: '' as LastEdited,
                instruction_no: '',
                ville_signature: '',
                date_signature: '',
                caution: '',
                mode_paiement: '',
            })
            this.selectedServices = []
        },
    },
})