import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  root: fileURLToPath(new URL('.', import.meta.url)),
  base: './',
  plugins: [react()],
  build: {
    outDir: '../../../assets/admin',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'veratag-admin-ui.js',
        chunkFileNames: 'veratag-admin-ui-[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.names.includes('index.css')) {
            return 'veratag-admin-ui.css'
          }

          return 'veratag-admin-ui-[name][extname]'
        },
      },
    },
  },
})
