import { expect, test } from '@playwright/test'
import { mockApi } from './support/mock-api'

/**
 * المسار السعيد الكامل للموظف:
 * تسجيل الدخول → اللوحة → كشوف الراتب → التفاصيل → الطباعة →
 * الحضور → الإجازات → تقديم طلب → الملف → التعديل → الحفظ.
 */
test.describe('رحلة الموظف الكاملة (happy path)', () => {
  test.beforeEach(async ({ page }) => {
    await mockApi(page)
  })

  test('يدخل الموظف ويتنقّل بين كل الشاشات وينفّذ الإجراءات', async ({ page }) => {
    // 1) تسجيل الدخول
    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
    await page.getByLabel('كلمة المرور').fill('secret-password')
    await page.getByRole('button', { name: 'دخول' }).click()

    // 2) اللوحة الرئيسية
    await expect(page).toHaveURL(/\/dashboard$/)
    await expect(page.getByRole('heading', { name: 'لوحتي' })).toBeVisible()
    await expect(page.getByText('EMP-1024')).toBeVisible()
    await expect(page.getByText('مرحباً، سارة العتيبي')).toBeVisible()

    // 3) كشوف الراتب (المسيّرة النهائية فقط)
    await page.getByRole('link', { name: 'كشوف راتبي' }).click()
    await expect(page).toHaveURL(/\/payslips$/)
    await expect(page.getByText('فترة 06/2026')).toBeVisible()
    await expect(page.getByText('14,250.50 SAR').first()).toBeVisible()

    // 4) تفاصيل الكشف
    await page.getByRole('link', { name: /فترة 06\/2026/ }).click()
    await expect(page).toHaveURL(/\/payslips\/55$/)
    await expect(page.getByRole('heading', { name: 'كشف الراتب' })).toBeVisible()
    await expect(page.getByText('الراتب الأساسي')).toBeVisible()
    await expect(page.getByText('التأمينات الاجتماعية')).toBeVisible()

    // 5) الطباعة — تجلب مستند HTML جاهزاً من الباك-إند وتفتح نافذة الطباعة
    const [popup, htmlRequest] = await Promise.all([
      page.waitForEvent('popup'),
      page.waitForRequest((r) => r.url().includes('/me/payslips/55/html')),
      page.getByRole('button', { name: 'طباعة' }).click(),
    ])
    expect(htmlRequest.method()).toBe('GET')
    // التوكن يُرسَل في ترويسة المصادقة لجلب المستند
    expect(htmlRequest.headers()['authorization']).toContain('Bearer')
    await expect(popup.getByTestId('payslip-doc')).toBeVisible()
    await popup.close()

    // 6) الحضور (قراءة فقط)
    await page.getByRole('link', { name: 'حضوري' }).click()
    await expect(page).toHaveURL(/\/attendance$/)
    await expect(page.getByRole('cell', { name: '2026-07-29' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'متأخّر' })).toBeVisible()

    // 7) الإجازات — عرض الرصيد ثم تقديم طلب
    await page.getByRole('link', { name: 'إجازاتي' }).click()
    await expect(page).toHaveURL(/\/leave$/)
    await expect(page.getByText('18', { exact: true }).first()).toBeVisible()

    await page.getByLabel('نوع الإجازة').selectOption('1')
    await page.getByLabel('من').fill('2026-09-01')
    await page.getByLabel('إلى').fill('2026-09-03')
    await page.getByRole('button', { name: 'تقديم الطلب' }).click()
    await expect(page.getByText(/تم تقديم طلبك بنجاح/)).toBeVisible()

    // 8) الملف — تعديل حقل مسموح وحفظه
    await page.getByRole('link', { name: 'ملفي' }).click()
    await expect(page).toHaveURL(/\/profile$/)
    await expect(page.getByText('محامٍ أول')).toBeVisible()

    const phone = page.getByLabel('الهاتف', { exact: true })
    await expect(phone).toHaveValue('0500000000')
    await phone.fill('0555555555')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.getByText(/تم حفظ بياناتك/)).toBeVisible()

    // 9) تسجيل الخروج → العودة لشاشة الدخول
    await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
    await expect(page).toHaveURL(/\/login$/)
  })

  test('التعديل يرسل الحقول المسموحة فقط (لا حقول مالية/تنظيمية)', async ({ page }) => {
    let patchBody: Record<string, unknown> | null = null
    await mockApi(page, {
      overrides: {
        'PATCH me/profile': async (route) => {
          patchBody = route.request().postDataJSON() as Record<string, unknown>
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ data: patchBody, meta: null, errors: null }),
          })
        },
      },
    })

    // جلسة عبر تسجيل دخول عادي ثم الانتقال للملف
    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
    await page.getByLabel('كلمة المرور').fill('secret-password')
    await page.getByRole('button', { name: 'دخول' }).click()
    await page.getByRole('link', { name: 'ملفي' }).click()

    const phone = page.getByLabel('الهاتف', { exact: true })
    await expect(phone).toHaveValue('0500000000')
    await phone.fill('0555555555')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.getByText(/تم حفظ بياناتك/)).toBeVisible()

    const keys = Object.keys(patchBody ?? {}).sort()
    expect(keys).toEqual(
      ['address', 'emergency_contact_name', 'emergency_contact_phone', 'phone', 'photo_path'].sort(),
    )
    expect(patchBody).not.toHaveProperty('basic_salary')
    expect(patchBody).not.toHaveProperty('department')
    expect(patchBody).not.toHaveProperty('job_title')
    expect(patchBody).not.toHaveProperty('employee_id')
  })
})
