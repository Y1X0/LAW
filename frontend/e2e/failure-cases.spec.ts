import { expect, test, type Page } from '@playwright/test'
import { DASHBOARD, errorReply, mockApi, seedSession } from './support/mock-api'

/** تسجيل دخول عادي (النداءات مُحاكاة مسبقاً). */
async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test.describe('حالات الفشل والحواف', () => {
  test('بيانات دخول خاطئة (401) تُظهر رسالة خطأ ولا تُنشئ جلسة', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'POST auth/login': errorReply(401, 'INVALID_CREDENTIALS', 'بيانات الدخول غير صحيحة.'),
      },
    })

    await page.goto('/login')
    await page.getByLabel('البريد الإلكتروني').fill('wrong@example.com')
    await page.getByLabel('كلمة المرور').fill('bad')
    await page.getByRole('button', { name: 'دخول' }).click()

    await expect(page.getByRole('alert')).toHaveText('بيانات الدخول غير صحيحة.')
    await expect(page).toHaveURL(/\/login$/)
    // لم يُخزَّن أي توكن
    const stored = await page.evaluate(() => localStorage.getItem('law.portal.tokens'))
    expect(stored).toBeNull()
  })

  test('انتهاء الجلسة وفشل تجديد التوكن يعيد للموظف شاشة الدخول', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'GET auth/me': errorReply(401, 'UNAUTHENTICATED'),
        'GET me/dashboard': errorReply(401, 'UNAUTHENTICATED'),
        'POST auth/refresh': errorReply(401, 'UNAUTHENTICATED'),
      },
    })
    await seedSession(page) // توكن قائم لكنه منتهي

    await page.goto('/dashboard')

    // فشل التجديد → مسح التوكن → إعادة توجيه لشاشة الدخول
    await expect(page).toHaveURL(/\/login$/)
    const stored = await page.evaluate(() => localStorage.getItem('law.portal.tokens'))
    expect(stored).toBeNull()
  })

  test('تجديد التوكن الناجح بعد 401 يُكمل النداء بشفافية', async ({ page }) => {
    let meCalls = 0
    let refreshed = false
    await mockApi(page, {
      overrides: {
        // أول نداء لـ me/dashboard يفشل بـ 401، وبعد التجديد ينجح
        'GET me/dashboard': async (route) => {
          meCalls += 1
          if (meCalls === 1) {
            await errorReply(401, 'UNAUTHENTICATED')(route)
          } else {
            await route.fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify({ data: DASHBOARD, meta: null, errors: null }),
            })
          }
        },
        'POST auth/refresh': async (route) => {
          refreshed = true
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              data: { tokens: { access_token: 'new-access', refresh_token: 'new-refresh' } },
              meta: null,
              errors: null,
            }),
          })
        },
      },
    })

    await login(page)
    // اللوحة ظهرت رغم 401 الأول لأن التوكن جُدِّد ثم أُعيد النداء
    await expect(page.getByRole('heading', { name: 'لوحتي' })).toBeVisible()
    expect(refreshed).toBe(true)
    expect(meCalls).toBeGreaterThanOrEqual(2)
    // التوكن الجديد حُفِظ
    const stored = await page.evaluate(() => localStorage.getItem('law.portal.tokens'))
    expect(stored).toContain('new-access')
  })

  test('حساب غير مرتبط بموظف (403) يُظهر رسالة الموارد البشرية', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'GET me/dashboard': errorReply(403, 'NO_LINKED_EMPLOYEE'),
      },
    })

    await login(page)
    await expect(page.getByText(/حسابك غير مرتبط بسجلّ موظف/)).toBeVisible()
  })

  test('خطأ خادم (500) يُظهر حالة خطأ عامة', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'GET me/dashboard': errorReply(500, 'SERVER_ERROR', 'حدث خطأ في الخادم.'),
      },
    })

    await login(page)
    await expect(page.getByText('حدث خطأ في الخادم.')).toBeVisible()
  })

  test('قوائم فارغة تُظهر رسائل الحالة الفارغة', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'GET me/payslips': (route) =>
          route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: null, errors: null }) }),
        'GET me/attendance': (route) =>
          route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: null, errors: null }) }),
      },
    })

    await login(page)

    await page.getByRole('link', { name: 'كشوف راتبي' }).click()
    await expect(page.getByText('لا توجد كشوف رواتب معتمدة بعد.')).toBeVisible()

    await page.getByRole('link', { name: 'حضوري' }).click()
    await expect(page.getByText('لا توجد سجلّات حضور ضمن النطاق المحدّد.')).toBeVisible()
  })

  test('أخطاء التحقّق (422) عند تقديم إجازة تظهر بجانب الحقل', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'POST me/leave/requests': errorReply(422, 'VALIDATION_ERROR', undefined, {
          start_date: ['الرصيد غير كافٍ.'],
        }),
      },
    })

    await login(page)
    await page.getByRole('link', { name: 'إجازاتي' }).click()

    await page.getByLabel('نوع الإجازة').selectOption('1')
    await page.getByLabel('من').fill('2026-09-01')
    await page.getByLabel('إلى').fill('2026-09-03')
    await page.getByRole('button', { name: 'تقديم الطلب' }).click()

    await expect(page.getByText('الرصيد غير كافٍ.')).toBeVisible()
  })

  test('حماية المسارات: زيارة صفحة محميّة دون جلسة تُعيد لشاشة الدخول', async ({ page }) => {
    await mockApi(page)

    await page.goto('/profile')
    await expect(page).toHaveURL(/\/login$/)

    await page.goto('/payslips')
    await expect(page).toHaveURL(/\/login$/)
  })
})
