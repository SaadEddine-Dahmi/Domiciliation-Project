<!-- components/PhoneNumberField.vue -->
<script setup lang="ts">
// Reusable "dial code + phone number" field.
// (see full rationale comment from the previous version — unchanged)

import { countryDialCodes } from '~/utils/countryDialCodes'

const props = withDefaults(defineProps<{
  dialCode: string
  number: string
  label?: string
  placeholder?: string
  required?: boolean
}>(), {
  label: 'Téléphone',
  placeholder: '6 XX XX XX XX',
  required: false,
})

const emit = defineEmits<{
  'update:dialCode': [value: string]
  'update:number': [value: string]
}>()

const open           = ref(false)
const query          = ref('')
const rootEl         = ref<HTMLElement | null>(null)
const searchInputEl  = ref<HTMLInputElement | null>(null)

const selected = computed(() =>
  countryDialCodes.find(c => c.dialCode === props.dialCode) ?? null
)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return countryDialCodes
  return countryDialCodes.filter(c =>
    c.country.toLowerCase().includes(q) ||
    c.iso.toLowerCase().includes(q) ||
    c.dialCode.includes(q)
  )
})

function toggle(): void {
  open.value = !open.value
  if (open.value) {
    query.value = ''
    nextTick(() => searchInputEl.value?.focus())
  }
}

function select(code: typeof countryDialCodes[number]): void {
  emit('update:dialCode', code.dialCode)
  open.value = false
}

function onNumberInput(e: Event): void {
  emit('update:number', (e.target as HTMLInputElement).value)
}

function onClickOutside(e: MouseEvent): void {
  if (!open.value) return
  if (rootEl.value && !rootEl.value.contains(e.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div>
    <label class="f-label">{{ label }}<span v-if="required"> *</span></label>

    <div ref="rootEl" class="flex gap-2">

      <!--
        FIX: width bumped 104px → 148px, and internal layout switched to
        justify-between instead of an ml-auto spacer. At 104px, the ISO
        badge + "+212" + chevron didn't fit and "+212" got clipped to
        "+2...". 148px gives comfortable room without needing to shrink
        the badge or hide the chevron.
      -->
      <div class="relative shrink-0">
        <button
          type="button"
          class="f-input flex items-center justify-between gap-1.5 !px-2.5 h-full whitespace-nowrap"
          style="width:148px"
          @click="toggle"
        >
          <span class="flex items-center gap-1.5 min-w-0">
            <span
              class="text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0"
              style="background:rgba(200,169,110,0.15);color:#c8a96e"
            >
              {{ selected?.iso ?? '—' }}
            </span>
            <span class="text-sm" style="color:var(--app-text)">{{ dialCode }}</span>
          </span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="shrink-0"
               stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
               style="color:var(--app-text-faint)">
            <path d="M6 9l6 6 6-6"/>
          </svg>
        </button>

        <div
          v-if="open"
          class="absolute left-0 top-full mt-1.5 w-72 rounded-xl shadow-xl z-50 overflow-hidden"
          style="background:var(--app-surface-2);border:1px solid var(--app-border-2)"
        >
          <div class="p-2" style="border-bottom:1px solid var(--app-border-2)">
            <input
              ref="searchInputEl"
              v-model="query"
              class="f-input !py-1.5 text-sm"
              placeholder="Rechercher un pays, indicatif..."
            />
          </div>
          <ul class="max-h-56 overflow-y-auto p-1">
            <li v-for="c in filtered" :key="c.iso">
              <button
                type="button"
                class="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg text-left transition-colors hover:bg-white/5"
                :style="c.dialCode === dialCode ? 'background:rgba(200,169,110,0.1)' : ''"
                @click="select(c)"
              >
                <span
                  class="text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0"
                  style="background:rgba(200,169,110,0.15);color:#c8a96e"
                >{{ c.iso }}</span>
                <span class="flex-1 truncate text-sm" style="color:var(--app-text)">{{ c.country }}</span>
                <span class="text-xs shrink-0" style="color:var(--app-text-faint)">{{ c.dialCode }}</span>
              </button>
            </li>
            <li v-if="!filtered.length" class="text-xs text-center py-4" style="color:var(--app-text-faint)">
              Aucun pays trouvé
            </li>
          </ul>
        </div>
      </div>

      <!-- Phone number -->
      <input
        :value="number"
        class="f-input flex-1 min-w-0"
        type="tel"
        inputmode="tel"
        :placeholder="placeholder"
        @input="onNumberInput"
      />
    </div>
  </div>
</template>