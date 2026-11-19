import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import symfonyPlugin from 'vite-plugin-symfony'

// Host port the browser uses to reach the dev server (docker-compose maps it to 5173 in the container).
const vitePort = Number(process.env.VITE_PORT ?? 5173)

export default defineConfig({
    plugins: [vue(), tailwindcss(), symfonyPlugin()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            input: {
                app: './assets/app.js',
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: `http://localhost:${vitePort}`,
        cors: {
            origin: /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/,
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/frontend/**/*.spec.js'],
        restoreMocks: true,
    },
})
