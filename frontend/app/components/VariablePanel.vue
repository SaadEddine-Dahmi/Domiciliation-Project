<!-- app/components/VariablePanel.vue -->
<!--
  Reusable variable panel for article body editing.
  Supports both:
    - Click to insert at cursor position (works on mobile too)
    - Drag and drop into any textarea (desktop)

  Props:
    - textareaRef: Ref<HTMLTextAreaElement | null> — the target textarea
    - modelValue:  string — the current textarea value (v-model)

  Emits:
    - update:modelValue — updated string after insertion
-->
<script setup lang="ts">
interface Variable {
  key: string        // e.g. 'raison_sociale'
  label: string      // e.g. 'Raison sociale'
  group: string      // e.g. 'Entreprise'
}

const props = defineProps<{
  textareaRef: { value: HTMLTextAreaElement | null }
  modelValue:  string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

// ── All available variables grouped ──────────────────────
const variableGroups: { group: string; color: string; vars: Variable[] }[] = [
  {
    group: 'Domiciliataire',
    color: 'text-purple-400 bg-purple-400/10 border-purple-400/20',
    vars: [
      { key: 'domiciliataire_nom',     label: 'Nom',          group: 'Domiciliataire' },
      { key: 'domiciliataire_rc',      label: 'RC',           group: 'Domiciliataire' },
      { key: 'domiciliataire_if',      label: 'IF',           group: 'Domiciliataire' },
      { key: 'domiciliataire_adresse', label: 'Adresse',      group: 'Domiciliataire' },
    ],
  },
  {
    group: 'Entreprise',
    color: 'text-blue-400 bg-blue-400/10 border-blue-400/20',
    vars: [
      { key: 'raison_sociale',    label: 'Raison sociale',    group: 'Entreprise' },
      { key: 'forme_juridique',   label: 'Forme juridique',   group: 'Entreprise' },
      { key: 'adresse_entreprise',label: 'Adresse',           group: 'Entreprise' },
      { key: 'ville',             label: 'Ville',             group: 'Entreprise' },
      { key: 'pays',              label: 'Pays',              group: 'Entreprise' },
      { key: 'capital',           label: 'Capital',           group: 'Entreprise' },
    ],
  },
  {
    group: 'Représentant',
    color: 'text-green-400 bg-green-400/10 border-green-400/20',
    vars: [
      { key: 'gerant_nom',         label: 'Nom complet',      group: 'Représentant' },
      { key: 'gerant_cin',         label: 'CIN',              group: 'Représentant' },
      { key: 'gerant_naissance',   label: 'Date naissance',   group: 'Représentant' },
      { key: 'gerant_adresse',     label: 'Adresse',          group: 'Représentant' },
      { key: 'gerant_telephone',   label: 'Téléphone',        group: 'Représentant' },
      { key: 'gerant_email',       label: 'Email',            group: 'Représentant' },
      { key: 'gerant_nationalite', label: 'Nationalité',      group: 'Représentant' },
    ],
  },
  {
    group: 'Contrat',
    color: 'text-gold bg-gold/10 border-gold/20',
    vars: [
      { key: 'numero_contrat',   label: 'N° Contrat',         group: 'Contrat' },
      { key: 'instruction_no',   label: 'N° Instruction',     group: 'Contrat' },
      { key: 'date_debut',       label: 'Date début',         group: 'Contrat' },
      { key: 'date_fin',         label: 'Date fin',           group: 'Contrat' },
      { key: 'duree_mois',       label: 'Durée (mois)',       group: 'Contrat' },
      { key: 'date_signature',   label: 'Date signature',     group: 'Contrat' },
      { key: 'ville_signature',  label: 'Ville signature',    group: 'Contrat' },
    ],
  },
  {
    group: 'Financier',
    color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20',
    vars: [
      { key: 'redevance_mensuelle', label: 'Redevance mensuelle', group: 'Financier' },
      { key: 'redevance_annuelle',  label: 'Redevance annuelle',  group: 'Financier' },
      { key: 'caution',             label: 'Caution',             group: 'Financier' },
      { key: 'mode_paiement',       label: 'Mode paiement',       group: 'Financier' },
    ],
  },
]

// ── Insert variable into textarea ─────────────────────────
// Works for both click and drop
// Inserts {{key}} at the current cursor position
function insertVariable(key: string): void {
  const tag      = `{{${key}}}`
  const textarea = props.textareaRef?.value

  if (!textarea) {
    // Fallback: append at end if no textarea ref
    emit('update:modelValue', props.modelValue + ' ' + tag)
    return
  }

  // Get cursor position
  const start = textarea.selectionStart ?? props.modelValue.length
  const end   = textarea.selectionEnd   ?? props.modelValue.length

  // Insert tag at cursor
  const before = props.modelValue.slice(0, start)
  const after  = props.modelValue.slice(end)
  const newVal = before + tag + after

  emit('update:modelValue', newVal)

  // Restore cursor position after Vue updates DOM
  nextTick(() => {
    textarea.focus()
    const newPos = start + tag.length
    textarea.setSelectionRange(newPos, newPos)
  })
}

// ── Drag and drop handlers ────────────────────────────────

// Called when drag starts on a variable chip
function onDragStart(event: DragEvent, key: string): void {
  if (!event.dataTransfer) return
  event.dataTransfer.setData('text/plain', `{{${key}}}`)
  event.dataTransfer.effectAllowed = 'copy'
}

// Called when dragging over the textarea — allow drop
function onDragOver(event: DragEvent): void {
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'
}

// Called when variable is dropped onto textarea
function onDrop(event: DragEvent): void {
  event.preventDefault()
  const tag      = event.dataTransfer?.getData('text/plain') ?? ''
  const textarea = props.textareaRef?.value
  if (!tag || !textarea) return

  // Get drop position using caretPositionFromPoint or caretRangeFromPoint
  let insertPos = props.modelValue.length // fallback: end

  if ('caretPositionFromPoint' in document) {
    const pos = (document as any).caretPositionFromPoint(event.clientX, event.clientY)
    if (pos) insertPos = pos.offset
  } else if ('caretRangeFromPoint' in document) {
    const range = (document as any).caretRangeFromPoint(event.clientX, event.clientY)
    if (range) insertPos = range.startOffset
  }

  const before = props.modelValue.slice(0, insertPos)
  const after  = props.modelValue.slice(insertPos)
  emit('update:modelValue', before + tag + after)

  nextTick(() => {
    textarea.focus()
    const newPos = insertPos + tag.length
    textarea.setSelectionRange(newPos, newPos)
  })
}

// Expose onDragOver and onDrop so parent can bind them to textarea
defineExpose({ onDragOver, onDrop })
</script>

<template>
  <div class="space-y-3">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <p class="text-xs font-semibold text-app-text/60 uppercase tracking-widest">
        Variables disponibles
      </p>
      <p class="text-xs text-app-text/30">
        Cliquez ou glissez dans le texte
      </p>
    </div>

    <!-- Groups -->
    <div class="space-y-3">
      <div
        v-for="group in variableGroups"
        :key="group.group"
      >
        <!-- Group label -->
        <p class="text-xs text-app-text/40 mb-1.5 font-medium">
          {{ group.group }}
        </p>

        <!-- Variable chips -->
        <div class="flex flex-wrap gap-1.5">
          <div
            v-for="v in group.vars"
            :key="v.key"
            class="variable-chip text-xs px-2.5 py-1 rounded-lg border font-mono cursor-grab
                   active:cursor-grabbing select-none transition-all hover:scale-105
                   active:scale-95 flex items-center gap-1.5"
            :class="group.color"
            draggable="true"
            :title="`Cliquer ou glisser pour insérer {{${v.key}}}`"
            @click="insertVariable(v.key)"
            @dragstart="onDragStart($event, v.key)"
          >
            <!-- Drag icon -->
            <span class="opacity-40 text-[10px]">⠿</span>
            {{ v.label }}
          </div>
        </div>
      </div>
    </div>

    <!-- Usage hint -->
    <div class="rounded-xl border border-white/5 bg-white/3 p-3 space-y-1.5">
      <p class="text-xs text-app-text/40 font-medium">Comment ça marche :</p>
      <p class="text-xs text-app-text/30">
        🖱 <strong class="text-app-text/50">Desktop</strong> — Glissez une variable directement dans le texte
      </p>
      <p class="text-xs text-app-text/30">
        👆 <strong class="text-app-text/50">Mobile</strong> — Tapez dans le texte pour positionner le curseur, puis touchez une variable
      </p>
      <p class="text-xs text-app-text/30 mt-1">
        Dans le PDF, <code class="text-gold bg-gold/10 px-1 rounded">{'{{raison_sociale}}'}</code>
        devient <strong class="text-white">BRONX IMMOBILIER</strong>
      </p>
    </div>

  </div>
</template>

<style scoped>
.variable-chip {
  user-select: none;
  -webkit-user-select: none;
  touch-action: none;
}
</style>