import { defineConfig, devices } from '@playwright/test'

/**
 * إعداد Playwright لاختبارات E2E لبوابة الموظف.
 *
 * لا حاجة لباك-إند حيّ: كل نداءات /api/** تُعترَض وتُحاكى داخل الاختبارات
 * (انظر e2e/support/mock-api.ts). نشغّل نسخة الإنتاج عبر `vite preview`
 * لاختبار السلوك الأقرب للواقع (بناء مُصغّر + توجيه المتصفّح).
 */
const PORT = 4173
const BASE_URL = `http://127.0.0.1:${PORT}`

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    locale: 'ar',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: `npm run build && npm run preview -- --host 127.0.0.1 --port ${PORT} --strictPort`,
    url: BASE_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 180_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
})
