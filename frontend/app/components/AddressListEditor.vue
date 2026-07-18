<!-- app/components/AddressListEditor.vue -->
<!--
  Reusable dynamic address list editor.
  Emits updates via v-model so the parent page owns the state.

  Usage:
    <AddressListEditor v-model="adresses" />

  Data shape:
    adresses = [{ label: 'Siège social', value: '123 Rue Hassan II, Agadir' }, ...]
-->
<script setup lang="ts">
// ── Types ─────────────────────────────────────────────────
export interface Address {
  label: string  // e.g. "Siège social", "Succursale Casablanca"
  value: string  // full address string
}

// ── Props / Emit ──────────────────────────────────────────
// v-model binding: parent passes :modelValue, we emit 'update:modelValue'
const props = defineProps<{
  modelValue: Address[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: Address[]): void
}>()

// ── Local reactive copy ───────────────────────────────────
// We work on a local ref and emit on every mutation.
// This avoids mutating props directly (Vue warning).
const list = ref<Address[]>([...props.modelValue])

// Keep in sync if parent changes the value externally
watch(() => props.modelValue, (val) => {
  list.value = [...val]
}, { deep: true })

// Emit every change upward
function notifyParent(): void {
  emit('update:modelValue', [...list.value])
}

// ── Address operations ─────────────────────────────────────

/**
 * Add a new blank address row.
 * Always starts empty so the user fills in what they need.
 */
function addAddress(): void {
  list.value.push({ label: '', value: '' })
  notifyParent()
}

/**
 * Remove an address by index.
 * The first address can also be removed — parent decides if at least one is required.
 */
function removeAddress(index: number): void {
  list.value.splice(index, 1)
  notifyParent()
}

/**
 * Move an address up in the list (for ordering).
 */
function moveUp(index: number): void {
  if (index === 0) return
  ;[list.value[index - 1], list.value[index]] = [list.value[index], list.value[index - 1]]
  notifyParent()
}

/**
 * Move an address down in the list.
 */
function moveDown(index: number): void {
  if (index === list.value.length - 1) return
  ;[list.value[index], list.value[index + 1]] = [list.value[index + 1], list.value[index]]
  notifyParent()
}

/**
 * Update a specific field of a specific address row.
 * Called by @input on each field.
 */
function updateField(index: number, field: keyof Address, event: Event): void {
  const val = (event.target as HTMLInputElement).value
  list.value[index] = { ...list.value[index], [field]: val }
  notifyParent()
}

// // Label suggestions shown via <datalist> for quick selection
// const labelSuggestions: string[] = [
//   'Siège social',
//   'Succursale 1',
//   'Succursale 2',
//   'Bureau Casablanca',
//   'Bureau Rabat',
//   'Bureau Marrakech',
//   'Bureau Agadir',
//   'Bureau Fès',
//   'Bureau Tanger',
//   'Entrepôt',
//   'Autre',
// ]
</script>

<template>
  <div class="space-y-3">

    <!-- Empty state: shown when no addresses exist yet -->
    <div
      v-if="list.length === 0"
      class="rounded-xl p-8 text-center"
      style="background: var(--app-surface-2);
             border: 2px dashed var(--app-border);
             color: var(--app-text-faint)"
    >
      <!-- Location pin icon -->
      <svg
        class="mx-auto mb-3 opacity-40"
        width="32" height="32" viewBox="0 0 24 24"
        fill="none" stroke="currentColor"
        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
      >
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
        <circle cx="12" cy="10" r="3"/>
      </svg>
      <p class="font-medium mb-1">Aucune adresse ajoutée</p>
      <p class="text-sm mb-4">
        Ajoutez votre siège social, succursales, etc.
      </p>
      <button
        type="button"
        class="btn btn-gold btn-sm"
        @click="addAddress"
      >
        + Ajouter une adresse
      </button>
    </div>

    <!-- Address rows: one card per entry -->
    <div
      v-for="(addr, index) in list"
      :key="index"
      class="rounded-xl p-4 space-y-3"
      style="background: var(--app-surface-2); border: 1px solid var(--app-border)"
    >

      <!-- Row header: badge + ordering controls + delete -->
      <div class="flex items-center justify-between">

        <!-- Index badge -->
        <span
          class="text-xs font-bold px-2.5 py-0.5 rounded-full"
          style="background: rgba(200,169,110,0.15); color: #c8a96e"
        >
          Adresse {{ index + 1 }}
        </span>

        <!-- Controls -->
        <div class="flex items-center gap-1">

          <!-- Move up -->
          <button
            v-if="index > 0"
            type="button"
            class="w-7 h-7 rounded-lg flex items-center justify-center nav-inactive transition-colors"
            title="Monter"
            @click="moveUp(index)"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M18 15l-6-6-6 6"/>
            </svg>
          </button>

          <!-- Move down -->
          <button
            v-if="index < list.length - 1"
            type="button"
            class="w-7 h-7 rounded-lg flex items-center justify-center nav-inactive transition-colors"
            title="Descendre"
            @click="moveDown(index)"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>

          <!-- Delete -->
          <button
            type="button"
            class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors"
            style="color: #ef4444"
            title="Supprimer cette adresse"
            @click="removeAddress(index)"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6M14 11v6"/>
              <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
          </button>

        </div>
      </div>

      <!-- Two-column input grid -->
      <div class="grid grid-cols-1 sm:grid-cols-[180px_1fr] gap-3">

        <!-- Label field — datalist for quick selection -->
        <div>
          <label class="f-label">
            Libellé *
            <span class="text-[10px] ml-1" style="color: var(--app-text-faint)">
              (type d'adresse)
            </span>
          </label>
          <!--
            We use :value + @input instead of v-model because we need to call
            notifyParent after every keystroke to keep parent state in sync.
          -->
          <input
            :id="`addr-label-${index}`"
            :value="addr.label"
            :list="`addr-suggestions-${index}`"
            class="f-input"
            placeholder="Siège social"
            @input="updateField(index, 'label', $event)"
          />
          <!-- Native browser datalist — no JS library needed -->
          <datalist :id="`addr-suggestions-${index}`">
            <option
              v-for="s in labelSuggestions"
              :key="s"
              :value="s"
            />
          </datalist>
        </div>

        <!-- Full address field -->
        <div>
          <label class="f-label">Adresse complète *</label>
          <input
            :id="`addr-value-${index}`"
            :value="addr.value"
            class="f-input"
            placeholder="N° Rue, Quartier, Ville, Code Postal"
            @input="updateField(index, 'value', $event)"
          />
        </div>

      </div>
    </div>

    <!-- "Add another" button — shown after first address exists -->
    <button
      v-if="list.length > 0"
      type="button"
      class="w-full py-3 rounded-xl text-sm font-medium flex items-center
             justify-center gap-2 transition-colors nav-inactive"
      style="border: 2px dashed var(--app-border)"
      @click="addAddress"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Ajouter une autre adresse
    </button>

    <!-- Live preview: what the contract wizard dropdown will look like -->
    <Transition name="fade">
      <div
        v-if="list.some(a => a.label.trim() && a.value.trim())"
        class="rounded-xl p-4"
        style="background: var(--app-surface); border: 1px solid var(--app-border)"
      >
        <p class="text-[10px] uppercase tracking-widest font-bold mb-3"
           style="color: var(--app-text-faint)">
          Aperçu du dropdown dans le contrat
        </p>

        <!-- Simulated select dropdown -->
        <div class="rounded-lg overflow-hidden text-sm"
             style="border: 1px solid var(--app-border)">

          <div
            class="px-3 py-2 text-xs"
            style="background: var(--app-surface-2);
                   color: var(--app-text-faint);
                   border-bottom: 1px solid var(--app-border)"
          >
            -- Choisir une adresse --
          </div>

          <div
            v-for="(addr, i) in list.filter(a => a.label.trim() && a.value.trim())"
            :key="i"
            class="px-3 py-2.5 flex items-start gap-3"
            :class="i > 0 ? 'border-t' : ''"
            :style="i > 0 ? 'border-color: var(--app-border-2)' : ''"
          >
            <!-- Dot: gold for first (assumed siège), gray for others -->
            <div
              class="w-2 h-2 rounded-full flex-shrink-0 mt-1"
              :style="`background: ${i === 0 ? '#c8a96e' : 'var(--app-text-faint)'}`"
            />
            <div class="min-w-0">
              <p class="font-medium text-xs" style="color: var(--app-text)">
                {{ addr.label }}
              </p>
              <p class="text-xs truncate" style="color: var(--app-text-muted)">
                {{ addr.value }}
              </p>
            </div>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
/* Subtle fade for the preview panel */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>