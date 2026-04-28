// app/composables/useSidebar.ts
// Module-level state — shared across all components in the same Vue instance

const isOpen = ref(true)   // desktop: expanded by default
const isMobileOpen = ref(false)  // mobile overlay: closed by default

export function useSidebar() {

    function toggle() { isOpen.value = !isOpen.value }
    function toggleMobile() { isMobileOpen.value = !isMobileOpen.value }
    function closeMobile() { isMobileOpen.value = false }

    // FIX: auto-close mobile drawer when resizing to desktop
    // Without this, isMobileOpen stays true after resizing from phone → desktop,
    // causing a permanent blur on the main content.
    if (import.meta.client && !(window as any).__sidebarResizeListenerAdded) {
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                isMobileOpen.value = false
            }
        }, { passive: true })
            ; (window as any).__sidebarResizeListenerAdded = true
    }

    return { isOpen, isMobileOpen, toggle, toggleMobile, closeMobile }
}