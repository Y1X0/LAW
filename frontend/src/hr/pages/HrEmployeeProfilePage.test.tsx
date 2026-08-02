import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { HrEmployeeProfilePage } from './HrEmployeeProfilePage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

const EMP = {
  id: 7,
  employee_no: 'EMP-1001',
  full_name_ar: 'محمد أحمد',
  job_title: 'محامٍ',
  status: 'active',
  hire_date: '2024-01-10',
  department: { id: 1, name: 'التقاضي' },
  branch: { id: 1, name: 'الرئيسي' },
}
const CONTRACT = { id: 1, contract_type: 'دائم', start_date: '2024-01-10', end_date: null, status: 'active', notes: null }
const ATT = { id: 1, work_date: '2026-07-29', check_in: '2026-07-29T08:00:00Z', check_out: '2026-07-29T17:00:00Z', late_minutes: 0, status: 'present' }
const BALANCE = { id: 1, year: 2026, entitled_days: 30, consumed_days: 12, remaining_days: 18, leaveType: { id: 1, name: 'سنوية' } }
const PAYROLL = { totals: { runs: 3, gross: '9000', deductions: '500', net: '8500' }, history: [{ payroll_item_id: 1, year: 2026, month: 7, status: 'paid', currency: 'SAR', gross: '3000', deductions: '200', net: '2800' }] }

function routeFetch(opts: { documents?: unknown[]; salary403?: boolean; detailStatus?: number } = {}) {
  return vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('employees/7/contracts')) return ok([CONTRACT])
    if (url.includes('employees/7/documents')) return ok(opts.documents ?? [{ id: 1, doc_type: 'certificate', title: 'شهادة تخرّج', expiry_date: null, created_at: '2024-02-01' }])
    if (url.includes('employees/7/leave-balances')) return ok([BALANCE])
    if (url.includes('leave-requests')) return ok([])
    if (url.includes('attendance')) return ok([ATT])
    if (url.includes('payroll-reports/employees/7')) return opts.salary403 ? json({ data: null, errors: { code: 'FORBIDDEN' } }, 403) : ok(PAYROLL)
    if (opts.detailStatus === 500) return json({ data: null, errors: { code: 'SERVER_ERROR' } }, 500)
    return ok(EMP)
  })
}

function renderProfile() {
  return renderWithProviders(<HrEmployeeProfilePage />, { route: '/hr/employees/7', path: '/hr/employees/:id' })
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('HrEmployeeProfilePage', () => {
  it('يحمّل رأس الموظف وتبويب المعلومات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())
    renderProfile()

    expect(await screen.findByRole('heading', { name: 'محمد أحمد' })).toBeInTheDocument()
    expect(screen.getByText('الرقم الوظيفي: EMP-1001')).toBeInTheDocument()
    expect(screen.getAllByText('التقاضي').length).toBeGreaterThan(0)
    expect(screen.getByRole('tab', { name: 'الرواتب' })).toBeInTheDocument()
  })

  it('يتنقّل إلى تبويب العقود ويعرض بياناتها', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())
    const user = userEvent.setup()
    renderProfile()
    await screen.findByRole('heading', { name: 'محمد أحمد' })

    await user.click(screen.getByRole('tab', { name: 'العقود' }))
    expect(await screen.findByText('دائم')).toBeInTheDocument()
  })

  it('يعرض سجلّ الحضور', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())
    const user = userEvent.setup()
    renderProfile()
    await screen.findByRole('heading', { name: 'محمد أحمد' })

    await user.click(screen.getByRole('tab', { name: 'الحضور' }))
    expect(await screen.findByText('حاضر')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة لتبويب بلا بيانات (الوثائق)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch({ documents: [] }))
    const user = userEvent.setup()
    renderProfile()
    await screen.findByRole('heading', { name: 'محمد أحمد' })

    await user.click(screen.getByRole('tab', { name: 'الوثائق' }))
    expect(await screen.findByText('لا توجد وثائق لهذا الموظف.')).toBeInTheDocument()
  })

  it('يعرض حالة الصلاحية عند 403 على الرواتب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch({ salary403: true }))
    const user = userEvent.setup()
    renderProfile()
    await screen.findByRole('heading', { name: 'محمد أحمد' })

    await user.click(screen.getByRole('tab', { name: 'الرواتب' }))
    expect(await screen.findByText(/لا تملك صلاحية الوصول/)).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل تحميل الموظف', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch({ detailStatus: 500 }))
    renderProfile()
    expect(await screen.findByText(/حدث خطأ/)).toBeInTheDocument()
  })

  it('يعرض حالة الحساب المرتبط (بريد + دور) داخل بوابة HR', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const withAccount = {
      ...EMP,
      has_account: true,
      account: { id: 5, email: 'user@firm.test', status: 'active', roles: [{ id: 2, name: 'hr', display_name: 'الموارد البشرية' }] },
    }
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/contracts')) return ok([])
      if (url.includes('/documents')) return ok([])
      if (url.includes('/leave-balances')) return ok([])
      if (url.includes('leave-requests')) return ok([])
      if (url.includes('attendance')) return ok([])
      if (url.includes('payroll-reports')) return ok(PAYROLL)
      return ok(withAccount)
    }))
    renderProfile()
    expect(await screen.findByText('user@firm.test')).toBeInTheDocument()
    expect(screen.getByText('الموارد البشرية')).toBeInTheDocument()
  })
})
