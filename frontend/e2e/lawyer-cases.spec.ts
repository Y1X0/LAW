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
      'GET cases/7': (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: CASE_DETAIL_7, meta: null, errors: null }) }),
      'GET cases/7/parties': (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: PARTIES_7, meta: null, errors: null }) }),
    },
  })
}

const CASE_DETAIL_7 = {
  id: 7,
  internal_number: 'C-100',
  court_case_number: '2026/7',
  title: 'نزاع عقد إيجار',
  case_type: 'تجاري',
  court_name: 'محكمة الرياض',
  value: '50000.00',
  status: 'open',
  progress: 40,
  opened_date: '2026-07-01',
  description: 'تفاصيل النزاع',
  client: { id: 1, name: 'شركة الأمل', phone: '0500000000', email: 'c@c.co' },
  responsibleLawyer: { id: 9, full_name_ar: 'أحمد المصري' },
  assignments: [{ id: 1, role: 'lead', employee: { id: 9, full_name_ar: 'أحمد المصري' } }],
}

const PARTIES_7 = [{ id: 1, name: 'خالد الشهري', type: 'plaintiff', phone: '0551112223', notes: null }]

async function loginLawyer(page: Page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill('sara@example.com')
  await page.getByLabel('كلمة المرور').fill('secret-password')
  await page.getByRole('button', { name: 'دخول' }).click()
  await expect(page).toHaveURL(/\/home$/)
}

/** قائمة قضايا المحامي (LP-3) + فتح ملف القضية بتبويباته (LP-4). */
test.describe('قائمة قضايا المحامي', () => {
  test('يفتح قضاياي → يرى الصفوف → يفتح ملف القضية ويتنقّل بين تبويباته', async ({ page }) => {
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

    // فتح ملف القضية (LP-4) — رأس + نظرة عامة
    await page.getByRole('link', { name: 'C-100' }).click()
    await expect(page).toHaveURL(/\/cases\/7$/)
    await expect(page.getByRole('heading', { name: 'نزاع عقد إيجار' })).toBeVisible()
    await expect(page.getByText('رقم الملف: C-100')).toBeVisible()
    await expect(page.getByText('محكمة الرياض')).toBeVisible()

    // التنقّل إلى تبويب الأطراف
    await page.getByRole('tab', { name: 'الأطراف' }).click()
    await expect(page.getByText('خالد الشهري')).toBeVisible()

    // تبويب المستندات (لا بيانات → حالة فارغة)
    await page.getByRole('tab', { name: 'المستندات' }).click()
    await expect(page.getByText(/لا توجد مستندات مفهرسة/)).toBeVisible()
  })
})
