import { fileURLToPath, URL } from 'node:url';

import react from '@vitejs/plugin-react';
import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
    ],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test-setup.ts'],
        // Agent worktrees hold full copies of the repo; never run their tests.
        exclude: [...configDefaults.exclude, '.claude/**'],
    },
});
