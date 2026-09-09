import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  // Devtools only in development — never expose in production
  devtools: { enabled: process.env.NODE_ENV !== 'production' },

  modules: ['@pinia/nuxt', '@vueuse/nuxt'],

  css: ['~/assets/css/main.css'],

  vite: {
    plugins: [tailwindcss()],
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? 'http://localhost:8000',

      // Generic localStorage keys — no brand name hardcoded.
      // Matches the fallback already used in stores/auth.ts and every
      // service file's authHeaders() helper ('app_auth').
      authStorageKey: process.env.NUXT_PUBLIC_AUTH_STORAGE_KEY ?? 'app_auth',
      themeStorageKey: process.env.NUXT_PUBLIC_THEME_STORAGE_KEY ?? 'app_theme',

      sessionMaxAgeDays: process.env.NUXT_PUBLIC_SESSION_MAX_AGE_DAYS ?? '7',

      // Product name — set NUXT_PUBLIC_APP_NAME in .env once the name
      // is chosen. Placeholder below is intentionally generic.
      appName: process.env.NUXT_PUBLIC_APP_NAME ?? 'DomPro',

      // Short 2-3 letter mark shown in the sidebar logo badge. Set
      // NUXT_PUBLIC_APP_SHORT_CODE alongside appName once the final
      // name/branding is chosen — falls back to the first two letters
      // of appName if not set.
      appShortCode: process.env.NUXT_PUBLIC_APP_SHORT_CODE ?? 'DP',
    },
  },

  app: {
    head: {
      title: 'DomPro',
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'shortcut icon', href: '/favicon.svg' },
        { rel: 'apple-touch-icon', href: '/brand/logo-icon.svg' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          // Plus Jakarta Sans — primary UI/body font (see main.css for rationale).
          // Fraunces — serif, used only via .font-serif for headings.
          href: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Fraunces:ital,wght@0,400;0,600;1,400;1,600&display=swap',
        },
      ],
    },
  },
})
