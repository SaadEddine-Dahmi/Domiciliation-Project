// tests/setup.ts

import { vi } from 'vitest'
import { config } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { ref, computed, reactive, watch, nextTick } from 'vue'

// ── Mock Nuxt composables ──────────────────────────────────
vi.mock('#app', () => ({
  useRuntimeConfig: () => ({
    public: { apiBase: 'http://localhost:8000' },
  }),
  navigateTo: vi.fn(),
  useRoute:   vi.fn(() => ({ query: {}, params: {} })),
  useRouter:  vi.fn(() => ({ push: vi.fn(), replace: vi.fn() })),
}))

vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost:8000' },
}))
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

// ── FIX: import.meta.client must be true in tests ─────────
// The store guards saveToStorage/restoreSession with:
//   if (!import.meta.client) return
// In Vitest this defaults to false (SSR context).
// We must patch it to true so localStorage operations execute.
Object.defineProperty(import.meta, 'client', {
  get: () => true,
  configurable: true,
})

// ── localStorage mock ─────────────────────────────────────
// Real in-memory implementation — not just a stub
const localStorageStore: Record<string, string> = {}

const localStorageMock = {
  getItem:    (key: string) => localStorageStore[key] ?? null,
  setItem:    (key: string, val: string) => { localStorageStore[key] = String(val) },
  removeItem: (key: string) => { delete localStorageStore[key] },
  clear:      () => { Object.keys(localStorageStore).forEach(k => delete localStorageStore[k]) },
  key:        (i: number) => Object.keys(localStorageStore)[i] ?? null,
  get length() { return Object.keys(localStorageStore).length },
}

// Override with writable descriptor so tests can clear it
Object.defineProperty(globalThis, 'localStorage', {
  value:        localStorageMock,
  writable:     true,
  configurable: true,
})

// Vue Test Utils global config
config.global.plugins = [createPinia()]