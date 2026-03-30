import type { ContratForm, ContratArticle } from '~/types/contrat'

interface DraftPayload {
  form: ContratForm
  selectedIds: string[]
  customArticles: ContratArticle[]
  updatedAt: string
}

const DRAFT_KEY = 'astfisc_contrat_draft_v1'

export function useContratDraft(
  form: ContratForm,
  selectedIds: Ref<string[]>,
  customArticles: Ref<ContratArticle[]>
) {
  const lastSavedAt = ref('')

  function saveDraft() {
    if (typeof window === 'undefined') return
    const payload: DraftPayload = {
      form: { ...form },
      selectedIds: [...selectedIds.value],
      customArticles: [...customArticles.value],
      updatedAt: new Date().toISOString(),
    }
    localStorage.setItem(DRAFT_KEY, JSON.stringify(payload))
    lastSavedAt.value = payload.updatedAt
  }

  function loadDraft() {
    if (typeof window === 'undefined') return false
    const raw = localStorage.getItem(DRAFT_KEY)
    if (!raw) return false
    try {
      const parsed = JSON.parse(raw) as DraftPayload
      Object.assign(form, parsed.form || {})
      selectedIds.value = parsed.selectedIds || selectedIds.value
      customArticles.value = parsed.customArticles || []
      lastSavedAt.value = parsed.updatedAt || ''
      return true
    } catch {
      return false
    }
  }

  function clearDraft() {
    if (typeof window === 'undefined') return
    localStorage.removeItem(DRAFT_KEY)
    lastSavedAt.value = ''
  }

  return { saveDraft, loadDraft, clearDraft, lastSavedAt }
}