import { expect, test, type Page, type Route } from '@playwright/test'
import { mockApi } from './support/mock-api'

const LEGAL_SUMMARY = {
  cases: { total: 1, open: 1, pending: 0, closed: 0 },
  tasks: { pending: 1 },
  next_hearing: null,
  recent_events: [],
  last_worklog: null,
}

const OPEN_TASK = {
  id: 5,
  title: 'إعداد مذكرة الرد',
  priority: 'high',
  due_date: '2026-08-01',
  status: 'open',
  assignee: { id: 9, full_name_ar: 'أحمد المصري' },
  case: { id: 7, internal_number: 'C-100', title: 'نزاع عقد إيجار' },
}

function jsonBody(data: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify({ data, meta: null, errors: null }) }
}
function tasksPage(items: unknown[]) {
  return {
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: items, meta: { page: 1, per_page: 15, total: items.length, total_pages: 1 }, errors: null }),
  }
}

async function loginLawyer(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
  await expect(page).toHaveURL(/\/home$/)
}

/** LP-5: تسجيل إنجاز اليوم + إكمال مهمة (بموك ذي حالة يعكس النتيجة). */
test.describe('إنجاز المحامي ومهامه', () => {
  test('يسجّل إنجاز اليوم', async ({ page }) => {
    await mockApi(page, {
      overrides: {
        'GET me/legal-summary': (route) => route.fulfill(jsonBody(LEGAL_SUMMARY)),
        'GET me/worklog': (route) => route.fulfill(jsonBody([])),
        'POST me/worklog': (route) => route.fulfill(jsonBody({ id: 1, work_date: '2026-07-31', done_today: 'مراجعة مذكرة', plan_tomorrow: null }, 201)),
      },
    })
    await loginLawyer(page)

    await page.getByRole('link', { name: 'الإنجازات اليومية' }).click()
    await expect(page).toHaveURL(/\/worklog$/)
    await expect(page.getByText('ابدأ تسجيل إنجاز اليوم')).toBeVisible()

    await page.getByLabel('ما أنجزته اليوم').fill('مراجعة مذكرة الرد وإعداد الحضور')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.getByText('تم حفظ إنجاز اليوم.')).toBeVisible()
  })

  test('يُكمل مهمة معلّقة فتختفي من القائمة', async ({ page }) => {
    let completed = false
    await mockApi(page, {
      overrides: {
        'GET me/legal-summary': (route) => route.fulfill(jsonBody(LEGAL_SUMMARY)),
        'GET tasks': (route: Route) => {
          const url = route.request().url()
          if (url.includes('status=done')) return route.fulfill(tasksPage(completed ? [{ ...OPEN_TASK, status: 'done', completed_at: '2026-07-31T00:00:00Z' }] : []))
          return route.fulfill(tasksPage(completed ? [] : [OPEN_TASK]))
        },
        'PATCH tasks/5/complete': (route) => {
          completed = true
          return route.fulfill(jsonBody({ ...OPEN_TASK, status: 'done', completed_at: '2026-07-31T00:00:00Z' }))
        },
      },
    })
    await loginLawyer(page)

    await page.getByRole('link', { name: 'المهام' }).click()
    await expect(page).toHaveURL(/\/tasks$/)
    await expect(page.getByText('إعداد مذكرة الرد')).toBeVisible()

    await page.getByRole('button', { name: 'إكمال' }).click()
    // بعد الإكمال يُعاد الجلب → المعلّقة أصبحت فارغة
    await expect(page.getByText('لا توجد مهام معلّقة.')).toBeVisible()
  })
})
