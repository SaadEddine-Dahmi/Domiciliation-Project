import { defineStore } from 'pinia'
import { reactive, ref, computed } from 'vue'

export interface NotifItem {
    id: number
    text: string
    time: string
    unread: boolean
    color: string
}

export const useNotificationsStore = defineStore('notifications', () => {
    const items = ref<NotifItem[]>([
        { id: 1, text: 'Nouveau document importé pour BRONX IMMOBILIER', time: 'Il y a 5 min', unread: true, color: '#60a5fa' },
        { id: 2, text: 'Paiement validé - ATLAS LOGISTICS', time: 'Il y a 1 h', unread: true, color: '#22c55e' },
        { id: 3, text: 'Contrat généré avec succès', time: 'Hier', unread: false, color: '#c8a96e' },
    ])

    const unreadCount = computed(() => items.value.filter(n => n.unread).length)

    function push(text: string, color = '#c8a96e') {
        const id = Math.max(0, ...items.value.map(i => i.id)) + 1
        items.value.unshift({ id, text, time: 'À l’instant', unread: true, color })
    }

    function markRead(id: number) {
        const n = items.value.find(i => i.id === id)
        if (n) n.unread = false
    }

    function markAllRead() {
        items.value.forEach(i => { i.unread = false })
    }

    return { items, unreadCount, push, markRead, markAllRead }
})