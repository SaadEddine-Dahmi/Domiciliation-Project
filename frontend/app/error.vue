<!-- error.vue
  Custom error page — replaces Nuxt's default error screen for 404s and
  other thrown errors (500, etc). Nuxt automatically renders this file
  when a route isn't matched or when clearError()/showError() is used.
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

const props = defineProps<{
  error: {
    statusCode: number
    statusMessage?: string
    message?: string
  }
}>()

const auth = useAuthStore()

function getAppName(): string {
  const config = useRuntimeConfig()
  return (config.public.appName as string) ?? 'Domiciliation Manager'
}
const appName = getAppName()

const isNotFound = computed(() => props.error?.statusCode === 404)

const title = computed(() =>
  isNotFound.value ? 'Page introuvable' : 'Une erreur est survenue'
)

const description = computed(() =>
  isNotFound.value
    ? "La page que vous cherchez n'existe pas ou a été déplacée."
    : "Quelque chose s'est mal passé de notre côté. Réessayez dans un instant."
)

function goHome(): void {
  auth.restoreSession()
  clearError({ redirect: auth.isAuthenticated
    ? (auth.isAdmin || auth.isDomiciliataire ? '/admin/dashboard' : '/client/dashboard')
    : '/'
  })
}
</script>

<template>
  <div class="error-page">
    <div class="error-blob error-blob--1" aria-hidden="true"></div>
    <div class="error-blob error-blob--2" aria-hidden="true"></div>

    <div class="error-page__inner">
      <span class="error-page__logo font-serif">{{ appName }}</span>

      <p class="error-page__code font-serif">{{ error?.statusCode ?? 404 }}</p>

      <h1 class="error-page__title font-serif">{{ title }}</h1>
      <p class="error-page__desc">{{ description }}</p>

      <div class="error-page__actions">
        <button class="btn btn-gold btn-lg" @click="goHome">
          Retour à l'accueil
        </button>
        <NuxtLink to="/login" class="btn btn-outline btn-lg">
          Se connecter
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: var(--app-bg);
  color: var(--app-text);
  font-family: var(--font-sans);
}

.error-page__inner {
  position: relative;
  z-index: 2;
  text-align: center;
  padding: 2rem;
  max-width: 480px;
}

.error-page__logo {
  display: block;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--gold);
  margin-bottom: 2.5rem;
}

.error-page__code {
  font-size: clamp(4rem, 12vw, 7rem);
  font-weight: 700;
  line-height: 1;
  color: var(--gold);
  margin-bottom: 0.5rem;
  animation: fadeUp 0.6s ease both;
}

.error-page__title {
  font-size: 1.5rem;
  margin-bottom: 0.75rem;
  animation: fadeUp 0.6s ease 0.1s both;
}

.error-page__desc {
  color: var(--app-text-muted);
  font-size: 0.95rem;
  line-height: 1.6;
  margin-bottom: 2rem;
  animation: fadeUp 0.6s ease 0.2s both;
}

.error-page__actions {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: wrap;
  animation: fadeUp 0.6s ease 0.3s both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.error-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
  z-index: 1;
  pointer-events: none;
}
.error-blob--1 {
  width: 380px;
  height: 380px;
  top: -120px;
  left: -100px;
  background: radial-gradient(circle, var(--gold) 0%, transparent 70%);
  animation: float1 14s ease-in-out infinite;
}
.error-blob--2 {
  width: 320px;
  height: 320px;
  bottom: -140px;
  right: -80px;
  background: radial-gradient(circle, var(--gold-2) 0%, transparent 70%);
  animation: float2 16s ease-in-out infinite;
}
@keyframes float1 {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50%      { transform: translate(40px, 30px) scale(1.1); }
}
@keyframes float2 {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50%      { transform: translate(-30px, -20px) scale(1.08); }
}
@media (prefers-reduced-motion: reduce) {
  .error-blob { animation: none !important; }
}
</style>