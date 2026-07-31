import { expect, test, type Page } from '@playwright/test'
import { mockApi } from './support/mock-api'

/** ملخّص قانوني بسيط ليكتشفه الـ probe كمحامٍ (يكفي أن تكون data غير فارغة). */
const LEGAL_SUMMARY = {
  cases: { total: 4, open: 3, pending: 1, closed: 0 },
  tasks: { pending: 2 },
  next_hearing: null,
  recent_events: [],
  last_worklog: null,
}

function asLawyer(page: Page) {
  return mockApi(page, {
    overrides: {
      'GET me/legal-summary': (route) =>
        route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: LEGAL_SUMMARY, meta: null, errors: null }),
        }),
    },
  })
}

/**
 * أساس بوابة المحامي (LP-1): كشف الدور عبر probe → التوجيه إلى لوحة المحامي
 * وإظهار التنقّل الموحّد. (شاشات البيانات نفسها تأتي في LP-2..LP-5.)
 */
test.describe('أساس بوابة المحامي', () => {
  test('المحامي يدخل → يُوجَّه إلى رئيسيته ويرى التنقّل الموحّد', async ({ page }) => {
    await asLawyer(page)

    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
    await page.getByLabel('كلمة المرور').fill('secret-password')
    await page.getByRole('button', { name: 'دخول' }).click()

    // «/» وُجّه إلى رئيسية المحامي (لا لوحة الموظف)
    await expect(page).toHaveURL(/\/home$/)
    await expect(page.getByRole('heading', { name: 'الرئيسية' })).toBeVisible()

    // التنقّل الموحّد: قانوني + خدمة ذاتية
    const nav = page.getByRole('link')
    await expect(page.getByRole('link', { name: 'قضاياي' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'الإنجازات اليومية' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'الراتب' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'الملف الشخصي' })).toBeVisible()
    expect(await nav.count()).toBeGreaterThan(0)

    // فتح «قضاياي» → مسار المحامي يعمل (شاشة أساس حتى LP-3)
    await page.getByRole('link', { name: 'قضاياي' }).click()
    await expect(page).toHaveURL(/\/cases$/)
    await expect(page.getByText(/قيد الإنشاء/)).toBeVisible()
  })

  test('المحامي يفتح خدمته الذاتية (الراتب) من نفس البوابة', async ({ page }) => {
    await asLawyer(page)

    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
    await page.getByLabel('كلمة المرور').fill('secret-password')
    await page.getByRole('button', { name: 'دخول' }).click()
    await expect(page).toHaveURL(/\/home$/)

    await page.getByRole('link', { name: 'الراتب' }).click()
    await expect(page).toHaveURL(/\/payslips$/)
    await expect(page.getByText('فترة 06/2026')).toBeVisible()
  })
})
