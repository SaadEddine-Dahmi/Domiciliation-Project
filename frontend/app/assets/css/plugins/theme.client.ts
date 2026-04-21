// app/plugins/theme.client.ts
// This plugin runs BEFORE the app mounts on the client.
// It reads the saved theme from localStorage and applies the class
// to <html> synchronously — eliminates the "flash of light mode"
// that happens when useTheme().init() is called in onMounted (too late).

export default defineNuxtPlugin(() => {
  if (!import.meta.client) return

  try {
    const saved = localStorage.getItem('astfisc_theme')
    const html  = document.documentElement

    // Remove any existing theme classes first
    html.classList.remove('dark', 'gray')

    if (saved === 'dark') {
      html.classList.add('dark')
    } else if (saved === 'gray') {
      html.classList.add('gray')
    }
    // 'light' or null = no class needed (default)
  } catch {
    // localStorage not available — ignore, default theme applies
  }
})