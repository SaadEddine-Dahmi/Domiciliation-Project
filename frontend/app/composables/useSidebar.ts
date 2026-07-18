// app/composables/useSidebar.ts
// Module-level state — shared across all components in the same Vue instance.
// isOpen:       desktop sidebar expanded/collapsed state (persisted)
// isMobileOpen: mobile overlay drawer open/closed state (not persisted —
//               should always start closed on a fresh page load)

const isOpen = ref(true)          // desktop: expanded by default
const isMobileOpen = ref(false)   // mobile overlay: closed by default

// Restore desktop preference from localStorage once, at module load.
if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('sidebar_open')
    if (saved !== null) isOpen.value = saved !== 'false'
}

export function useSidebar() {
    function toggle() {
        isOpen.value = !isOpen.value
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar_open', String(isOpen.value))
        }
    }

    function open() {
        isOpen.value = true
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar_open', 'true')
        }
    }

    function close() {
        isOpen.value = false
        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar_open', 'false')
        }
    }

    function toggleMobile() { isMobileOpen.value = !isMobileOpen.value }
    function closeMobile() { isMobileOpen.value = false }

    // FIX: auto-close mobile drawer when resizing to desktop.
    // Without this, isMobileOpen stays true after resizing from phone → desktop,
    // causing a permanent blur on the main content. Listener is added only
    // once per page load (guarded via a window flag) since useSidebar() is
    // called by multiple components.
    if (import.meta.client && !(window as any).__sidebarResizeListenerAdded) {
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                isMobileOpen.value = false
            }
        }, { passive: true })
        ;(window as any).__sidebarResizeListenerAdded = true
    }

    return { isOpen, isMobileOpen, toggle, toggleMobile, open, close, closeMobile }
}