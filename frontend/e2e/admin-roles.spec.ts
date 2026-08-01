import { expect, test, type Page, type Route } from '@playwright/test'
import { mockApi } from './support/mock-api'

const ROLES = [
  {
    id: 1,
    name: 'admin',
    display_name: 'المدير العام',
    is_system: true,
    permissions: [
      { id: 10, name: 'users.manage', module: 'users' },
      { id: 11, name: 'roles.manage', module: 'roles' },
    ],
  },
  { id: 2, name: 'auditor', display_name: 'مدقّق', is_system: false, permissions: [{ id: 12, name: 'audit.view', module: 'audit' }] },
]

const PERMISSIONS = [
  { id: 10, name: 'users.manage', module: 'users', description: null },
  { id: 11, name: 'roles.manage', module: 'roles', description: null },
  { id: 12, name: 'audit.view', module: 'audit', description: null },
]

function envelope(route: Route, data: unknown, status = 200) {
  return route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify({ data, meta: null, errors: null }),
  })
}

/** يهيّئ صفحة كـ Super Admin مع نقاط الأدوار/الصلاحيات القائمة. */
function asAdmin(page: Page) {
  return mockApi(page, {
    overrides: {
      'GET roles': (route) => envelope(route, ROLES), // كشف الدور + قائمة الأدوار
      'GET permissions': (route) => envelope(route, PERMISSIONS),
      'POST roles': (route) => envelope(route, { id: 9, name: 'clerk', display_name: 'كاتب', is_system: false, permissions: [] }, 201),
      'PUT roles/2/permissions': (route) => envelope(route, { ...ROLES[1], permissions: PERMISSIONS }),
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
 * ADMIN-3: إدارة الأدوار والصلاحيات.
 * فتح «الأدوار» → عرض القائمة → تحرير مصفوفة الصلاحيات وحفظها.
 */
test.describe('وحدة التحكّم — الأدوار والصلاحيات', () => {
  test('يفتح «الأدوار» → يرى القائمة وشارة النظامي', async ({ page }) => {
    await asAdmin(page)
    await login(page)
    await expect(page).toHaveURL(/\/admin$/)

    await page.getByRole('navigation').getByRole('link', { name: 'الأدوار والصلاحيات' }).click()
    await expect(page).toHaveURL(/\/admin\/roles$/)

    await expect(page.getByText('المدير العام')).toBeVisible()
    await expect(page.getByText('مدقّق')).toBeVisible()
    await expect(page.getByText('نظامي')).toBeVisible()
  })

  test('يحرّر مصفوفة صلاحيات دور ويحفظها', async ({ page }) => {
    await asAdmin(page)
    await login(page)
    await page.getByRole('navigation').getByRole('link', { name: 'الأدوار والصلاحيات' }).click()
    await expect(page).toHaveURL(/\/admin\/roles$/)

    const auditorRow = page.getByRole('row', { name: /مدقّق/ })
    await auditorRow.getByRole('button', { name: 'الصلاحيات' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('users.manage')).toBeVisible()

    const syncCall = page.waitForRequest(
      (req) => req.url().includes('/api/roles/2/permissions') && req.method() === 'PUT',
    )
    await dialog.getByText('users.manage').click()
    await dialog.getByRole('button', { name: 'حفظ' }).click()
    await syncCall
  })

  test('نموذج إنشاء دور يظهر ويقبل الإدخال', async ({ page }) => {
    await asAdmin(page)
    await login(page)
    await page.getByRole('navigation').getByRole('link', { name: 'الأدوار والصلاحيات' }).click()
    await expect(page).toHaveURL(/\/admin\/roles$/)

    await page.getByRole('button', { name: 'إنشاء دور' }).click()
    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByLabel('الاسم الظاهر')).toBeVisible()
  })
})
