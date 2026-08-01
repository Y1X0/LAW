import { expect, test, type Page, type Route } from '@playwright/test'
import { mockApi } from './support/mock-api'

/** مستخدمون ثابتون لقائمة وحدة التحكّم. */
const USERS = [
  {
    id: 5,
    name: 'سارة عبدالله',
    username: 'sara',
    email: 'sara@law.test',
    status: 'active',
    roles: [{ id: 1, name: 'admin', display_name: 'مدير' }],
    employee: null,
  },
  {
    id: 6,
    name: 'خالد يوسف',
    username: 'khaled',
    email: 'khaled@law.test',
    status: 'suspended',
    roles: [],
    employee: null,
  },
]

function envelope(route: Route, data: unknown, status = 200, meta: unknown = null) {
  return route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify({ data, meta, errors: null }),
  })
}

const listMeta = { page: 1, per_page: 15, total: USERS.length, total_pages: 1 }

/** يهيّئ صفحة كـ Super Admin: probe الأدوار ينجح + نقاط إدارة المستخدمين. */
function asAdmin(page: Page) {
  return mockApi(page, {
    overrides: {
      // كشف الدور: GET /roles ينجح ⇒ isAdmin=true (غير مبذور لغير المدير).
      'GET roles': (route) => envelope(route, [{ id: 1, name: 'admin', display_name: 'مدير' }]),
      // قائمة المستخدمين.
      'GET users': (route) => envelope(route, USERS, 200, listMeta),
      // تعطيل/تفعيل.
      'POST users/6/enable': (route) => envelope(route, { ...USERS[1], status: 'active' }),
      'POST users/5/disable': (route) => envelope(route, { ...USERS[0], status: 'suspended' }),
    },
  })
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('admin@law.test')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
}

/**
 * ADMIN-2: إدارة المستخدمين لوحدة تحكّم المنصّة.
 * كشف الدور → التوجيه إلى /admin → فتح «المستخدمون» → عرض/تفعيل مستخدم.
 */
test.describe('وحدة التحكّم — إدارة المستخدمين', () => {
  test('المدير يدخل → يُوجَّه إلى وحدة التحكّم ويرى تنقّلها', async ({ page }) => {
    await asAdmin(page)
    await login(page)

    await expect(page).toHaveURL(/\/admin$/)
    await expect(page.getByRole('navigation').getByRole('link', { name: 'المستخدمون' })).toBeVisible()
    // لا يرى تنقّل المحامي.
    await expect(page.getByRole('link', { name: 'قضاياي' })).toHaveCount(0)
  })

  test('يفتح «المستخدمون» → يرى القائمة → يفعّل مستخدماً موقوفاً', async ({ page }) => {
    await asAdmin(page)
    await login(page)
    await expect(page).toHaveURL(/\/admin$/)

    await page.getByRole('navigation').getByRole('link', { name: 'المستخدمون' }).click()
    await expect(page).toHaveURL(/\/admin\/users$/)

    // القائمة ظاهرة بالبيانات.
    await expect(page.getByText('سارة عبدالله')).toBeVisible()
    await expect(page.getByText('khaled@law.test')).toBeVisible()

    // تفعيل المستخدم الموقوف (خالد) — يصدر POST enable.
    const enableCall = page.waitForRequest(
      (req) => req.url().includes('/api/users/6/enable') && req.method() === 'POST',
    )
    const khaledRow = page.getByRole('row', { name: /خالد يوسف/ })
    await khaledRow.getByRole('button', { name: 'تفعيل' }).click()
    await enableCall
  })

  test('نموذج إنشاء مستخدم يظهر ويقبل الإدخال', async ({ page }) => {
    await asAdmin(page)
    await login(page)
    await page.getByRole('navigation').getByRole('link', { name: 'المستخدمون' }).click()
    await expect(page).toHaveURL(/\/admin\/users$/)

    await page.getByRole('button', { name: 'إنشاء مستخدم' }).click()
    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByLabel('الاسم')).toBeVisible()
    await expect(dialog.getByLabel('البريد الإلكتروني')).toBeVisible()
  })
})
