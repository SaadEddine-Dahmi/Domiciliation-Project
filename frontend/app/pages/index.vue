<!-- pages/index.vue
  Public home page — the actual root route of the app.

  Behavior:
    - Everyone (logged-in or not) sees the landing page. No forced redirect.
    - Guests see Login / Register buttons in the nav and a final CTA.
    - Authenticated users see their name + a "Mon espace" link to their
      dashboard, plus a Logout button, instead of Login/Register.

  Design notes:
    - Pure CSS animations only: floating gradient blobs in the hero,
      scroll-reveal via IntersectionObserver, hover micro-interactions.
    - Uses the app's existing theme tokens (--app-*, --gold) so it follows
      the light/gray/dark theme switch automatically.
    - No brand name hardcoded — appName comes from runtime config.
-->
<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'default' })

const auth   = useAuthStore()
const router = useRouter()

// Restore the session from localStorage so the nav reflects the correct
// state (logged-in vs guest) immediately, without waiting for some other
// page's onMounted to have run first.
auth.restoreSession()

// No redirect here — authenticated users are allowed to view the landing
// page too. They get a different nav (name + logout) instead of being
// bounced away.

function getAppName(): string {
  const config = useRuntimeConfig()
  return (config.public.appName as string) ?? 'DomPro'
}

const appName = getAppName()
const { isDark } = useTheme()
const brandLogoSrc = computed(() => isDark.value ? '/brand/logo-dark.svg' : '/brand/logo.svg')

/** Where "Mon espace" / post-logout redirects should point, based on role */
const dashboardPath = computed(() =>
  auth.isAdmin || auth.isDomiciliataire ? '/admin/dashboard' : '/client/dashboard'
)

function handleLogout(): void {
  auth.logout()
  // Stay on the home page after logout — it already works for guests.
  router.push('/')
}

const services = [
  {
    icon: '📄',
    title: 'Contrats de domiciliation',
    description:
      "Générez des contrats professionnels en quelques minutes grâce à un assistant guidé en 4 étapes : informations légales, client, clauses, aperçu PDF.",
  },
  {
    icon: '🏢',
    title: 'Gestion des clients',
    description:
      "Centralisez les dossiers de vos entreprises domiciliées : représentants légaux, coordonnées, historique complet et documents associés.",
  },
  {
    icon: '📁',
    title: 'Documents & conformité',
    description:
      "Importez, classez et suivez l'expiration des documents obligatoires (RC, CIN, statuts...) avec des alertes automatiques avant échéance.",
  },
  {
    icon: '💳',
    title: 'Facturation & paiements',
    description:
      "Suivez les redevances, générez des factures numérotées automatiquement et gardez une vue claire sur les paiements en attente.",
  },
  {
    icon: '✉️',
    title: 'Messagerie intégrée',
    description:
      "Communiquez directement avec vos clients depuis la plateforme, avec accusés de lecture pour chaque message envoyé.",
  },
  {
    icon: '🔔',
    title: 'Alertes automatiques',
    description:
      "Recevez des rappels avant l'expiration de chaque contrat, avec des délais personnalisables (1, 3 ou 6 mois).",
  },
]

const steps = [
  {
    number: '01',
    title: 'Complétez votre profil',
    description: "Renseignez les informations légales de votre société de domiciliation une seule fois.",
  },
  {
    number: '02',
    title: 'Ajoutez vos clients',
    description: "Créez ou importez les entreprises que vous domiciliez, avec leurs représentants légaux.",
  },
  {
    number: '03',
    title: 'Générez vos contrats',
    description: "Utilisez l'assistant pour produire un contrat PDF prêt à signer, en quelques clics.",
  },
]

const stats = [
  { value: '4', label: 'Étapes pour créer un contrat' },
  { value: '100%', label: 'Conforme aux formats légaux' },
  { value: '24/7', label: 'Accès à vos dossiers' },
]

// ── Scroll-reveal animation ────────────────────────────────────────────────
const revealEls = ref<HTMLElement[]>([])
let observer: IntersectionObserver | null = null

function registerReveal(el: any) {
  if (el) revealEls.value.push(el as HTMLElement)
}

onMounted(() => {
  if (!import.meta.client) return
  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('reveal-visible')
          observer?.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.15 }
  )
  revealEls.value.forEach((el) => observer?.observe(el))
})

onBeforeUnmount(() => {
  observer?.disconnect()
})
</script>

<template>
  <div class="landing">

    <!-- ── Nav ─────────────────────────────────────────────────────────── -->
    <header class="landing-nav">
      <div class="landing-nav__inner">
        <img class="landing-nav__logo" :src="brandLogoSrc" :alt="appName">

        <!-- Guest nav: Login / Register -->
        <nav v-if="!auth.isAuthenticated" class="landing-nav__links">
          <NuxtLink to="/login" class="landing-nav__link nav-inactive">Connexion</NuxtLink>
          <NuxtLink to="/register" class="btn btn-gold btn-sm">Créer un compte</NuxtLink>
        </nav>

        <!-- Authenticated nav: user name + dashboard link + logout -->
        <nav v-else class="landing-nav__links">
          <NuxtLink :to="dashboardPath" class="landing-nav__user">
            <span class="landing-nav__user-avatar">{{ (auth.user?.name ?? 'U').slice(0, 2).toUpperCase() }}</span>
            <span class="landing-nav__user-name">{{ auth.user?.name ?? 'Mon compte' }}</span>
          </NuxtLink>
          <button class="btn btn-outline btn-sm" @click="handleLogout">
            Déconnexion
          </button>
        </nav>
      </div>
    </header>

    <!-- ── Hero ────────────────────────────────────────────────────────── -->
    <section class="landing-hero">
      <div class="hero-blob hero-blob--1" aria-hidden="true"></div>
      <div class="hero-blob hero-blob--2" aria-hidden="true"></div>

      <div class="landing-hero__inner">
        <span class="landing-hero__badge">✨ Nouvelle génération de gestion de domiciliation</span>

        <h1 class="landing-hero__title font-serif">
          La gestion de domiciliation,
          <em class="text-gold italic">enfin simplifiée.</em>
        </h1>

        <p class="landing-hero__subtitle">
          Contrats, clients, documents et facturation réunis dans un seul espace,
          pensé pour les sociétés de domiciliation modernes.
        </p>

        <!-- Guests: acquisition CTA. Authenticated users: shortcut to their space. -->
        <div v-if="!auth.isAuthenticated" class="landing-hero__cta">
          <NuxtLink to="/register" class="btn btn-gold btn-lg">Démarrer gratuitement →</NuxtLink>
          <NuxtLink to="/login" class="btn btn-outline btn-lg">J'ai déjà un compte</NuxtLink>
        </div>
        <div v-else class="landing-hero__cta">
          <NuxtLink :to="dashboardPath" class="btn btn-gold btn-lg">
            Accéder à mon espace →
          </NuxtLink>
        </div>

        <div class="landing-hero__stats">
          <div v-for="s in stats" :key="s.label" class="landing-hero__stat">
            <span class="landing-hero__stat-value font-serif">{{ s.value }}</span>
            <span class="landing-hero__stat-label">{{ s.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Services ────────────────────────────────────────────────────── -->
    <section class="landing-section">
      <div class="landing-section__header reveal" :ref="registerReveal">
        <p class="landing-section__eyebrow">Fonctionnalités</p>
        <h2 class="landing-section__title font-serif">
          Tout ce dont vous avez besoin, <em class="text-gold italic">au même endroit</em>
        </h2>
      </div>

      <div class="landing-services-grid">
        <div
          v-for="(s, i) in services"
          :key="s.title"
          :ref="registerReveal"
          class="card landing-service-card reveal"
          :style="`transition-delay: ${i * 70}ms`"
        >
          <div class="landing-service-card__icon">{{ s.icon }}</div>
          <h3 class="landing-service-card__title">{{ s.title }}</h3>
          <p class="landing-service-card__desc">{{ s.description }}</p>
        </div>
      </div>
    </section>

    <!-- ── How it works ────────────────────────────────────────────────── -->
    <section class="landing-section landing-section--muted">
      <div class="landing-section__header reveal" :ref="registerReveal">
        <p class="landing-section__eyebrow">Mise en route</p>
        <h2 class="landing-section__title font-serif">
          Opérationnel en <em class="text-gold italic">trois étapes</em>
        </h2>
      </div>

      <div class="landing-steps">
        <div
          v-for="(s, i) in steps"
          :key="s.number"
          :ref="registerReveal"
          class="landing-step reveal"
          :style="`transition-delay: ${i * 100}ms`"
        >
          <span class="landing-step__number font-serif">{{ s.number }}</span>
          <h3 class="landing-step__title">{{ s.title }}</h3>
          <p class="landing-step__desc">{{ s.description }}</p>
        </div>
      </div>
    </section>

    <!-- ── Trust strip ─────────────────────────────────────────────────── -->
    <section class="landing-trust reveal" :ref="registerReveal">
      <p>🔒 Accès isolé par domiciliataire &nbsp;·&nbsp; Export PDF conforme &nbsp;·&nbsp; Données hébergées de façon sécurisée</p>
    </section>

    <!-- ── Final CTA ───────────────────────────────────────────────────── -->
    <!-- Guests get the acquisition pitch; authenticated users get a
         friendly "welcome back" version pointing to their dashboard. -->
    <section class="landing-final-cta reveal" :ref="registerReveal">
      <template v-if="!auth.isAuthenticated">
        <h2 class="font-serif">Prêt à simplifier votre gestion ?</h2>
        <p class="landing-final-cta__sub">Créez votre compte en moins de deux minutes.</p>
        <NuxtLink to="/register" class="btn btn-gold btn-lg">Créer mon compte domiciliataire →</NuxtLink>
      </template>
      <template v-else>
        <h2 class="font-serif">Content de vous revoir, {{ auth.user?.name }} 👋</h2>
        <p class="landing-final-cta__sub">Reprenez la gestion de vos contrats là où vous l'avez laissée.</p>
        <NuxtLink :to="dashboardPath" class="btn btn-gold btn-lg">Accéder à mon espace →</NuxtLink>
      </template>
    </section>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="landing-footer">
      <p>© {{ new Date().getFullYear() }} {{ appName }}. Tous droits réservés.</p>
    </footer>

  </div>
</template>

<style scoped>
.landing {
  color: var(--app-text);
  background: var(--app-bg);
  min-height: 100vh;
  overflow-x: hidden;
}

.reveal {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}
.reveal-visible {
  opacity: 1;
  transform: translateY(0);
}
@media (prefers-reduced-motion: reduce) {
  .reveal { opacity: 1; transform: none; transition: none; }
  .hero-blob { animation: none !important; }
}

.landing-nav {
  border-bottom: 1px solid var(--app-border-2);
  position: sticky;
  top: 0;
  z-index: 20;
  background: var(--topbar-bg);
  backdrop-filter: blur(10px);
}
.landing-nav__inner {
  max-width: 1120px;
  margin: 0 auto;
  padding: 1.1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.landing-nav__logo {
  display: block;
  height: 2.5rem;
  width: auto;
  max-width: 180px;
}
.landing-nav__links {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.landing-nav__link {
  font-size: 0.875rem;
  padding: 0.4rem 0.6rem;
  border-radius: 8px;
  transition: color 0.15s ease;
}

/* Authenticated user chip in the nav */
.landing-nav__user {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.3rem 0.7rem 0.3rem 0.3rem;
  border-radius: 999px;
  background: var(--app-surface-2);
  border: 1px solid var(--app-border);
  transition: border-color 0.15s ease;
}
.landing-nav__user:hover {
  border-color: rgba(200, 169, 110, 0.4);
}
.landing-nav__user-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  font-weight: 700;
  background: rgba(200, 169, 110, 0.18);
  color: var(--gold);
  flex-shrink: 0;
}
.landing-nav__user-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--app-text);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.landing-hero {
  position: relative;
  overflow: hidden;
}
.landing-hero__inner {
  position: relative;
  z-index: 2;
  max-width: 820px;
  margin: 0 auto;
  padding: 5.5rem 1.5rem 4.5rem;
  text-align: center;
}
.landing-hero__badge {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  background: rgba(200, 169, 110, 0.12);
  color: var(--gold);
  border: 1px solid rgba(200, 169, 110, 0.25);
  margin-bottom: 1.5rem;
  animation: fadeUp 0.6s ease both;
}
.landing-hero__title {
  font-size: clamp(2.1rem, 5.5vw, 3.5rem);
  line-height: 1.15;
  margin-bottom: 1.25rem;
  animation: fadeUp 0.7s ease 0.1s both;
}
.landing-hero__subtitle {
  font-size: 1.08rem;
  color: var(--app-text-muted);
  line-height: 1.7;
  margin-bottom: 2.25rem;
  animation: fadeUp 0.7s ease 0.2s both;
}
.landing-hero__cta {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: wrap;
  animation: fadeUp 0.7s ease 0.3s both;
}
.landing-hero__stats {
  display: flex;
  justify-content: center;
  gap: clamp(1.5rem, 5vw, 3.5rem);
  margin-top: 3.5rem;
  flex-wrap: wrap;
  animation: fadeUp 0.7s ease 0.4s both;
}
.landing-hero__stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.landing-hero__stat-value {
  font-size: 1.8rem;
  color: var(--gold);
  font-weight: 600;
}
.landing-hero__stat-label {
  font-size: 0.75rem;
  color: var(--app-text-faint);
  margin-top: 0.25rem;
}

.hero-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(70px);
  opacity: 0.35;
  z-index: 1;
  pointer-events: none;
}
.hero-blob--1 {
  width: 380px;
  height: 380px;
  top: -120px;
  left: -100px;
  background: radial-gradient(circle, var(--gold) 0%, transparent 70%);
  animation: float1 14s ease-in-out infinite;
}
.hero-blob--2 {
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
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.landing-section {
  max-width: 1120px;
  margin: 0 auto;
  padding: 4.5rem 1.5rem;
  position: relative;
  z-index: 2;
}
.landing-section--muted {
  max-width: 100%;
  background: var(--app-surface-2);
}
.landing-section--muted > * {
  max-width: 1120px;
  margin-left: auto;
  margin-right: auto;
}
.landing-section__header {
  text-align: center;
  margin-bottom: 3rem;
}
.landing-section__eyebrow {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-weight: 700;
  color: var(--gold);
  margin-bottom: 0.5rem;
}
.landing-section__title {
  font-size: clamp(1.6rem, 3.5vw, 2.35rem);
}

.landing-services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.25rem;
}
.landing-service-card {
  padding: 1.85rem;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.landing-service-card:hover {
  transform: translateY(-4px);
  border-color: rgba(200, 169, 110, 0.35);
}
.landing-service-card__icon {
  font-size: 1.85rem;
  margin-bottom: 0.85rem;
  display: inline-block;
  transition: transform 0.25s ease;
}
.landing-service-card:hover .landing-service-card__icon {
  transform: scale(1.15) rotate(-4deg);
}
.landing-service-card__title {
  font-weight: 600;
  margin-bottom: 0.5rem;
}
.landing-service-card__desc {
  font-size: 0.875rem;
  line-height: 1.6;
  color: var(--app-text-muted);
}

.landing-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2rem;
}
.landing-step {
  position: relative;
  padding-left: 0.25rem;
}
.landing-step__number {
  font-size: 1.9rem;
  color: var(--gold);
  display: block;
  margin-bottom: 0.5rem;
  opacity: 0.9;
}
.landing-step__title {
  font-weight: 600;
  margin-bottom: 0.4rem;
}
.landing-step__desc {
  font-size: 0.875rem;
  color: var(--app-text-muted);
  line-height: 1.6;
}

.landing-trust {
  text-align: center;
  padding: 1.5rem 1.5rem;
  font-size: 0.8rem;
  color: var(--app-text-faint);
  border-top: 1px solid var(--app-border-2);
  border-bottom: 1px solid var(--app-border-2);
}

.landing-final-cta {
  text-align: center;
  padding: 5.5rem 1.5rem;
}
.landing-final-cta h2 {
  font-size: clamp(1.6rem, 3.5vw, 2.1rem);
  margin-bottom: 0.75rem;
}
.landing-final-cta__sub {
  color: var(--app-text-muted);
  margin-bottom: 1.75rem;
}

.landing-footer {
  text-align: center;
  padding: 2rem 1.5rem;
  font-size: 0.75rem;
  color: var(--app-text-faint);
}
</style>
