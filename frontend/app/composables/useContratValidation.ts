import type { ContratForm } from '~/types/contrat'

type Errors = Record<string, string>
const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const phoneRe = /^[+0-9()\-\s]{8,20}$/

export function useContratValidation(form: ContratForm) {
  const errors = ref<Errors>({})

  function setErr(key: string, msg: string) {
    errors.value[key] = msg
  }

  function validateStep(step: number) {
    errors.value = {}

    if (step === 0) {
      if (!form.astNom?.trim()) setErr('astNom', 'Raison sociale requise')
      if (!form.astRC?.trim()) setErr('astRC', 'RC requis')
      if (!form.astIF?.trim()) setErr('astIF', 'IF requis')
      if (!form.astRepresentant?.trim()) setErr('astRepresentant', 'Représentant requis')
      if (!form.astCIN?.trim()) setErr('astCIN', 'CIN requis')
      if (!form.astAdresse?.trim()) setErr('astAdresse', 'Adresse requise')
    }

    if (step === 1) {
      if (!form.societe?.trim()) setErr('societe', 'Société requise')
      if (!form.gerantNom?.trim()) setErr('gerantNom', 'Nom gérant requis')
      if (!form.gerantCIN?.trim()) setErr('gerantCIN', 'CIN requis')
      if (!form.tel?.trim()) setErr('tel', 'Téléphone requis')
      else if (!phoneRe.test(form.tel)) setErr('tel', 'Téléphone invalide')
      if (!form.email?.trim()) setErr('email', 'Email requis')
      else if (!emailRe.test(form.email)) setErr('email', 'Email invalide')
      if (!form.adressePerso?.trim()) setErr('adressePerso', 'Adresse requise')
    }

    if (step === 2) {
      if (!form.dateDebut) setErr('dateDebut', 'Date début requise')
      if (!form.months || Number(form.months) < 1) setErr('months', 'Durée invalide')
      if (Number(form.monthlyFee) < 0) setErr('monthlyFee', 'Montant invalide')
      if (Number(form.legalFees) < 0) setErr('legalFees', 'Montant invalide')
    }

    return Object.keys(errors.value).length === 0
  }

  return { errors, validateStep }
}