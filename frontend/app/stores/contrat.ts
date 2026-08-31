// app/stores/contrat.ts
// Manages the contract wizard form, totals, and hydration helpers.

import { defineStore } from 'pinia'

type LastEdited = '' | 'monthly' | 'annual'

interface ServiceOption {
  id: string
  label: string
  price: number
}

function todayISO(): string {
  const date = new Date()
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function addMonths(isoDate: string, months: number): string {
  if (!isoDate) return ''

  const date = new Date(isoDate)
  if (Number.isNaN(date.getTime())) return ''

  date.setMonth(date.getMonth() + months)
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function toNum(value: unknown): number {
  const number = Number(value)
  return Number.isFinite(number) ? number : 0
}

function round2(value: number): number {
  return Math.round(value * 100) / 100
}

function fullName(input?: { nom?: string; prenom?: string; nom_complet?: string } | null): string {
  return input?.nom_complet ?? (input ? `${input.prenom ?? ''} ${input.nom ?? ''}`.trim() : '')
}

export const useContractStore = defineStore('contrat', {
  state: () => ({
    form: {
      companyName: '',
      companyRC: '',
      companyIF: '',
      companyTP: '',
      companyRepresentant: '',
      companyCIN: '',
      companyAdresse: '',
      companyEmail: '',
      companyTelephone: '',
      titreContrat: '',

      societe: '',
      gerantNom: '',
      gerantPrenom: '',
      gerantCIN: '',
      nationalite: '',
      dateNaissance: '',
      tel: '',
      email: '',
      adressePerso: '',

      dateDebut: todayISO(),
      dateFin: addMonths(todayISO(), 12),
      months: 12,
      redevanceMensuelle: 0,
      redevanceAnnuelle: 0,
      lastEdited: '' as LastEdited,

      instruction_no: '',
      ville_signature: '',
      date_signature: '',
      caution: '',
      mode_paiement: '',
    },

    serviceOptions: [
      { id: 'scan', label: 'Scan courrier', price: 100 },
      { id: 'forward', label: 'Reexpedition', price: 150 },
      { id: 'assist', label: 'Assistance admin', price: 200 },
    ] as ServiceOption[],

    selectedServices: [] as string[],
  }),

  getters: {
    monthlyServicesTotal(state): number {
      return state.selectedServices.reduce((sum, id) => {
        const service = state.serviceOptions.find(item => item.id === id)
        return sum + (service?.price ?? 0)
      }, 0)
    },

    monthlyTotal(): number {
      return round2(toNum(this.form.redevanceMensuelle) + this.monthlyServicesTotal)
    },

    grandTotal(): number {
      return round2(this.monthlyTotal * Math.max(1, toNum(this.form.months)))
    },
  },

  actions: {
    fillFromProfile(profile: {
      nom_societe?: string
      rc?: string
      if_fiscal?: string
      tp?: string
      representant?: {
        nom?: string
        prenom?: string
        nom_complet?: string
        cin?: string
        telephone?: string
        email?: string
      } | null
      email?: string
      telephone?: string
    }): void {
      const representant = profile.representant

      this.form.companyName = profile.nom_societe ?? ''
      this.form.companyRC = profile.rc ?? ''
      this.form.companyIF = profile.if_fiscal ?? ''
      this.form.companyTP = profile.tp ?? ''
      this.form.companyRepresentant = fullName(representant)
      this.form.companyCIN = representant?.cin ?? ''
      this.form.companyEmail = profile.email ?? representant?.email ?? ''
      this.form.companyTelephone = profile.telephone ?? representant?.telephone ?? ''
    },

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
      const representant = client.representant

      this.form.societe = client.raison_sociale ?? ''
      this.form.gerantNom = fullName(representant)
        || `${client.client_user?.nom ?? ''} ${client.client_user?.prenom ?? ''}`.trim()
      this.form.gerantPrenom = representant?.prenom ?? ''
      this.form.gerantCIN = representant?.cin ?? ''
      this.form.nationalite = representant?.nationalite ?? ''
      this.form.dateNaissance = representant?.date_naissance ?? ''
      this.form.tel = representant?.telephone ?? client.client_user?.telephone ?? ''
      this.form.email = representant?.email ?? client.client_user?.email ?? ''
      this.form.adressePerso = representant?.adresse ?? ''
    },

    setDateDebut(isoDate: string): void {
      this.form.dateDebut = isoDate
      if (isoDate && this.form.months > 0) {
        this.form.dateFin = addMonths(isoDate, this.form.months)
      }
    },

    setMonths(months: number): void {
      const duration = Math.max(1, Math.round(toNum(months)))
      this.form.months = duration

      if (this.form.dateDebut) {
        this.form.dateFin = addMonths(this.form.dateDebut, duration)
      }

      this.syncFromMonths()
    },

    setMonthly(value: number): void {
      this.form.redevanceMensuelle = round2(toNum(value))
      this.form.lastEdited = 'monthly'
      this.form.redevanceAnnuelle = round2(
        this.form.redevanceMensuelle * Math.max(1, toNum(this.form.months))
      )
    },

    setAnnual(value: number): void {
      this.form.redevanceAnnuelle = round2(toNum(value))
      this.form.lastEdited = 'annual'
      this.form.redevanceMensuelle = round2(
        this.form.redevanceAnnuelle / Math.max(1, toNum(this.form.months))
      )
    },

    syncFromMonths(): void {
      if (this.form.lastEdited === 'annual') {
        this.setAnnual(this.form.redevanceAnnuelle)
        return
      }

      this.setMonthly(this.form.redevanceMensuelle)
    },

    toggleService(id: string): void {
      if (this.selectedServices.includes(id)) {
        this.selectedServices = this.selectedServices.filter(item => item !== id)
      } else {
        this.selectedServices.push(id)
      }

      this.syncFromMonths()
    },

    resetForm(): void {
      const today = todayISO()

      Object.assign(this.form, {
        companyName: '',
        companyRC: '',
        companyIF: '',
        companyTP: '',
        companyRepresentant: '',
        companyCIN: '',
        companyAdresse: '',
        companyEmail: '',
        companyTelephone: '',
        titreContrat: '',
        societe: '',
        gerantNom: '',
        gerantPrenom: '',
        gerantCIN: '',
        nationalite: '',
        dateNaissance: '',
        tel: '',
        email: '',
        adressePerso: '',
        dateDebut: today,
        dateFin: addMonths(today, 12),
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
