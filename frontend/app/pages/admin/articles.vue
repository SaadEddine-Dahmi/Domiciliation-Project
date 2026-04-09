<!-- app/pages/admin/articles.vue -->
<!-- Full CRUD for articles (contract clauses library) -->
<!-- API: GET/POST/PUT/DELETE /api/articles -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useArticlesStore } from '~/stores/articles'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const articlesStore = useArticlesStore()
const { items, loading } = storeToRefs(articlesStore)
const { success, error: toastError } = useToast()

// ── State ─────────────────────────────────────────────────
const search      = ref('')
const showModal   = ref(false)
const modalMode   = ref<'create' | 'edit'>('create')
const editId      = ref<string | null>(null)
const saving      = ref(false)
const serverError = ref('')

// ── Form ──────────────────────────────────────────────────
const form = reactive({
  title:     '',
  body:      '',
  is_active: true,
})

// ── Search ────────────────────────────────────────────────
const filtered = computed(() => {
  if (!search.value.trim()) return items.value
  const q = search.value.toLowerCase()
  return items.value.filter(a =>
    a.title?.toLowerCase().includes(q) ||
    a.body?.toLowerCase().includes(q)
  )
})

// ── Stats ─────────────────────────────────────────────────
const activeCount   = computed(() => items.value.filter(a => a.is_active).length)
const inactiveCount = computed(() => items.value.filter(a => !a.is_active).length)

// ── Open modal: create ────────────────────────────────────
function openCreate(): void {
  modalMode.value   = 'create'
  editId.value      = null
  serverError.value = ''
  Object.assign(form, { title: '', body: '', is_active: true })
  showModal.value   = true
}

// ── Open modal: edit ──────────────────────────────────────
function openEdit(article: any): void {
  modalMode.value   = 'edit'
  editId.value      = article.id
  serverError.value = ''
  Object.assign(form, {
    title:     article.title     ?? '',
    body:      article.body      ?? '',
    is_active: article.is_active ?? true,
  })
  showModal.value = true
}

// ── Submit create / edit ──────────────────────────────────
async function submitArticle(): Promise<void> {
  if (!form.title.trim()) {
    serverError.value = 'Le titre est obligatoire.'
    return
  }
  serverError.value = ''
  saving.value      = true
  try {
    if (modalMode.value === 'create') {
      await articlesStore.create(form.title.trim(), form.body.trim())
      success('Article créé avec succès')
    } else if (editId.value) {
      await articlesStore.update(
        editId.value,
        form.title.trim(),
        form.body.trim(),
        form.is_active
      )
      success('Article mis à jour')
    }
    showModal.value = false
  } catch (e: any) {
    serverError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    toastError?.(serverError.value)
  } finally {
    saving.value = false
  }
}

// ── Toggle active status directly from list ───────────────
async function toggleActive(article: any): Promise<void> {
  try {
    await articlesStore.update(
      article.id,
      article.title,
      article.body,
      !article.is_active
    )
    success(article.is_active ? 'Article désactivé' : 'Article activé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur mise à jour statut')
  }
}

// ── Delete ────────────────────────────────────────────────
async function deleteArticle(id: string): Promise<void> {
  if (!confirm('Supprimer cet article définitivement ? Il sera retiré de tous les contrats.')) return
  try {
    await articlesStore.remove(id)
    success('Article supprimé')
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

// ── Template variables ────────────────────────────────────
const templateVars = [
  '{{date_debut}}',
  '{{date_fin}}',
  '{{raison_sociale}}',
  '{{gerant_nom}}',
  '{{gerant_cin}}',
  '{{gerant_naissance}}',
  '{{gerant_adresse}}',
  '{{redevance_mensuelle}}',
  '{{redevance_annuelle}}',
  '{{instruction_no}}',
  '{{ville_signature}}',
]

function insertVar(v: string): void {
  form.body += (form.body.endsWith(' ') || form.body === '' ? '' : ' ') + v
}

onMounted(() => articlesStore.fetchAll())
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Articles <em class="text-gold italic">&amp; Clauses</em>
        </h1>
        <p class="text-app-text/50 text-sm mt-1">
          Bibliothèque des clauses réutilisables dans les contrats
        </p>
      </div>
      <button class="btn btn-gold btn-md" @click="openCreate">
        + Nouvel article
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-gold">{{ items.length }}</p>
        <p class="text-xs text-app-text/40 mt-1">Total articles</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-green-400">{{ activeCount }}</p>
        <p class="text-xs text-app-text/40 mt-1">Articles actifs</p>
      </div>
      <div class="card p-4 text-center">
        <p class="text-2xl font-serif text-app-text/40">{{ inactiveCount }}</p>
        <p class="text-xs text-app-text/40 mt-1">Désactivés</p>
      </div>
    </div>

    <!-- Search -->
    <div class="card p-3">
      <input
        v-model="search"
        class="f-input"
        placeholder="Rechercher par titre ou contenu..."
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">
      Chargement...
    </div>

    <!-- Articles list -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div
        v-for="article in filtered"
        :key="article.id"
        class="card p-4 space-y-3 transition"
        :class="!article.is_active ? 'opacity-50' : ''"
      >
        <!-- Article header -->
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold">{{ article.title }}</p>
              <span
                class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="article.is_active
                  ? 'text-green-400 bg-green-400/10'
                  : 'text-app-text/40 bg-white/5'"
              >
                {{ article.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <p class="text-sm text-app-text/50 mt-1 line-clamp-3 leading-relaxed">
              {{ article.body || '— Aucun contenu —' }}
            </p>
          </div>

          <!-- Actions -->
          <div class="flex gap-2 flex-shrink-0">
            <button
              class="btn btn-outline btn-sm"
              :title="article.is_active ? 'Désactiver' : 'Activer'"
              @click="toggleActive(article)"
            >
              {{ article.is_active ? '⏸' : '▶' }}
            </button>
            <button class="btn btn-outline btn-sm" @click="openEdit(article)">
              Modifier
            </button>
            <button class="btn btn-danger btn-sm" @click="deleteArticle(article.id)">
              ✕
            </button>
          </div>
        </div>

        <!-- Meta -->
        <div class="flex items-center gap-4 text-xs text-app-text/30 border-t border-white/5 pt-2">
          <!-- <span>ID : {{ article.id }}</span> -->
          <span v-if="article.created_at">
            Créé le {{ new Date(article.created_at).toLocaleDateString('fr-MA') }}
          </span>
          <span v-if="article.updated_at">
            · Modifié le {{ new Date(article.updated_at).toLocaleDateString('fr-MA') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📄</p>
      <p>Aucun article trouvé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="openCreate">
        Créer le premier article
      </button>
    </div>

  </div>

  <!-- ════ Modal — Teleported to body ════════════════════ -->
  <!-- Teleport fixes fixed positioning inside scrolled/transformed parents -->
  <Teleport to="body">
    <div
      v-if="showModal"
      class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
      @click.self="showModal = false"
    >
      <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-5">

        <!-- Modal header -->
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-xl">
            {{ modalMode === 'create' ? 'Nouvel article' : 'Modifier l\'article' }}
          </h2>
          <button
            class="text-app-text/40 hover:text-white text-xl"
            @click="showModal = false"
          >✕</button>
        </div>

        <!-- Form -->
        <form class="space-y-4" @submit.prevent="submitArticle">

          <!-- Title -->
          <div>
            <label class="f-label">Titre *</label>
            <input
              v-model="form.title"
              class="f-input"
              required
              placeholder="Ex: ARTICLE 1 — DURÉE"
            />
          </div>

          <!-- Body -->
          <div>
            <label class="f-label">Contenu</label>
            <textarea
              v-model="form.body"
              class="f-input min-h-[180px] resize-y leading-relaxed"
              placeholder="Texte de la clause... Vous pouvez utiliser {{variable}} pour les valeurs dynamiques."
            />

            <!-- Template variables clickable hints -->
            <div class="mt-2 p-3 rounded-xl bg-white/3 border border-white/10 space-y-2">
              <p class="text-xs text-gold font-semibold">
                Variables disponibles — cliquez pour insérer :
              </p>
              <div class="flex flex-wrap gap-2">
                <code
                  v-for="v in templateVars"
                  :key="v"
                  class="text-xs px-2 py-0.5 rounded bg-gold/10 text-gold font-mono cursor-pointer hover:bg-gold/20 transition"
                  @click="insertVar(v)"
                >{{ v }}</code>
              </div>
              <p class="text-xs text-app-text/30">
                Ces variables seront remplacées automatiquement lors de la génération du PDF.
              </p>
            </div>
          </div>

          <!-- Active toggle -->
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="w-10 h-6 rounded-full transition-colors relative flex-shrink-0"
              :class="form.is_active ? 'bg-gold' : 'bg-white/20'"
              @click="form.is_active = !form.is_active"
            >
              <span
                class="absolute top-0.5 w-5 h-5 rounded-full bg-white transition-transform"
                :class="form.is_active ? 'translate-x-4' : 'translate-x-0.5'"
              />
            </button>
            <span class="text-sm text-app-text/70">
              Article actif (inclus par défaut dans les nouveaux contrats)
            </span>
          </div>

          <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

          <!-- Footer buttons -->
          <div class="flex gap-3 justify-end pt-2">
            <button
              type="button"
              class="btn btn-outline btn-md"
              @click="showModal = false"
            >Annuler</button>
            <button
              type="submit"
              class="btn btn-gold btn-md"
              :disabled="saving"
            >
              {{ saving
                ? 'Enregistrement...'
                : modalMode === 'create'
                  ? 'Créer l\'article'
                  : 'Sauvegarder'
              }}
            </button>
          </div>

        </form>
      </div>
    </div>
  </Teleport>

</template>