import { vi } from 'vitest'
import { config } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { ref, computed, reactive, watch, nextTick } from 'vue'

// ── Mock Nuxt composables ──────────────────────────────────
vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost:8000' } }),
  navigateTo: vi.fn(),
  useRoute:   vi.fn(() => ({ query: {}, params: {} })),
  useRouter:  vi.fn(() => ({ push: vi.fn(), replace: vi.fn() })),
}))

vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: 'http://localhost:8000' } }))
vi.stubGlobal('navigateTo', vi.fn())
vi.stubGlobal('useRoute',   vi.fn(() => ({ query: {}, params: {} })))
vi.stubGlobal('useRouter',  vi.fn(() => ({ push: vi.fn() })))
vi.stubGlobal('$fetch',     vi.fn())

// Vue reactivity globals
vi.stubGlobal('ref',      ref)
vi.stubGlobal('computed', computed)
vi.stubGlobal('reactive', reactive)
vi.stubGlobal('watch',    watch)
vi.stubGlobal('nextTick', nextTick)

// ── FIX: import.meta.client = true ────────────────────────
// Every guard in stores/services does: if (!import.meta.client) return
// In Vitest this property is undefined/false by default (SSR context).
// Patching it to true lets saveToStorage(), restoreSession(),
// authHeaders() etc. actually execute.
Object.defineProperty(import.meta, 'client', {
  get: () => true,
  configurable: true,
})

// ── localStorage — real in-memory implementation ──────────
// Must be a persistent object so setItem/getItem share the same store.
// Using a plain object (not a closure) so .clear() wipes it correctly.
const _storage: Record<string, string> = {}

const localStorageMock: Storage = {
  getItem:    (key: string) => _storage[key] ?? null,
  setItem:    (key: string, val: string) => { _storage[key] = String(val) },
  removeItem: (key: string) => { delete _storage[key] },
  clear:      () => { Object.keys(_storage).forEach(k => delete _storage[k]) },
  key:        (i: number) => Object.keys(_storage)[i] ?? null,
  get length() { return Object.keys(_storage).length },
}

Object.defineProperty(globalThis, 'localStorage', {
  value:        localStorageMock,
  writable:     true,
  configurable: true,
})

// Vue Test Utils global config
config.global.plugins = [createPinia()]