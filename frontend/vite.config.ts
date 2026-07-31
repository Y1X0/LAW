/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// أثناء التطوير: بروكسي /api إلى الباك-إند لتفادي CORS (يُضبط عبر VITE_DEV_API_TARGET).
export default defineConfig({
  plugins: [react()],
  resolve: {
    // alias المجالات: @/ → src (يجعل إعادة التنظيم/النقل بلا هشاشة مسارات نسبية).
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    proxy: {
      '/api': {
        target: process.env.VITE_DEV_API_TARGET ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  // معاينة الإنتاج (تُستخدم لاختبارات E2E) — ربط IPv4 صريح لتفادي عدم تطابق
  // localhost/::1 مع 127.0.0.1 على منصّات CI.
  preview: {
    host: '127.0.0.1',
    port: 4173,
    strictPort: true,
  },
  test: {
    // اختبارات الوحدة فقط داخل src — اختبارات E2E (e2e/**) يشغّلها Playwright.
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/core/test/setup.ts'],
    css: false,
  },
})
