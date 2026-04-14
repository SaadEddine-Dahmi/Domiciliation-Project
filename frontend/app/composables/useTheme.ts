// app/composables/useTheme.ts
// Manages light/dark theme toggle.
// Persists preference to localStorage.
// Applies 'dark' class to <html> element.
// Default: light

const STORAGE_KEY = 'astfisc_theme'

export function useTheme() {
    // Reactive state — true = dark, false = light
    const isDark = ref(false)

    // Apply theme to <html> element
    function applyTheme(dark: boolean): void {
        if (!import.meta.client) return
        document.documentElement.classList.toggle('dark', dark)
    }

    // Toggle between light and dark
    function toggle(): void {
        isDark.value = !isDark.value
        applyTheme(isDark.value)
        if (import.meta.client) {
            localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light')
        }
    }

    // Set explicit mode
    function setDark(dark: boolean): void {
        isDark.value = dark
        applyTheme(dark)
        if (import.meta.client) {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light')
        }
    }

    // Restore saved preference on app load
    // Default is light — only go dark if explicitly saved
    function init(): void {
        if (!import.meta.client) return
        const saved = localStorage.getItem(STORAGE_KEY)
        const prefersDark = saved === 'dark'
        isDark.value = prefersDark
        applyTheme(prefersDark)
    }

    return { isDark, toggle, setDark, init }
}