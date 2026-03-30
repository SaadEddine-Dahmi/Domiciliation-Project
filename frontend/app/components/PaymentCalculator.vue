<script setup lang="ts">
import { computed } from 'vue'
import { useContractStore } from '~/stores/contrat'

type ServiceOption = {
  id: string
  label: string
  price: number
}

const contract = useContractStore()

/**
 * Defensive computed wrappers to avoid runtime errors
 * when store fields are temporarily undefined.
 */
const options = computed<ServiceOption[]>(() => {
  const raw = (contract as any).serviceOptions
  return Array.isArray(raw) ? raw : []
})

const selected = computed<string[]>(() => {
  const raw = (contract as any).selectedServices
  return Array.isArray(raw) ? raw : []
})

const monthlyBase = computed<number>(() => Number((contract.form as any)?.redevanceMensuelle || 0))

const servicesMonthlyTotal = computed<number>(() =>
  options.value.reduce((sum, opt) => {
    return selected.value.includes(opt.id) ? sum + Number(opt.price || 0) : sum
  }, 0)
)

const monthlyTotal = computed<number>(() => monthlyBase.value + servicesMonthlyTotal.value)

const months = computed<number>(() => {
  const m = Number((contract.form as any)?.months || 0)
  return m > 0 ? m : 1
})

const annualTotal = computed<number>(() => monthlyTotal.value * months.value)

function isSelected(id: string) {
  return selected.value.includes(id)
}

function toggleService(id: string) {
  const current = [...selected.value]
  if (current.includes(id)) {
    ;(contract as any).selectedServices = current.filter((x) => x !== id)
  } else {
    current.push(id)
    ;(contract as any).selectedServices = current
  }

  // keep annual in sync if monthly mode is active
  if ((contract.form as any)?.lastEdited !== 'annual') {
    ;(contract.form as any).redevanceAnnuelle = Number((contract.form as any).redevanceMensuelle || 0) * months.value
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="card p-4">
      <h4 class="font-serif text-base mb-3">Services additionnels</h4>

      <div v-if="options.length === 0" class="text-sm text-app-text/60">
        Aucun service configuré.
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="opt in options"
          :key="opt.id"
          class="rounded-xl border p-3 flex items-center justify-between"
          :style="
            isSelected(opt.id)
              ? 'background:rgba(200,169,110,.08);border-color:rgba(200,169,110,.35)'
              : 'background:transparent;border-color:rgba(255,255,255,.12)'
          "
        >
          <button class="text-left flex-1" @click="toggleService(opt.id)">
            {{ opt.label }}
          </button>
          <div class="flex items-center gap-3">
            <span class="text-sm">{{ opt.price }} DH / mois</span>
            <span class="text-gold font-bold">{{ isSelected(opt.id) ? '✓' : '' }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card p-4">
      <h4 class="font-serif text-base mb-3">Récap calcul</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div class="card p-3">Base mensuelle: <b>{{ monthlyBase }} DH</b></div>
        <div class="card p-3">Services mensuels: <b>{{ servicesMonthlyTotal }} DH</b></div>
        <div class="card p-3">Total mensuel: <b>{{ monthlyTotal }} DH</b></div>
        <div class="card p-3">Total ({{ months }} mois): <b class="text-gold">{{ annualTotal }} DH</b></div>
      </div>
    </div>
  </div>
</template>