<!-- pages/admin/templates.vue -->
<script setup lang="ts">
import { templateService, type TemplateEntity } from '~/services/template.service'
import { useArticlesStore } from '~/stores/articles'

definePageMeta({ layout: 'dashboard', middleware: ['auth'] })

const articlesStore = useArticlesStore()
const { success, error: toastError } = useToast()
const { confirm: confirmAction } = useConfirm()

// ── State ─────────────────────────────────────────────────
const templates    = ref<TemplateEntity[]>([])
const loading      = ref(true)
const search       = ref('')
const showModal    = ref(false)
const modalMode    = ref<'create' | 'edit'>('create')
const editId       = ref<number | null>(null)
const saving       = ref(false)
const serverError  = ref('')

// ── Form ──────────────────────────────────────────────────
const form = reactive({
  name:        '',
  description: '',
})

// Same article-selection mechanics as the contract wizard: a flat string[]
// of selected IDs plus a parallel ordered array for drag-and-drop, kept as
// two structures because Vue tracks array reassignment/mutation reactively
// but does not track internal mutation of a Set/Map — see contrat.vue for
// the full explanation of why this pattern is required.
const selectedArticleIds = ref<string[]>([])
const orderedArticles    = ref<any[]>([])
const dragIndex          = ref<number | null>(null)

/** Add or remove an article from the current selection, keeping ordre dense. */
function toggleArticle(article: any): void {
  const id = String(article.id)
  if (selectedArticleIds.value.includes(id)) {
    selectedArticleIds.value = selectedArticleIds.value.filter(x => x !== id)
    orderedArticles.value    = orderedArticles.value
      .filter(a => String(a.id) !== id)
      .map((a, i) => ({ ...a, ordre: i + 1 }))
  } else {
    const newOrdre = orderedArticles.value.length + 1
    selectedArticleIds.value.push(id)
    orderedArticles.value.push({ ...article, ordre: newOrdre })
  }
}

function onDragStart(index: number, event: DragEvent): void {
  dragIndex.value = index
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDragOver(event: DragEvent): void {
  event.preventDefault()
}

/** Reorders orderedArticles after a drag-drop, re-numbering ordre to stay dense. */
function onDrop(targetIndex: number): void {
  if (dragIndex.value === null || dragIndex.value === targetIndex) return
  const arr = [...orderedArticles.value]
  const [moved] = arr.splice(dragIndex.value, 1)
  arr.splice(targetIndex, 0, moved)
  orderedArticles.value = arr.map((a, i) => ({ ...a, ordre: i + 1 }))
  dragIndex.value = null
}

// ── Load ──────────────────────────────────────────────────

async function loadTemplates(): Promise<void> {
  loading.value = true
  try {
    const res = await templateService.list()
    templates.value = res.data ?? []
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur chargement modèles')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!search.value.trim()) return templates.value
  const q = search.value.toLowerCase()
  return templates.value.filter(t => t.name?.toLowerCase().includes(q))
})

function versionLabel(template: TemplateEntity): string {
  const value = template.updated_at ?? template.created_at
  if (!value) return 'v1'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return 'v1'
  return `v${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
}

// ── Modal: create ─────────────────────────────────────────

function openCreate(): void {
  modalMode.value   = 'create'
  editId.value      = null
  serverError.value = ''
  Object.assign(form, { name: '', description: '' })
  selectedArticleIds.value = []
  orderedArticles.value    = []
  showModal.value = true
}

// ── Modal: edit ───────────────────────────────────────────

function openEdit(template: TemplateEntity): void {
  modalMode.value   = 'edit'
  editId.value      = template.id
  serverError.value = ''
  Object.assign(form, {
    name:        template.name ?? '',
    description: template.description ?? '',
  })
  const sorted = [...template.articles].sort(
    (a, b) => (a.pivot?.ordre ?? 0) - (b.pivot?.ordre ?? 0)
  )
  selectedArticleIds.value = sorted.map(a => String(a.id))
  orderedArticles.value    = sorted.map((a, i) => ({ ...a, ordre: a.pivot?.ordre ?? i + 1 }))
  showModal.value = true
}

// ── Submit ────────────────────────────────────────────────

async function submitTemplate(): Promise<void> {
  if (!form.name.trim()) {
    serverError.value = 'Le nom du modèle est obligatoire.'
    return
  }
  serverError.value = ''
  saving.value      = true
  try {
    const payload = orderedArticles.value.map(a => ({ id: String(a.id), ordre: a.ordre }))

    if (modalMode.value === 'create') {
      await templateService.create(form.name.trim(), form.description.trim(), payload)
      success('Modèle créé avec succès')
    } else if (editId.value) {
      await templateService.update(editId.value, form.name.trim(), form.description.trim(), payload)
      success('Modèle mis à jour')
    }
    showModal.value = false
    await loadTemplates()
  } catch (e: any) {
    serverError.value = e?.data?.errors
      ? Object.values(e.data.errors).flat().join(' · ')
      : e?.data?.message ?? 'Erreur lors de la sauvegarde'
    toastError?.(serverError.value)
  } finally {
    saving.value = false
  }
}

// ── Delete ────────────────────────────────────────────────

async function deleteTemplate(id: number): Promise<void> {
  if (!(await confirmAction({
    title: 'Supprimer le modele',
    message: 'Supprimer ce modele definitivement ?',
    confirmLabel: 'Supprimer',
    variant: 'danger',
  }))) return
  try {
    await templateService.remove(id)
    success('Modèle supprimé')
    await loadTemplates()
  } catch (e: any) {
    toastError?.(e?.data?.message ?? 'Erreur suppression')
  }
}

onMounted(async () => {
  await Promise.all([loadTemplates(), articlesStore.fetchAll()])
})
</script>

<template>
  <div class="space-y-5 animate-fade-up">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-serif text-2xl">
          Modèles <em class="text-gold italic">de contrat</em>
        </h1>
        <p class="text-app-text/50 text-sm mt-1">
          Ensembles d'articles réutilisables pour accélérer la création de contrats
        </p>
      </div>
      <button class="btn btn-gold btn-md" @click="openCreate">
        + Nouveau modèle
      </button>
    </div>

    <!-- Search -->
    <div class="card p-4 rounded-xl border border-[var(--app-border,#212936)] bg-[var(--app-card-bg,#131822)] space-y-3">
  <!-- Controls Row -->
  <div class="flex flex-wrap items-center gap-3">
    
    <!-- Search Input -->
    <div class="relative flex-1 min-w-[220px]">
      <svg 
        class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
        width="15" height="15" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round"
        style="color: var(--app-text-faint, #8A94A6)"
      >
        <circle cx="11" cy="11" r="8"/>
        <path d="M21 21l-4.35-4.35"/>
      </svg>
      <input
        v-model="search"
        type="text"
        autocomplete="off"
        class="w-full pl-9 pr-3 py-2 bg-[var(--app-bg,#0B0E14)] border border-[var(--app-border,#212936)] rounded-lg text-sm text-[var(--app-text,#FFF)] placeholder:text-[var(--app-text-faint,#8A94A6)] focus:outline-none focus:border-[#E5C158] transition"
        placeholder="Rechercher un modèle..."
      />
    </div>

  </div>
</div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-app-text/40">Chargement...</div>

    <!-- Templates list -->
    <div v-else-if="filtered.length" class="space-y-3">
      <div v-for="tpl in filtered" :key="tpl.id" class="card p-4 space-y-3">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div class="flex-1 min-w-0">
            <p class="font-semibold">{{ tpl.name }}</p>
            <p v-if="tpl.description" class="text-sm text-app-text/50 mt-1">{{ tpl.description }}</p>
            <p class="text-xs text-app-text/40 mt-2">
              {{ tpl.articles.length }} article(s) - {{ versionLabel(tpl) }}
            </p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button class="btn btn-outline btn-sm" @click="openEdit(tpl)">Modifier</button>
            <button class="btn btn-danger btn-sm" @click="deleteTemplate(tpl.id)">✕</button>
          </div>
        </div>
        <div v-if="tpl.articles.length" class="flex flex-wrap gap-2 border-t border-white/5 pt-3">
          <span
            v-for="a in [...tpl.articles].sort((x, y) => (x.pivot?.ordre ?? 0) - (y.pivot?.ordre ?? 0))"
            :key="a.id"
            class="text-xs px-2 py-1 rounded-lg"
            style="background:rgba(200,169,110,0.1);color:#c8a96e;border:1px solid rgba(200,169,110,0.2)"
          >
            {{ a.pivot?.ordre }}. {{ a.title }}
          </span>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="card p-10 text-center text-app-text/40">
      <p class="text-4xl mb-3">📋</p>
      <p>Aucun modèle créé.</p>
      <button class="btn btn-gold btn-md mt-4" @click="openCreate">
        Créer le premier modèle
      </button>
    </div>

    <!-- ════ Modal — Create / Edit ════════════════════════ -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-[100] bg-black/70 flex items-center justify-center p-4"
        @click.self="showModal = false"
      >
        <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-5">
          <div class="flex items-center justify-between">
            <h2 class="font-serif text-xl">
              {{ modalMode === 'create' ? 'Nouveau modèle' : 'Modifier le modèle' }}
            </h2>
            <button class="text-app-text/40 hover:text-white text-xl" @click="showModal = false">✕</button>
          </div>

          <form class="space-y-4" @submit.prevent="submitTemplate">
            <div>
              <label class="f-label">Nom du modèle *</label>
              <input v-model="form.name" class="f-input" required placeholder="Ex: Contrat standard SARL" />
            </div>
            <div>
              <label class="f-label">Description (optionnel)</label>
              <textarea
                v-model="form.description"
                class="f-input min-h-[70px] resize-none"
                placeholder="Ex: Modèle par défaut pour les nouvelles sociétés..."
              />
            </div>

            <div>
              <p class="text-xs uppercase tracking-widest font-bold mb-2" style="color:#c8a96e">
                Articles inclus
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="article in articlesStore.items"
                  :key="String(article.id)"
                  type="button"
                  class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
                  :style="selectedArticleIds.includes(String(article.id))
                    ? 'background:#c8a96e;color:#111;border-color:#c8a96e'
                    : 'background:var(--app-surface-2);border-color:var(--app-border);color:var(--app-text-muted)'"
                  @click="toggleArticle(article)"
                >
                  {{ selectedArticleIds.includes(String(article.id)) ? '✓ ' : '+ ' }}{{ article.title }}
                </button>

                <p v-if="articlesStore.items.length === 0" class="text-sm" style="color:var(--app-text-faint)">
                  Aucun article dans la bibliothèque.
                  <NuxtLink to="/admin/articles" class="underline" target="_blank">Créer des articles →</NuxtLink>
                </p>
              </div>
            </div>

            <div v-if="orderedArticles.length" class="space-y-2">
              <p class="text-xs uppercase tracking-widest font-bold" style="color:#c8a96e">
                Ordre des articles — glissez pour réordonner
              </p>
              <div
                v-for="(article, index) in orderedArticles"
                :key="String(article.id)"
                draggable="true"
                class="flex items-center gap-3 px-3 py-2 rounded-lg"
                style="background:var(--app-surface-2);border:1px solid var(--app-border)"
                @dragstart="onDragStart(index, $event)"
                @dragover="onDragOver"
                @drop="onDrop(index)"
              >
                <svg class="shrink-0 cursor-grab" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.8" style="color:var(--app-text-faint)">
                  <circle cx="9" cy="5"  r="1"/><circle cx="15" cy="5"  r="1"/>
                  <circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/>
                  <circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
                <span
                  class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0"
                  style="background:rgba(200,169,110,0.2);color:#c8a96e"
                >
                  {{ article.ordre }}
                </span>
                <span class="flex-1 text-sm truncate">{{ article.title }}</span>
                <button type="button" class="text-red-400 text-xs shrink-0" @click="toggleArticle(article)">
                  ✕
                </button>
              </div>
            </div>

            <p v-if="serverError" class="text-red-400 text-sm">{{ serverError }}</p>

            <div class="flex gap-3 justify-end pt-2">
              <button type="button" class="btn btn-outline btn-md" @click="showModal = false">Annuler</button>
              <button type="submit" class="btn btn-gold btn-md" :disabled="saving">
                {{ saving ? 'Enregistrement...' : modalMode === 'create' ? 'Créer le modèle' : 'Sauvegarder' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </div>
</template>
