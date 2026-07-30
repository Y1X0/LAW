/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// أثناء التطوير: بروكسي /api إلى الباك-إند لتفادي CORS (يُضبط عبر VITE_DEV_API_TARGET).
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: process.env.VITE_DEV_API_TARGET ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    // اختبارات الوحدة فقط داخل src — اختبارات E2E (e2e/**) يشغّلها Playwright.
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
  },
})
