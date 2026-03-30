import type { ContratArticle } from '~/types/contrat'
import type { ContratPayload } from '~/types/contrat-api'
import { useContractStore } from '~/stores/contrat'

export function useContratPayload() {
  const contract = useContractStore()

  function buildPayload(selectedArticles: ContratArticle[]): ContratPayload {
    return {
      form: {
        astNom: contract.form.astNom,
        astRC: contract.form.astRC,
        astIF: contract.form.astIF,
        astRepresentant: contract.form.astRepresentant,
        astCIN: contract.form.astCIN,
        astAdresse: contract.form.astAdresse,

        societe: contract.form.societe,
        gerantNom: contract.form.gerantNom,
        gerantCIN: contract.form.gerantCIN,
        tel: contract.form.tel,
        email: contract.form.email,
        adressePerso: contract.form.adressePerso,

        dateDebut: contract.form.dateDebut,
        dateFin: contract.form.dateFin,
        months: contract.form.months,
        redevanceMensuelle: contract.form.redevanceMensuelle,
        redevanceAnnuelle: contract.form.redevanceAnnuelle,
      },
      selectedServices: [...contract.selectedServices],
      articles: selectedArticles,
      totals: {
        monthly: contract.monthlyTotal,
        global: contract.grandTotal,
      },
    }
  }

  return { buildPayload }
}