import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  // Devtools only in development — never expose in production
  devtools: { enabled: process.env.NODE_ENV !== 'production' },

  modules: ['@pinia/nuxt', '@vueuse/nuxt'],

  css: ['./app/assets/css/main.css'],

  vite: {
    plugins: [tailwindcss()],
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? 'http://localhost:8000',
      authStorageKey: process.env.NUXT_PUBLIC_AUTH_STORAGE_KEY ?? 'astfisc_auth',
      themeStorageKey: process.env.NUXT_PUBLIC_THEME_STORAGE_KEY ?? 'astfisc_theme',
      sessionMaxAgeDays: process.env.NUXT_PUBLIC_SESSION_MAX_AGE_DAYS ?? '7',
      appName: process.env.NUXT_PUBLIC_APP_NAME ?? 'AST-FISC',
    },
  },
})