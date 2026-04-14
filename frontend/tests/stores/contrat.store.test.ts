// tests/stores/contrat.store.test.ts
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useContractStore } from '~/stores/contrat'

describe('Contract Store', () => {

    beforeEach(() => {
        setActivePinia(createPinia())
    })

    // ── Initial state ─────────────────────────────────────────

    it('has default AST-FISC name', () => {
        const store = useContractStore()
        expect(store.form.astNom).toBe('AST-FISC SARL AU')
    })

    it('starts with 12 months default', () => {
        const store = useContractStore()
        expect(store.form.months).toBe(12)
    })

    it('starts with zero redevance', () => {
        const store = useContractStore()
        expect(store.form.redevanceMensuelle).toBe(0)
        expect(store.form.redevanceAnnuelle).toBe(0)
    })

    it('starts with empty selected services', () => {
        const store = useContractStore()
        expect(store.selectedServices).toHaveLength(0)
    })

    // ── setMonthly ────────────────────────────────────────────

    it('setMonthly updates monthly and calculates annual', () => {
        const store = useContractStore()
        store.form.months = 12
        store.setMonthly(500)

        expect(store.form.redevanceMensuelle).toBe(500)
        expect(store.form.redevanceAnnuelle).toBe(6000)
        expect(store.form.lastEdited).toBe('monthly')
    })

    it('setMonthly rounds to 2 decimal places', () => {
        const store = useContractStore()
        store.form.months = 3
        store.setMonthly(100.333)

        expect(store.form.redevanceMensuelle).toBe(100.33)
    })

    it('setMonthly handles zero correctly', () => {
        const store = useContractStore()
        store.setMonthly(0)
        expect(store.form.redevanceMensuelle).toBe(0)
        expect(store.form.redevanceAnnuelle).toBe(0)
    })

    // ── setAnnual ─────────────────────────────────────────────

    it('setAnnual updates annual and calculates monthly', () => {
        const store = useContractStore()
        store.form.months = 12
        store.setAnnual(6000)

        expect(store.form.redevanceAnnuelle).toBe(6000)
        expect(store.form.redevanceMensuelle).toBe(500)
        expect(store.form.lastEdited).toBe('annual')
    })

    it('setAnnual handles non-12 month periods', () => {
        const store = useContractStore()
        store.form.months = 6
        store.setAnnual(3000)

        expect(store.form.redevanceMensuelle).toBe(500)
    })

    // ── monthlyTotal getter ───────────────────────────────────

    it('monthlyTotal includes service prices', () => {
        const store = useContractStore()
        store.setMonthly(500)
        store.selectedServices = ['scan'] // price: 100

        expect(store.monthlyTotal).toBe(600)
    })

    it('monthlyTotal with multiple services', () => {
        const store = useContractStore()
        store.setMonthly(500)
        store.selectedServices = ['scan', 'forward'] // 100 + 150

        expect(store.monthlyTotal).toBe(750)
    })

    it('monthlyTotal equals redevanceMensuelle when no services', () => {
        const store = useContractStore()
        store.setMonthly(400)

        expect(store.monthlyTotal).toBe(400)
    })

    // ── grandTotal getter ─────────────────────────────────────

    it('grandTotal multiplies monthly total by months', () => {
        const store = useContractStore()
        store.form.months = 12
        store.setMonthly(500)

        expect(store.grandTotal).toBe(6000)
    })

    it('grandTotal includes services in calculation', () => {
        const store = useContractStore()
        store.form.months = 6
        store.selectedServices = ['scan'] // 100
        store.setMonthly(500)

        expect(store.grandTotal).toBe(3600) // (500+100) * 6
    })

    // ── toggleService ─────────────────────────────────────────

    it('toggleService adds service when not selected', () => {
        const store = useContractStore()
        store.toggleService('scan')

        expect(store.selectedServices).toContain('scan')
    })

    it('toggleService removes service when already selected', () => {
        const store = useContractStore()
        store.selectedServices = ['scan']
        store.toggleService('scan')

        expect(store.selectedServices).not.toContain('scan')
    })

    it('toggleService recalculates totals', () => {
        const store = useContractStore()
        store.setMonthly(500)
        const before = store.monthlyTotal

        store.toggleService('scan')

        expect(store.monthlyTotal).toBeGreaterThan(before)
    })

    // ── syncFromMonths ────────────────────────────────────────

    it('syncFromMonths recalculates when lastEdited is monthly', () => {
        const store = useContractStore()
        store.setMonthly(500)         // lastEdited = 'monthly'
        store.form.months = 6
        store.syncFromMonths()

        expect(store.form.redevanceAnnuelle).toBe(3000)
    })

    it('syncFromMonths recalculates when lastEdited is annual', () => {
        const store = useContractStore()
        store.setAnnual(6000)         // lastEdited = 'annual'
        store.form.months = 6
        store.syncFromMonths()

        expect(store.form.redevanceMensuelle).toBe(1000)
    })
})