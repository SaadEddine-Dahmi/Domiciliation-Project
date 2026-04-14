// vitest.config.ts
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'url'

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'happy-dom',
        globals: true,
        setupFiles: ['./tests/setup.ts'],
        alias: {
            // Match Nuxt's ~ alias
            '~': fileURLToPath(new URL('./app', import.meta.url)),
        },
        coverage: {
            reporter: ['text', 'html'],
            include: ['app/stores/**', 'app/services/**', 'app/composables/**'],
        },
    },
    resolve: {
        alias: {
            '~': fileURLToPath(new URL('./app', import.meta.url)),
        },
    },
})