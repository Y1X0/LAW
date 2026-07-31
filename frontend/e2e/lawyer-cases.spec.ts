import { expect, test, type Page } from '@playwright/test'
import { mockApi } from './support/mock-api'

const LEGAL_SUMMARY = {
  cases: { total: 2, open: 2, pending: 0, closed: 0 },
  tasks: { pending: 0 },
  next_hearing: null,
  recent_events: [],
  last_worklog: null,
}

const CASES_PAGE = {
  data: [
    {
      id: 7,
      internal_number: 'C-100',
      title: 'نزاع عقد إيجار',
      status: 'open',
      progress: 40,
      opened_date: '2026-07-01',
      client: { id: 1, name: 'شركة الأمل' },
    },
    {
      id: 8,
      internal_number: 'C-101',
      title: 'مطالبة مالية',
      status: 'pending',
      progress: 10,
      opened_date: '2026-06-15',
      client: { id: 2, name: 'مؤسسة النور' },
    },
  ],
  meta: { page: 1, per_page: 15, total: 2, total_pages: 1 },
  errors: null,
}

function asLawyerWithCases(page: Page) {
  return mockApi(page, {
    overrides: {
      'GET me/legal-summary': (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: LEGAL_SUMMARY, meta: null, errors: null }) }),
      'GET cases': (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(CASES_PAGE) }),
    },
  })
}

async function loginLawyer(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
  await expect(page).toHaveURL(/\/home$/)
}

/** قائمة قضايا المحامي (LP-3): عرض الصفوف + فتح ملف القضية (placeholder حتى LP-4). */
test.describe('قائمة قضايا المحامي', () => {
  test('يفتح قضاياي → يرى الصفوف → يفتح قضية', async ({ page }) => {
    await asLawyerWithCases(page)
    await loginLawyer(page)

    await page.getByRole('link', { name: 'قضاياي' }).click()
    await expect(page).toHaveURL(/\/cases$/)
    await expect(page.getByRole('heading', { name: 'قضاياي' })).toBeVisible()

    // الصفوف
    await expect(page.getByRole('link', { name: 'C-100' })).toBeVisible()
    await expect(page.getByText('نزاع عقد إيجار')).toBeVisible()
    await expect(page.getByText('شركة الأمل')).toBeVisible()
    await expect(page.getByText('مطالبة مالية')).toBeVisible()

    // البحث والفلتر موجودان
    await expect(page.getByLabel('بحث (رقم أو عنوان القضية)')).toBeVisible()
    await expect(page.getByLabel('الحالة')).toBeVisible()

    // فتح ملف القضية → placeholder حتى LP-4
    await page.getByRole('link', { name: 'C-100' }).click()
    await expect(page).toHaveURL(/\/cases\/7$/)
    await expect(page.getByRole('heading', { name: 'ملف القضية' })).toBeVisible()
    await expect(page.getByText(/قيد الإنشاء/)).toBeVisible()
  })
})
