<!-- pages/welcome.vue
  Public marketing / landing page — presents the platform's core services.
  No auth middleware: accessible to logged-out visitors.
  Uses the app's existing CSS custom properties (--app-*, --gold) so it
  automatically follows the active theme (light / gray / dark) set by
  plugins/theme.client.ts — no separate landing-only theme needed.

  Sections:
    1. Nav        — logo + login/register CTA
    2. Hero        — value proposition
    3. Services    — 4 core capabilities (contracts, clients, documents, billing)
    4. How it works — 3-step onboarding flow
    5. Trust strip — compliance / security reassurance
    6. Final CTA + footer

  No brand name is hardcoded as a business name — appName below is a
  placeholder pulled from runtime config, ready to be renamed later.
-->
<script setup lang="ts">
definePageMeta({ layout: 'default' })

function getAppName(): string {
  const config = useRuntimeConfig()
  return (config.public.appName as string) ?? 'Votre plateforme de domiciliation'
}

const appName = getAppName()

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
</script>

<template>
  <div class="landing">

    <!-- ── Nav ─────────────────────────────────────────────────────────── -->
    <header class="landing-nav">
      <div class="landing-nav__inner">
        <span class="landing-nav__logo font-serif">{{ appName }}</span>
        <nav class="landing-nav__links">
          <NuxtLink to="/login" class="landing-nav__link nav-inactive">Connexion</NuxtLink>
          <NuxtLink to="/register" class="btn btn-gold btn-sm">Créer un compte</NuxtLink>
        </nav>
      </div>
    </header>

    <!-- ── Hero ────────────────────────────────────────────────────────── -->
    <section class="landing-hero">
      <div class="landing-hero__inner">
        <h1 class="landing-hero__title font-serif">
          La gestion de domiciliation,
          <em class="text-gold italic">simplifiée.</em>
        </h1>
        <p class="landing-hero__subtitle">
          Contrats, clients, documents et facturation réunis dans un seul espace,
          pensé pour les sociétés de domiciliation.
        </p>
        <div class="landing-hero__cta">
          <NuxtLink to="/register" class="btn btn-gold btn-lg">Démarrer gratuitement</NuxtLink>
          <NuxtLink to="/login" class="btn btn-outline btn-lg">J'ai déjà un compte</NuxtLink>
        </div>
      </div>
    </section>

    <!-- ── Services ────────────────────────────────────────────────────── -->
    <section class="landing-section">
      <div class="landing-section__header">
        <p class="landing-section__eyebrow">Fonctionnalités</p>
        <h2 class="landing-section__title font-serif">
          Tout ce dont vous avez besoin, <em class="text-gold italic">au même endroit</em>
        </h2>
      </div>

      <div class="landing-services-grid">
        <div v-for="s in services" :key="s.title" class="card landing-service-card">
          <div class="landing-service-card__icon">{{ s.icon }}</div>
          <h3 class="landing-service-card__title">{{ s.title }}</h3>
          <p class="landing-service-card__desc">{{ s.description }}</p>
        </div>
      </div>
    </section>

    <!-- ── How it works ────────────────────────────────────────────────── -->
    <section class="landing-section landing-section--muted">
      <div class="landing-section__header">
        <p class="landing-section__eyebrow">Mise en route</p>
        <h2 class="landing-section__title font-serif">
          Opérationnel en <em class="text-gold italic">trois étapes</em>
        </h2>
      </div>

      <div class="landing-steps">
        <div v-for="s in steps" :key="s.number" class="landing-step">
          <span class="landing-step__number font-serif">{{ s.number }}</span>
          <h3 class="landing-step__title">{{ s.title }}</h3>
          <p class="landing-step__desc">{{ s.description }}</p>
        </div>
      </div>
    </section>

    <!-- ── Trust strip ─────────────────────────────────────────────────── -->
    <section class="landing-trust">
      <p>🔒 Accès isolé par domiciliataire · Export PDF conforme · Données hébergées de façon sécurisée</p>
    </section>

    <!-- ── Final CTA ───────────────────────────────────────────────────── -->
    <section class="landing-final-cta">
      <h2 class="font-serif">Prêt à simplifier votre gestion ?</h2>
      <NuxtLink to="/register" class="btn btn-gold btn-lg">Créer mon compte domiciliataire</NuxtLink>
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
}

/* Nav */
.landing-nav {
  border-bottom: 1px solid var(--app-border-2);
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
  font-size: 1.15rem;
  font-weight: 600;
}
.landing-nav__links {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}
.landing-nav__link {
  font-size: 0.875rem;
  padding: 0.4rem 0.6rem;
  border-radius: 8px;
}

/* Hero */
.landing-hero__inner {
  max-width: 780px;
  margin: 0 auto;
  padding: 5rem 1.5rem 4rem;
  text-align: center;
}
.landing-hero__title {
  font-size: clamp(2rem, 5vw, 3.25rem);
  line-height: 1.15;
  margin-bottom: 1.25rem;
}
.landing-hero__subtitle {
  font-size: 1.05rem;
  color: var(--app-text-muted);
  line-height: 1.7;
  margin-bottom: 2rem;
}
.landing-hero__cta {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Section shell */
.landing-section {
  max-width: 1120px;
  margin: 0 auto;
  padding: 4rem 1.5rem;
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
  font-size: clamp(1.5rem, 3.5vw, 2.25rem);
}

/* Services grid */
.landing-services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}
.landing-service-card {
  padding: 1.75rem;
}
.landing-service-card__icon {
  font-size: 1.75rem;
  margin-bottom: 0.75rem;
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

/* Steps */
.landing-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2rem;
}
.landing-step__number {
  font-size: 1.75rem;
  color: var(--gold);
  display: block;
  margin-bottom: 0.5rem;
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

/* Trust strip */
.landing-trust {
  text-align: center;
  padding: 1.25rem 1.5rem;
  font-size: 0.8rem;
  color: var(--app-text-faint);
  border-top: 1px solid var(--app-border-2);
  border-bottom: 1px solid var(--app-border-2);
}

/* Final CTA */
.landing-final-cta {
  text-align: center;
  padding: 5rem 1.5rem;
}
.landing-final-cta h2 {
  font-size: clamp(1.5rem, 3.5vw, 2rem);
  margin-bottom: 1.5rem;
}

/* Footer */
.landing-footer {
  text-align: center;
  padding: 2rem 1.5rem;
  font-size: 0.75rem;
  color: var(--app-text-faint);
}
</style>