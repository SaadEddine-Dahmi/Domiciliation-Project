// app/composables/useTheme.ts
export type Theme = 'light' | 'gray' | 'dark'

const current = ref<Theme>('light')

export function useTheme() {

  function getStorageKey(): string {
    const config = useRuntimeConfig()
    return (config.public.themeStorageKey as string) ?? 'astfisc_theme'
  }

  function applyTheme(theme: Theme): void {
    if (!import.meta.client) return
    const html = document.documentElement
    html.classList.remove('gray', 'dark')
    if (theme === 'gray') html.classList.add('gray')
    if (theme === 'dark') html.classList.add('dark')
  }

  function setTheme(theme: Theme): void {
    current.value = theme
    applyTheme(theme)
    if (import.meta.client) {
      localStorage.setItem(getStorageKey(), theme)
    }
  }

  function toggle(): void {
    const next: Record<Theme, Theme> = { light: 'gray', gray: 'dark', dark: 'light' }
    setTheme(next[current.value])
  }

  function init(): void {
    if (!import.meta.client) return
    const saved = localStorage.getItem(getStorageKey()) as Theme | null
    const theme: Theme = saved === 'gray' || saved === 'dark' ? saved : 'light'
    current.value = theme
    applyTheme(theme)
  }

  const isDark = computed(() => current.value === 'dark')
  const isGray = computed(() => current.value === 'gray')
  const isLight = computed(() => current.value === 'light')

  return { current, isDark, isGray, isLight, setTheme, toggle, init }
}