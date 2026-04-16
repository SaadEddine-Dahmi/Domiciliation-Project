// stores/contrat.ts
import { defineStore } from 'pinia'

type LastEdited = '' | 'monthly' | 'annual'

interface ServiceOption {
    id: string
    label: string
    price: number
}

const toNum = (v: unknown) => { const n = Number(v); return Number.isFinite(n) ? n : 0 }
const round2 = (v: number) => Math.round(v * 100) / 100

export const useContractStore = defineStore('contrat', {
    state: () => ({
        form: {
            // ── AST-FISC ───────────────────────────────────
            astNom: 'AST-FISC SARL AU',
            astRC: '',
            astIF: '',
            astRepresentant: '',
            astCIN: '',
            astAdresse: '',

            // ── Client ─────────────────────────────────────
            societe: '',
            gerantNom: '',
            gerantCIN: '',
            tel: '',
            email: '',
            adressePerso: '',

            // ── Durée + montants ───────────────────────────
            dateDebut: '',
            dateFin: '',
            months: 12,
            redevanceMensuelle: 0,
            redevanceAnnuelle: 0,
            lastEdited: '' as LastEdited,

            // ── Détails contrat (variables PDF) ───────────
            // These fields feed {{variables}} in article bodies
            instruction_no: '',   // → {{instruction_no}}
            ville_signature: '',   // → {{ville_signature}}
            date_signature: '',   // → {{date_signature}}
            caution: '',   // → {{caution}}
            mode_paiement: '',   // → {{mode_paiement}}
        },

        serviceOptions: [
            { id: 'scan', label: 'Scan courrier', price: 100 },
            { id: 'forward', label: 'Réexpédition', price: 150 },
            { id: 'assist', label: 'Assistance admin', price: 200 },
        ] as ServiceOption[],
        selectedServices: [] as string[],
    }),

    getters: {
        monthlyServicesTotal(state): number {
            return state.selectedServices.reduce((sum, id) => {
                const s = state.serviceOptions.find((x) => x.id === id)
                return sum + (s?.price || 0)
            }, 0)
        },
        monthlyTotal(): number {
            return round2(toNum(this.form.redevanceMensuelle) + this.monthlyServicesTotal)
        },
        grandTotal(): number {
            const m = Math.max(1, toNum(this.form.months))
            return round2(this.monthlyTotal * m)
        },
    },

    actions: {
        setMonthly(v: number) {
            this.form.redevanceMensuelle = round2(toNum(v))
            this.form.lastEdited = 'monthly'
            this.form.redevanceAnnuelle = round2(
                this.form.redevanceMensuelle * Math.max(1, toNum(this.form.months))
            )
        },
        setAnnual(v: number) {
            this.form.redevanceAnnuelle = round2(toNum(v))
            this.form.lastEdited = 'annual'
            this.form.redevanceMensuelle = round2(
                this.form.redevanceAnnuelle / Math.max(1, toNum(this.form.months))
            )
        },
        syncFromMonths() {
            if (this.form.lastEdited === 'annual') this.setAnnual(this.form.redevanceAnnuelle)
            else this.setMonthly(this.form.redevanceMensuelle)
        },
        toggleService(id: string) {
            if (this.selectedServices.includes(id)) {
                this.selectedServices = this.selectedServices.filter((x) => x !== id)
            } else {
                this.selectedServices.push(id)
            }
            this.syncFromMonths()
        },
        resetForm() {
            this.form.astNom = 'AST-FISC SARL AU'
            this.form.astRC = ''
            this.form.astIF = ''
            this.form.astRepresentant = ''
            this.form.astCIN = ''
            this.form.astAdresse = ''
            this.form.societe = ''
            this.form.gerantNom = ''
            this.form.gerantCIN = ''
            this.form.tel = ''
            this.form.email = ''
            this.form.adressePerso = ''
            this.form.dateDebut = ''
            this.form.dateFin = ''
            this.form.months = 12
            this.form.redevanceMensuelle = 0
            this.form.redevanceAnnuelle = 0
            this.form.lastEdited = ''
            this.form.instruction_no = ''
            this.form.ville_signature = ''
            this.form.date_signature = ''
            this.form.caution = ''
            this.form.mode_paiement = ''
            this.selectedServices = []
        },
    },
})