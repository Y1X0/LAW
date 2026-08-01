import { expect, test, type Page, type Route } from '@playwright/test'
import { mockApi } from './support/mock-api'

const AUDIT = [
  {
    id: 1,
    action: 'user_created',
    user: { id: 2, name: 'مدير المنصّة' },
    auditable_type: 'User',
    auditable_id: 5,
    ip_address: '10.0.0.1',
    created_at: '2026-08-01T10:00:00Z',
  },
]

const SETTINGS = { general: [{ id: 1, key: 'org_name_ar', value: 'مكتب الحق' }] }

const SUMMARY = {
  users: { total: 1, active: 1, suspended: 0, locked: 0, without_roles: 0 },
  employees: { total: 0, active: 0, on_leave: 0, suspended: 0, terminated: 0 },
  rbac: { roles: 1, permissions: 1 },
  legal: { cases_total: 0, cases_open: 0, cases_closed: 0, hearings_upcoming: 0 },
  hr: { leave_pending: 0 },
  activity: [],
}

function envelope(route: Route, data: unknown, meta: unknown = null) {
  return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data, meta, errors: null }) })
}

function asAdmin(page: Page) {
  return mockApi(page, {
    overrides: {
      'GET roles': (route) => envelope(route, [{ id: 1, name: 'admin', display_name: 'مدير' }]),
      'GET admin/summary': (route) => envelope(route, SUMMARY),
      'GET admin/audit': (route) => envelope(route, AUDIT, { page: 1, per_page: 20, total: 1, total_pages: 1 }),
      'GET admin/settings': (route) => envelope(route, SETTINGS),
      'PUT admin/settings': (route) => envelope(route, SETTINGS),
    },
  })
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('admin@law.test')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
  await expect(page).toHaveURL(/\/admin$/)
}

/**
 * ADMIN-5: سجلّ التدقيق والإعدادات.
 */
test.describe('وحدة التحكّم — التدقيق والإعدادات', () => {
  test('يفتح سجلّ التدقيق ويرى الأحداث', async ({ page }) => {
    await asAdmin(page)
    await login(page)

    await page.getByRole('navigation').getByRole('link', { name: 'سجلّ التدقيق' }).click()
    await expect(page).toHaveURL(/\/admin\/audit$/)
    await expect(page.getByText('إنشاء مستخدم')).toBeVisible()
    await expect(page.getByText('مدير المنصّة')).toBeVisible()
  })

  test('يفتح الإعدادات ويحفظها', async ({ page }) => {
    await asAdmin(page)
    await login(page)

    await page.getByRole('navigation').getByRole('link', { name: 'الإعدادات' }).click()
    await expect(page).toHaveURL(/\/admin\/settings$/)
    await expect(page.getByLabel('اسم المكتب (عربي)')).toHaveValue('مكتب الحق')

    const saveCall = page.waitForRequest(
      (req) => req.url().includes('/api/admin/settings') && req.method() === 'PUT',
    )
    await page.getByRole('button', { name: 'حفظ' }).click()
    await saveCall
  })
})
