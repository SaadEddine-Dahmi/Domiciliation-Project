// app/services/template.service.ts
// Calls reusable contract template endpoints.

import { apiBase, authHeaders } from '~/services/http'

export interface TemplateArticle {
  id: string
  title: string
  body: string
  pivot?: { ordre: number }
}

export interface TemplateEntity {
  id: number
  name: string
  description: string | null
  articles: TemplateArticle[]
  created_at?: string
  updated_at?: string
}

interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

type OrderedArticle = { id: string; ordre: number }

export const templateService = {
  list(): Promise<ApiSuccess<TemplateEntity[]>> {
    return $fetch(`${apiBase()}/api/templates`, {
      headers: authHeaders(),
      query: { per_page: 100 },
    })
  },

  create(name: string, description: string, articles: OrderedArticle[]): Promise<ApiSuccess<TemplateEntity>> {
    return $fetch(`${apiBase()}/api/templates`, {
      method: 'POST',
      headers: authHeaders(),
      body: { name, description, articles },
    })
  },

  update(id: number, name: string, description: string, articles: OrderedArticle[]): Promise<ApiSuccess<TemplateEntity>> {
    return $fetch(`${apiBase()}/api/templates/${id}`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { name, description, articles },
    })
  },

  remove(id: number): Promise<ApiSuccess<{ message: string }>> {
    return $fetch(`${apiBase()}/api/templates/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
  },
}
