export interface ApiSuccess<T> {
  success: boolean
  data: T
  message?: string
}

export interface ArticleEntity {
  id: number
  title: string
  body: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export const articleService = {
  list: () => $fetch<ApiSuccess<ArticleEntity[]>>('/api/articles'),

  create: (payload: Pick<ArticleEntity, 'title' | 'body' | 'is_active'>) =>
    $fetch<ApiSuccess<ArticleEntity>>('/api/articles', {
      method: 'POST',
      body: payload,
    }),

  update: (id: number, payload: Pick<ArticleEntity, 'title' | 'body' | 'is_active'>) =>
    $fetch<ApiSuccess<ArticleEntity>>(`/api/articles/${id}`, {
      method: 'PUT',
      body: payload,
    }),

  remove: (id: number) =>
    $fetch<ApiSuccess<{ message: string }>>(`/api/articles/${id}`, {
      method: 'DELETE',
    }),
}