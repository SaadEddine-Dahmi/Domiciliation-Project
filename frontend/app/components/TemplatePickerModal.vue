<!-- components/TemplatePickerModal.vue -->
<script setup lang="ts">
import type { TemplateEntity } from '~/services/template.service'

const props = defineProps<{
  modelValue: boolean
  templates: TemplateEntity[]
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  'select': [TemplateEntity]
  'skip': []
}>()

function close(): void {
  emit('update:modelValue', false)
}

function pick(tpl: TemplateEntity): void {
  emit('select', tpl)
  close()
}

function skip(): void {
  emit('skip')
  close()
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[110] bg-black/70 flex items-center justify-center p-4"
      @click.self="close"
    >
      <div class="card w-full max-w-lg p-6 space-y-4">
        <div class="text-center space-y-1">
          <p class="text-3xl">📋</p>
          <h2 class="font-serif text-xl">Démarrer depuis un modèle ?</h2>
          <p class="text-sm text-app-text/50">
            Chargez instantanément un ensemble d'articles pré-configuré,
            ou continuez avec un contrat vierge.
          </p>
        </div>

        <div v-if="templates.length" class="space-y-2 max-h-64 overflow-y-auto">
          <button
            v-for="tpl in templates" :key="tpl.id"
            type="button"
            class="w-full text-left rounded-xl px-4 py-3 transition-all flex items-center justify-between gap-3"
            style="background:var(--app-surface-2);border:2px solid var(--app-border)"
            @click="pick(tpl)"
          >
            <div class="min-w-0">
              <p class="font-semibold text-sm truncate" style="color:var(--app-text)">{{ tpl.name }}</p>
              <p v-if="tpl.description" class="text-xs truncate" style="color:var(--app-text-faint)">
                {{ tpl.description }}
              </p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full shrink-0"
                  style="background:rgba(200,169,110,0.15);color:#c8a96e">
              {{ tpl.articles.length }} art.
            </span>
          </button>
        </div>

        <div v-else class="text-center py-6 text-sm" style="color:var(--app-text-faint)">
          Aucun modèle disponible pour le moment.
        </div>

        <div class="flex gap-3 justify-center pt-2">
          <button class="btn btn-outline btn-md" @click="skip">
            Continuer sans modèle
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>