// stores/contrat.ts
//
// Pinia store for the 4-step contract creation wizard.
//
// Naming conventions:
//   company*           → fields describing the domiciliataire (your company)
//   societe / gerant*  → fields describing the client being domiciled
//   No application-specific brand names anywhere in this file.
//
// titreContrat:
//   Fully dynamic — the domiciliataire types whatever title they want printed
//   at the top of the contract PDF. Editable in wizard step 1.
//   Stored exactly as typed; the backend applies 'Contrat de Domiciliation'
//   as a fallback only when the field arrives null or empty.
//
// Date initialization:
//   dateDebut defaults to today's local date (YYYY-MM-DD, no UTC shift).
//   dateFin   defaults to today + 12 months.
//   Kept in sync via setDateDebut() and setMonths() store actions.

import { defineStore } from 'pinia'

// ── Types ─────────────────────────────────────────────────────────────────────

/**
 * Controls which redevance field is the "source of truth" when the months
 * value changes. 'monthly' keeps the monthly amount fixed and recomputes the
 * annual; 'annual' keeps the annual amount fixed and recomputes the monthly.
 */
type LastEdited = '' | 'monthly' | 'annual'

interface ServiceOption {
    id: string
    label: string
    price: number
}

// ── Pure date helpers ─────────────────────────────────────────────────────────

/**
 * Returns today's date as a YYYY-MM-DD string using the local timezone.
 *
 * Why not new Date().toISOString().split('T')[0]:
 *   toISOString() always returns UTC time. For users west of UTC (e.g. UTC-5),
 *   calling this after midnight local time but before midnight UTC returns
 *   yesterday's date, causing the date input to show the wrong day.
 *   This helper builds the string from local year/month/day instead.
 */
function todayISO(): string {
    const d = new Date()
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
}

/**
 * Adds a given number of calendar months to a YYYY-MM-DD date string.
 *
 * Handles month-end clamping automatically via Date.setMonth():
 *   addMonths('2026-01-31', 1) → '2026-02-28' (not Feb 31, which doesn't exist)
 *
 * Returns '' when the input is blank or unparseable so callers can safely
 * assign the result to a date input value without showing 'Invalid Date'.
 *
 * @param  isoDate  YYYY-MM-DD string — e.g. '2026-06-16'
 * @param  months   Number of whole months to add — e.g. 12
 * @returns         YYYY-MM-DD string — e.g. '2027-06-16', or '' on bad input
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

// ── Utility helpers ───────────────────────────────────────────────────────────

/** Safely convert any value to a finite number; returns 0 for NaN / Infinity */
const toNum = (v: unknown): number => {
    const n = Number(v)
    return Number.isFinite(n) ? n : 0
}

/** Round to exactly 2 decimal places without floating-point drift */
const round2 = (v: number): number => Math.round(v * 100) / 100

// ── Store ─────────────────────────────────────────────────────────────────────

export const useContractStore = defineStore('contrat', {

    state: () => ({
        form: {
            // ── Domiciliataire (autofilled from GET /api/profile on mount) ────
            // fillFromProfile() writes these once after the profile resolves.
            // companyAdresse is NOT set from the profile — the user picks it from
            // the address chip selector in wizard step 1.
            companyName: '',
            companyRC: '',
            companyIF: '',
            companyTP: '',
            companyRepresentant: '',
            companyCIN: '',
            companyAdresse: '',

            // ── Contract title — freely editable by the domiciliataire ────────
            // This is the exact string printed centred at the top of the PDF.
            // The domiciliataire can type any value they want, for example:
            //   "Contrat de Domiciliation"
            //   "Convention de Domiciliation Commerciale"
            //   "Contrat de Sous-location n°2026-042"
            // Empty string is valid on the frontend; the backend then falls back
            // to 'Contrat de Domiciliation' — see ContratController::store().
            titreContrat: '',

            // ── Client / domicilié (wizard step 2) ───────────────────────────
            societe: '',
            gerantNom: '',
            gerantCIN: '',
            tel: '',
            email: '',
            adressePerso: '',

            // ── Duration and amounts (wizard step 3) ─────────────────────────
            // dateDebut and dateFin are initialized to real dates so the inputs
            // are never shown with the browser placeholder 'mm/dd/yyyy' on first load.
            dateDebut: todayISO(),          // today, local timezone
            dateFin: addMonths(todayISO(), 12),  // today + 12 months
            months: 12,
            redevanceMensuelle: 0,
            redevanceAnnuelle: 0,
            lastEdited: '' as LastEdited,

            // ── Contract metadata ─────────────────────────────────────────────
            instruction_no: '',
            ville_signature: '',
            date_signature: '',
            caution: '',
            mode_paiement: '',
        },

        // ── Optional add-on services shown alongside financial fields ─────────
        serviceOptions: [
            { id: 'scan', label: 'Scan courrier', price: 100 },
            { id: 'forward', label: 'Réexpédition', price: 150 },
            { id: 'assist', label: 'Assistance admin', price: 200 },
        ] as ServiceOption[],

        selectedServices: [] as string[],
    }),

    // ── Getters ───────────────────────────────────────────────────────────────

    getters: {
        /** Total monthly cost of all selected add-on services */
        monthlyServicesTotal(state): number {
            return state.selectedServices.reduce((sum, id) => {
                const s = state.serviceOptions.find(x => x.id === id)
                return sum + (s?.price ?? 0)
            }, 0)
        },

        /** Monthly redevance + monthly services total */
        monthlyTotal(): number {
            return round2(toNum(this.form.redevanceMensuelle) + this.monthlyServicesTotal)
        },

        /** Monthly total × contract duration in months */
        grandTotal(): number {
            return round2(this.monthlyTotal * Math.max(1, toNum(this.form.months)))
        },
    },

    // ── Actions ───────────────────────────────────────────────────────────────

    actions: {
        /**
         * Autofill domiciliataire fields from the GET /api/profile response.
         * Called once on wizard mount after the profile fetch resolves.
         *
         * companyAdresse is intentionally excluded — the user picks that from
         * the address chip selector in step 1, not from the profile endpoint.
         */
        fillFromProfile(profile: {
            nom_societe?: string
            rc?: string
            if_fiscal?: string
            tp?: string
            representant_legal?: string
            identite_representant?: string
        }): void {
            this.form.companyName = profile.nom_societe ?? ''
            this.form.companyRC = profile.rc ?? ''
            this.form.companyIF = profile.if_fiscal ?? ''
            this.form.companyTP = profile.tp ?? ''
            this.form.companyRepresentant = profile.representant_legal ?? ''
            this.form.companyCIN = profile.identite_representant ?? ''
        },

        /**
         * Set the contract start date and auto-recompute the end date.
         *
         * Called by the date-de-début input's @change handler.
         * Always recomputes dateFin = dateDebut + months so the two fields
         * stay in sync without requiring the user to touch both.
         */
        setDateDebut(isoDate: string): void {
            this.form.dateDebut = isoDate
            if (isoDate && this.form.months > 0) {
                this.form.dateFin = addMonths(isoDate, this.form.months)
            }
        },

        /**
         * Set the contract duration in months and auto-recompute the end date.
         *
         * Called when the user changes the months input directly.
         * Recalculates dateFin and also calls syncFromMonths() to keep the
         * redevance amounts consistent with the new duration.
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
         * Set the monthly redevance and recalculate the annual total.
         *
         * Marks lastEdited = 'monthly' so syncFromMonths() knows which amount
         * field to keep fixed when the months value subsequently changes.
         */
        setMonthly(v: number): void {
            this.form.redevanceMensuelle = round2(toNum(v))
            this.form.lastEdited = 'monthly'
            this.form.redevanceAnnuelle = round2(
                this.form.redevanceMensuelle * Math.max(1, toNum(this.form.months))
            )
        },

        /**
         * Set the annual redevance and back-calculate the monthly amount.
         *
         * Marks lastEdited = 'annual'.
         */
        setAnnual(v: number): void {
            this.form.redevanceAnnuelle = round2(toNum(v))
            this.form.lastEdited = 'annual'
            this.form.redevanceMensuelle = round2(
                this.form.redevanceAnnuelle / Math.max(1, toNum(this.form.months))
            )
        },

        /**
         * Recalculate the dependent amount field after the months value changes.
         *
         * If the user last edited the annual field, keep the annual total fixed
         * and recompute the monthly. Otherwise keep the monthly fixed.
         * This preserves intent regardless of which field the user typed in first.
         */
        syncFromMonths(): void {
            if (this.form.lastEdited === 'annual') {
                this.setAnnual(this.form.redevanceAnnuelle)
            } else {
                this.setMonthly(this.form.redevanceMensuelle)
            }
        },

        /** Toggle a service add-on on or off and recalculate totals */
        toggleService(id: string): void {
            if (this.selectedServices.includes(id)) {
                this.selectedServices = this.selectedServices.filter(x => x !== id)
            } else {
                this.selectedServices.push(id)
            }
            this.syncFromMonths()
        },

        /**
         * Reset all wizard fields to their initial empty/default state.
         *
         * Dates reset to today / today+12months so the inputs are never blank
         * after a reset — avoids the 'mm/dd/yyyy' placeholder appearing mid-session.
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
                titreContrat: '',
                societe: '',
                gerantNom: '',
                gerantCIN: '',
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