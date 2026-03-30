// stores/articles.ts
import { defineStore } from 'pinia'

export interface Article {
  id: string
  title: string
  body: string
  is_active: boolean
}

export const useArticlesStore = defineStore('articles', () => {
  const items = ref<Article[]>([])
  const loading = ref(false)
  const error = ref('')

  function getApiBase() {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
  }

  function authHeaders(): Record<string, string> {
    if (!import.meta.client) return {}
    try {
      const raw = localStorage.getItem('astfisc_auth')
      if (!raw) return {}
      const parsed = JSON.parse(raw)
      const token = parsed?.token ?? ''
      return token ? { Authorization: `Bearer ${token}` } : {}
    } catch { return {} }
  }

  async function fetchAll() {
    loading.value = true
    error.value = ''
    try {
      const res = await $fetch<{ success: boolean; data: Article[] }>(
        `${getApiBase()}/api/articles`,
        { headers: authHeaders() }
      )
      items.value = res.data ?? []
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Erreur chargement articles'
    } finally {
      loading.value = false
    }
  }

  async function create(title: string, body: string): Promise<Article> {
    const res = await $fetch<{ success: boolean; data: Article }>(
      `${getApiBase()}/api/articles`,
      {
        method: 'POST',
        headers: authHeaders(),
        body: { title, body, is_active: true },
      }
    )
    items.value.unshift(res.data)
    return res.data
  }

  async function update(id: string, title: string, body: string, is_active: boolean) {
    const res = await $fetch<{ success: boolean; data: Article }>(
      `${getApiBase()}/api/articles/${id}`,
      {
        method: 'PUT',
        headers: authHeaders(),
        body: { title, body, is_active },
      }
    )
    const idx = items.value.findIndex((a) => a.id === id)
    if (idx !== -1) items.value[idx] = res.data
    return res.data
  }

  async function remove(id: string) {
    await $fetch(`${getApiBase()}/api/articles/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    items.value = items.value.filter((a) => a.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
