import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// в dev-режиме API и swagger отдаёт php -S на 8080 (см. README)
const backend = 'http://127.0.0.1:8080'

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': backend,
      '/docs': backend,
      '/openapi.yaml': backend,
    },
  },
})
