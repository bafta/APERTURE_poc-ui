import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

// https://vite.dev/config/
export default defineConfig({
  plugins: [svelte()],
  build: {
    sourcemap: true,
    rollupOptions: {
      input: 'src/main.js',
      output: {
        entryFileNames: 'assets/bundle.js',
        assetFileNames: 'assets/[name][extname]',
      },
    },
  },
})
