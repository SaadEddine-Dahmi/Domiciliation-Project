// composables/useSidebar.ts
// Singleton sidebar state — shared between AppSidebar, AppTopbar, dashboard layout
import { ref, watch } from 'vue'

const _open = ref(true)

export function useSidebar() {
    function toggle() { _open.value = !_open.value }
    function open() { _open.value = true }
    function close() { _open.value = false }

    // Persist preference across navigation
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('sidebar_open')
        if (saved !== null) _open.value = saved !== 'false'

        watch(_open, (val) => {
            localStorage.setItem('sidebar_open', String(val))
        })
    }

    return { isOpen: _open, toggle, open, close }
}