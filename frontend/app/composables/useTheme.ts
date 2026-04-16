// app/composables/useTheme.ts

export type Theme = 'light' | 'gray' | 'dark'

const STORAGE_KEY = 'astfisc_theme'

// Shared reactive state across all useTheme() calls
const current = ref<Theme>('light')

export function useTheme() {

  // Apply theme class to <html>
  function applyTheme(theme: Theme): void {
    if (!import.meta.client) return
    const html = document.documentElement
    html.classList.remove('gray', 'dark')
    if (theme === 'gray') html.classList.add('gray')
    if (theme === 'dark') html.classList.add('dark')
  }

  // Set a specific theme
  function setTheme(theme: Theme): void {
    current.value = theme
    applyTheme(theme)
    if (import.meta.client) {
      localStorage.setItem(STORAGE_KEY, theme)
    }
  }

  // Cycle: light → gray → dark → light
  function toggle(): void {
    const next: Record<Theme, Theme> = {
      light: 'gray',
      gray: 'dark',
      dark: 'light',
    }
    setTheme(next[current.value])
  }

  // Restore saved preference on app load — default is light
  function init(): void {
    if (!import.meta.client) return
    const saved = localStorage.getItem(STORAGE_KEY) as Theme | null
    const theme: Theme = saved === 'gray' || saved === 'dark' ? saved : 'light'
    current.value = theme
    applyTheme(theme)
  }

  const isDark = computed(() => current.value === 'dark')
  const isGray = computed(() => current.value === 'gray')
  const isLight = computed(() => current.value === 'light')

  return { current, isDark, isGray, isLight, setTheme, toggle, init }
}