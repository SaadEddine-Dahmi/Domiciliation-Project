// app/services/article.service.ts
// Calls tenant article library endpoints.

import { apiBase, authHeaders } from '~/services/http'

export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface ArticleEntity {
  id: string
  title: string
  body: string
  is_active: boolean
  created_at: string
  updated_at: string
}

type ArticlePayload = Pick<ArticleEntity, 'title' | 'body' | 'is_active'>

export const articleService = {
  list(): Promise<ApiSuccess<ArticleEntity[]>> {
    return $fetch<ApiSuccess<ArticleEntity[]>>(`${apiBase()}/api/articles`, {
      headers: authHeaders(),
    })
  },

  create(payload: ArticlePayload): Promise<ApiSuccess<ArticleEntity>> {
    return $fetch<ApiSuccess<ArticleEntity>>(`${apiBase()}/api/articles`, {
      method: 'POST',
      headers: authHeaders(),
      body: payload,
    })
  },

  update(id: string, payload: ArticlePayload): Promise<ApiSuccess<ArticleEntity>> {
    return $fetch<ApiSuccess<ArticleEntity>>(`${apiBase()}/api/articles/${id}`, {
      method: 'PUT',
      headers: authHeaders(),
      body: payload,
    })
  },

  remove(id: string): Promise<ApiSuccess<{ message: string }>> {
    return $fetch<ApiSuccess<{ message: string }>>(`${apiBase()}/api/articles/${id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
  },
}
