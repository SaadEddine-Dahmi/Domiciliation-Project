<template>
  <div :class="span2 ? 'col-span-2' : ''">
    <label class="f-label">{{ label }}</label>

    <textarea
      v-if="textarea"
      :value="modelValue"
      class="f-input min-h-[96px]"
      :placeholder="placeholder"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />

    <input
      v-else
      :value="modelValue"
      :type="type"
      class="f-input"
      :placeholder="placeholder"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  modelValue: string | number
  placeholder?: string
  type?: string
  textarea?: boolean
  span2?: boolean
}>(), {
  placeholder: '',
  type: 'text',
  textarea: false,
  span2: false,
})

defineEmits<{ (e: 'update:modelValue', value: string): void }>()
</script>