import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollReportsPage } from './PayrollReportsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })
const BRANCHES = [{ id: 1, name: 'المكتب الرئيسي', code: 'HQ', is_active: true, departments_count: 1 }]
const COST = {
  totals: { headcount: 12, runs: 3, basic: '30000', allowances: '5000', deductions: '2000', gross: '35000', net: '33000' },
  groups: [{ key: 1, label: 'المكتب الرئيسي', headcount: 12, runs: 3, basic: '30000', allowances: '5000', deductions: '2000', gross: '35000', net: '33000' }],
}
const EMP_META = { page: 1, per_page: 10, total: 1, total_pages: 1 }
const EMPLOYEES = [{ id: 50, employee_no: 'EMP-1001', full_name_ar: 'أحمد المصري', status: 'active' }]
const EMP_REPORT = {
  employee: { id: 50, name: 'أحمد المصري', employee_number: 'EMP-1001' },
  totals: { runs: 2, gross: '10000', deductions: '1000', net: '9000' },
  history: [{ payroll_item_id: 100, year: 2026, month: 1, status: 'approved', currency: 'SAR', gross: '5000', deductions: '500', net: '4500' }],
}

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('payroll-reports/cost')) return ok(COST)
    if (url.includes('payroll-reports/employees/')) return ok(EMP_REPORT)
    if (url.includes('branches')) return ok(BRANCHES)
    if (url.includes('employees')) return ok(EMPLOYEES, EMP_META)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('PayrollReportsPage', () => {
  it('يعرض تقرير التكلفة مع الإجماليات والتجميع', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PayrollReportsPage />)

    expect(await screen.findByText('إجمالي الرواتب')).toBeInTheDocument()
    expect(screen.getByText('الموظفون')).toBeInTheDocument()
    // صفّ التجميع يعرض عدد موظفي المجموعة (نصّ فريد لا يتكرّر في خيارات الفلتر).
    expect(screen.getByText('الموظفون: 12')).toBeInTheDocument()
  })

  it('تقرير الموظف: بحث ثم عرض الإجماليات والسجلّ', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollReportsPage />)

    await user.click(screen.getByRole('tab', { name: 'تقرير موظف' }))
    await user.type(await screen.findByPlaceholderText('الاسم أو الرقم الوظيفي'), 'أحمد')
    await user.click(await screen.findByRole('button', { name: /أحمد المصري/ }))

    // إجماليات الموظف + سجلّ شهري.
    expect(await screen.findByText('يناير 2026')).toBeInTheDocument()
    expect(screen.getByText('المسيرات')).toBeInTheDocument()
  })
})
