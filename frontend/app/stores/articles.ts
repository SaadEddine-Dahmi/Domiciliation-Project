// app/stores/articles.ts
// Stores tenant article library state for the contract wizard.

import { defineStore } from 'pinia'
import { apiBase, authHeaders } from '~/services/http'

export interface Article {
  id: string
  title: string
  body: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export const useArticlesStore = defineStore('articles', () => {
  const items = ref<Article[]>([])
  const loading = ref(false)
  const error = ref('')

  function normalize(article: Article): Article {
    return { ...article, id: String(article.id) }
  }

  async function fetchAll(): Promise<void> {
    loading.value = true
    error.value = ''

    try {
      const res = await $fetch<ApiSuccess<Article[]>>(`${apiBase()}/api/articles`, {
        headers: authHeaders(),
        query: { per_page: 100 },
      })

      items.value = (res.data ?? []).map(normalize)
    } catch (e: any) {
      error.value = e?.data?.message ?? 'Erreur lors du chargement des articles'
    } finally {
      loading.value = false
    }
  }

  async function create(title: string, body: string): Promise<Article> {
    const res = await $fetch<ApiSuccess<Article>>(`${apiBase()}/api/articles`, {
      method: 'POST',
      headers: authHeaders(),
      body: { title, body, is_active: true },
    })

    const article = normalize(res.data)
    items.value.unshift(article)

    return article
  }

  async function update(id: string, title: string, body: string, is_active: boolean): Promise<Article> {
    const res = await $fetch<ApiSuccess<Article>>(`${apiBase()}/api/articles/${id}`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { title, body, is_active },
    })

    const updated = normalize(res.data)
    const index = items.value.findIndex(article => article.id === id)
    if (index !== -1) items.value[index] = updated

    return updated
  }

  async function remove(id: string): Promise<void> {
    await $fetch(`${apiBase()}/api/articles/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })

    items.value = items.value.filter(article => article.id !== id)
  }

  return { items, loading, error, fetchAll, create, update, remove }
})
