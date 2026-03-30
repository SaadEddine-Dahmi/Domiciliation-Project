import { defineStore } from 'pinia'
import { reactive, ref, computed } from 'vue'

export interface DocumentItem {
    id: number
    clientId: number
    name: string
    type: 'pdf' | 'image' | 'doc'
    size: string
    date: string
    pending?: boolean
}

export const useDocumentsStore = defineStore('documents', () => {
    const items = ref<DocumentItem[]>([
        { id: 1, clientId: 1, name: 'Contrat-BRONX-2026.pdf', type: 'pdf', size: '340 KB', date: '2026-03-01', pending: false },
        { id: 2, clientId: 1, name: 'CIN-Gerant.jpg', type: 'image', size: '1.2 MB', date: '2026-03-02', pending: true },
        { id: 3, clientId: 2, name: 'Patente-ATLAS.pdf', type: 'pdf', size: '510 KB', date: '2026-03-03', pending: true },
    ])

    const pendingCount = computed(() => items.value.filter(d => d.pending).length)

    function add(doc: Omit<DocumentItem, 'id'>) {
        const id = Math.max(0, ...items.value.map(i => i.id)) + 1
        items.value.unshift({ ...doc, id })
    }

    function remove(id: number) {
        items.value = items.value.filter(d => d.id !== id)
    }

    function forClient(clientId: number) {
        return items.value.filter(d => d.clientId === clientId)
    }

    return { items, pendingCount, add, remove, forClient }
})