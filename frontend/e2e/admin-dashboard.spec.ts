import { expect, test, type Page, type Route } from '@playwright/test'
import { mockApi } from './support/mock-api'

const SUMMARY = {
  users: { total: 12, active: 9, suspended: 2, locked: 1, without_roles: 3 },
  employees: { total: 20, active: 18, on_leave: 1, suspended: 1, terminated: 0 },
  rbac: { roles: 7, permissions: 40 },
  legal: { cases_total: 5, cases_open: 4, cases_closed: 1, hearings_upcoming: 2 },
  hr: { leave_pending: 4 },
  activity: [{ id: 1, user_id: 1, action: 'user_created', auditable_type: 'User', created_at: '2026-08-01T10:00:00Z' }],
}

function envelope(route: Route, data: unknown) {
  return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data, meta: null, errors: null }) })
}

function asAdmin(page: Page) {
  return mockApi(page, {
    overrides: {
      'GET roles': (route) => envelope(route, [{ id: 1, name: 'admin', display_name: 'مدير' }]),
      'GET admin/summary': (route) => envelope(route, SUMMARY),
    },
  })
}

/**
 * ADMIN-4: لوحة نظام وحدة التحكّم — دخول المدير → عرض المؤشّرات والنشاط.
 */
test.describe('وحدة التحكّم — لوحة النظام', () => {
  test('المدير يدخل → يرى مؤشّرات المنصّة وآخر نشاط', async ({ page }) => {
    await asAdmin(page)

    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('admin@law.test')
    await page.getByLabel('كلمة المرور').fill('secret-password')
    await page.getByRole('button', { name: 'دخول' }).click()

    await expect(page).toHaveURL(/\/admin$/)
    await expect(page.getByText('نظرة عامة على حالة المنصّة')).toBeVisible()
    // نصوص فريدة للوحة (لا تتعارض مع تنقّل الشريط الجانبي).
    await expect(page.getByText('القضايا', { exact: true })).toBeVisible()
    await expect(page.getByText('مستخدمون بلا أدوار')).toBeVisible()
    await expect(page.getByText('إنشاء مستخدم')).toBeVisible() // من ترجمة نشاط user_created
  })
})
