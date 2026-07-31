import type { Page, Route } from '@playwright/test'

/**
 * محاكاة طبقة الـ API لبوابة الموظف داخل اختبارات E2E.
 *
 * تعترض كل نداءات `/api/**` وتردّ ببيانات ثابتة بالغلاف الموحّد
 * `{ data, meta, errors }` — دون أي باك-إند حيّ. يمكن تجاوز أي مسار
 * عبر `overrides` لاختبار حالات الفشل (401/403/422/500/فارغ).
 */

export const TOKENS = {
  access_token: 'e2e-access-token',
  refresh_token: 'e2e-refresh-token',
  access_expires_at: '2999-01-01T00:00:00Z',
  refresh_expires_at: '2999-01-01T00:00:00Z',
}

export const USER = {
  id: 1,
  name: 'سارة العتيبي',
  username: 'sara',
  email: 'sara@example.com',
  status: 'active',
  mfa_enabled: false,
}

export const DASHBOARD = {
  profile: {
    name: 'سارة العتيبي',
    employee_number: 'EMP-1024',
    job_title: 'محامٍ أول',
    branch: 'فرع الرياض',
    department: 'التقاضي',
    manager: 'أحمد المصري',
  },
  leave_balance: {
    year: 2026,
    total_remaining: 18,
    by_type: [{ type: 'سنوية', remaining: 18 }],
  },
  attendance_today: {
    date: '2026-07-30',
    status: 'present',
    check_in: '2026-07-30T08:02:00Z',
    check_out: null,
    worked_minutes: 0,
    late_minutes: 2,
    overtime_minutes: 0,
  },
  last_payslip: {
    payroll_item_id: 55,
    year: 2026,
    month: 6,
    net: 14250.5,
    currency: 'SAR',
  },
}

export const PAYSLIPS = [
  {
    payroll_item_id: 55,
    year: 2026,
    month: 6,
    status: 'paid',
    gross: 16000,
    deductions_total: 1749.5,
    net: 14250.5,
    currency: 'SAR',
  },
  {
    payroll_item_id: 42,
    year: 2026,
    month: 5,
    status: 'paid',
    gross: 16000,
    deductions_total: 1749.5,
    net: 14250.5,
    currency: 'SAR',
  },
]

export const PAYSLIP_DETAIL = {
  employee: { name: 'سارة العتيبي', employee_number: 'EMP-1024' },
  period: { year: 2026, month: 6 },
  currency: 'SAR',
  earnings: [
    { code: 'BASIC', name: 'الراتب الأساسي', amount: 12000 },
    { code: 'HOUSING', name: 'بدل سكن', amount: 4000 },
  ],
  deductions: [{ code: 'GOSI', name: 'التأمينات الاجتماعية', amount: 1749.5 }],
  gross: 16000,
  deductions_total: 1749.5,
  net: 14250.5,
  status: 'paid',
}

export const PAYSLIP_HTML =
  '<!doctype html><html><head><meta charset="utf-8"><title>كشف راتب</title></head>' +
  '<body><h1 data-testid="payslip-doc">كشف راتب — سارة العتيبي</h1></body></html>'

export const ATTENDANCE = [
  {
    date: '2026-07-29',
    status: 'present',
    check_in: '2026-07-29T08:00:00Z',
    check_out: '2026-07-29T17:00:00Z',
    worked_minutes: 480,
    late_minutes: 0,
    early_leave_minutes: 0,
    overtime_minutes: 30,
  },
  {
    date: '2026-07-28',
    status: 'late',
    check_in: '2026-07-28T08:20:00Z',
    check_out: '2026-07-28T17:00:00Z',
    worked_minutes: 460,
    late_minutes: 20,
    early_leave_minutes: 0,
    overtime_minutes: 0,
  },
]

export const LEAVE_BALANCE = {
  year: 2026,
  total_remaining: 18,
  by_type: [
    { leave_type_id: 1, type: 'سنوية', entitled: 30, consumed: 12, remaining: 18 },
    { leave_type_id: 2, type: 'مرضية', entitled: 15, consumed: 0, remaining: 15 },
  ],
}

export const LEAVE_REQUESTS = [
  {
    id: 7,
    type: 'سنوية',
    start_date: '2026-08-10',
    end_date: '2026-08-12',
    days: 3,
    status: 'pending',
    reason: null,
  },
]

export const PROFILE = {
  name: 'سارة العتيبي',
  employee_number: 'EMP-1024',
  job_title: 'محامٍ أول',
  branch: 'فرع الرياض',
  department: 'التقاضي',
  manager: 'أحمد المصري',
  phone: '0500000000',
  address: 'الرياض، حي الملقا',
  photo_path: null,
  emergency_contact_name: null,
  emergency_contact_phone: null,
}

type Envelope = { data?: unknown; meta?: unknown; errors?: unknown }

/** ردّ JSON بالغلاف الموحّد. */
function json(route: Route, body: Envelope, status = 200) {
  return route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify({ data: null, meta: null, errors: null, ...body }),
  })
}

/** مُعالِج مسار مخصّص: يستقبل الـ Route ويقرّر الردّ. */
export type RouteHandler = (route: Route) => Promise<unknown> | unknown

/** مفاتيح التجاوز: `METHOD pathname` (المسار بعد /api). */
export interface MockOptions {
  overrides?: Record<string, RouteHandler>
}

/**
 * يسجّل اعتراض كل نداءات /api/** بالبيانات الافتراضية،
 * مع إمكانية تجاوز مسارات بعينها عبر overrides.
 */
export async function mockApi(page: Page, options: MockOptions = {}) {
  const overrides = options.overrides ?? {}

  // تحييد طباعة المتصفّح حتى لا تحجب الاختبار في الوضع المرئي.
  await page.addInitScript(() => {
    window.print = () => {}
  })

  await page.route('**/api/**', async (route) => {
    const req = route.request()
    const method = req.method()
    const url = new URL(req.url())
    const path = url.pathname.replace(/^\/api\//, '').replace(/\/$/, '')
    const key = `${method} ${path}`

    const override = overrides[key]
    if (override) {
      await override(route)
      return
    }

    switch (key) {
      case 'POST auth/login':
        return json(route, { data: { user: USER, tokens: TOKENS } })
      case 'GET auth/me':
        return json(route, { data: { user: USER } })
      case 'POST auth/logout':
        return json(route, { data: null })
      case 'POST auth/refresh':
        return json(route, { data: { tokens: TOKENS } })
      case 'GET me/dashboard':
        return json(route, { data: DASHBOARD })
      case 'GET me/payslips':
        return json(route, { data: PAYSLIPS })
      case 'GET me/payslips/55':
      case 'GET me/payslips/42':
        return json(route, { data: PAYSLIP_DETAIL })
      case 'GET me/attendance':
        return json(route, { data: ATTENDANCE })
      case 'GET me/leave/balance':
        return json(route, { data: LEAVE_BALANCE })
      case 'GET me/leave/requests':
        return json(route, { data: LEAVE_REQUESTS })
      case 'POST me/leave/requests':
        return json(route, { data: { id: 99, status: 'pending', days: 3 } }, 201)
      case 'GET me/profile':
        return json(route, { data: PROFILE })
      case 'PATCH me/profile': {
        const patch = req.postDataJSON() as Record<string, unknown>
        return json(route, { data: { ...PROFILE, ...patch } })
      }
      default:
        if (key === 'GET me/payslips/55/html' || key === 'GET me/payslips/42/html') {
          return route.fulfill({ status: 200, contentType: 'text/html', body: PAYSLIP_HTML })
        }
        return json(route, { data: null })
    }
  })
}

/** يزرع توكنات صالحة في localStorage قبل تحميل الصفحة (جلسة قائمة). */
export async function seedSession(page: Page) {
  await page.addInitScript((tokens) => {
    localStorage.setItem('law.portal.tokens', JSON.stringify(tokens))
  }, TOKENS)
}

/** ردّ خطأ بالغلاف الموحّد — لبناء تجاوزات حالات الفشل. */
export function errorReply(status: number, code: string, message?: string, fields?: unknown): RouteHandler {
  return (route) =>
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify({ data: null, meta: null, errors: { code, message, fields } }),
    })
}
