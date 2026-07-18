// services/auth.service.ts
// Alternative auth store used by some pages.
// Storage key: 'app_auth' — consistent with stores/auth.ts and all Vue pages.
// No brand-specific name is used anywhere in this file.

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

type Role = 'admin' | 'domiciliataire' | 'client'

interface AuthUser {
  id: number
  name: string
  email: string
  role: Role
  company: string
  avatar: string
  color: string
}

interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

// Single source of truth for the localStorage key across login/logout/restore.
// Change this constant if you rename the key — no other file needs to change.
const AUTH_STORAGE_KEY = 'app_auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref('')
  const loading = ref(false)
  const error = ref('')

  const isAuthenticated = computed(() => !!user.value && !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isDomiciliataire = computed(() => user.value?.role === 'domiciliataire')
  const isClient = computed(() => user.value?.role === 'client')

  function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
  }

  async function login(payload: LoginPayload): Promise<boolean> {
    loading.value = true
    error.value = ''
    try {
      const res = await $fetch<{ success: boolean; token: string; user: any }>(
        `${getApiBase()}/api/auth/login`,
        {
          method: 'POST',
          body: {
            email: payload.email.trim().toLowerCase(),
            password: payload.password.trim(),
          },
        }
      )

      const u = res.user
      user.value = {
        id: u.id,
        name: u.name ?? u.nom ?? '',
        email: u.email,
        role: u.role ?? 'client',
        company: u.company ?? u.entreprise ?? '',
        avatar: (u.name ?? u.nom ?? 'U').slice(0, 2).toUpperCase(),
        color: u.role === 'admin' ? '#c8a96e' : '#60a5fa',
      }
      token.value = res.token

      if (import.meta.client) {
        localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify({
          user: user.value,
          token: token.value,
          savedAt: Date.now(),
        }))
      }

      return true
    } catch (e: any) {
      error.value = e?.data?.message ?? e?.message ?? 'Identifiants invalides'
      return false
    } finally {
      loading.value = false
    }
  }

  function logout(): void {
    user.value = null
    token.value = ''
    error.value = ''
    if (import.meta.client) {
      localStorage.removeItem(AUTH_STORAGE_KEY)
    }
  }

  function restoreSession(): void {
    if (!import.meta.client) return
    if (user.value && token.value) return
    try {
      const raw = localStorage.getItem(AUTH_STORAGE_KEY)
      if (!raw) return
      const parsed = JSON.parse(raw)
      user.value = parsed.user ?? null
      token.value = parsed.token ?? ''
    } catch { }
  }

  return {
    user, token, loading, error,
    isAuthenticated, isAdmin, isDomiciliataire, isClient,
    login, logout, restoreSession,
  }
})