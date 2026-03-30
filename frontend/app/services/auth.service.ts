import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

type Role = 'admin' | 'client'

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

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref('')
  const loading = ref(false)
  const error = ref('')

  const isAuthenticated = computed(() => !!user.value && !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isClient = computed(() => user.value?.role === 'client')

  function getApiBase(): string {
    const config = useRuntimeConfig()
    return (config.public.apiBase as string) ?? ''
  }

  async function login(payload: LoginPayload) {
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
        localStorage.setItem(
          'astfisc_auth',
          JSON.stringify({ user: user.value, token: token.value })
        )
      }

      return true
    } catch (e: any) {
      error.value =
        e?.data?.message ?? e?.message ?? 'Identifiants invalides'
      return false
    } finally {
      loading.value = false
    }
  }

  function logout() {
    user.value = null
    token.value = ''
    error.value = ''
    if (import.meta.client) localStorage.removeItem('astfisc_auth')
  }

  function restoreSession() {
    if (!import.meta.client) return
    if (user.value && token.value) return
    const raw = localStorage.getItem('astfisc_auth')
    if (!raw) return
    try {
      const parsed = JSON.parse(raw)
      user.value = parsed.user ?? null
      token.value = parsed.token ?? ''
    } catch { }
  }

  return {
    user,
    token,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    isClient,
    login,
    logout,
    restoreSession,
  }
})