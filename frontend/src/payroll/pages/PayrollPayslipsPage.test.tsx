import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollPayslipsPage } from './PayrollPayslipsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })
const RUN = { id: 10, payroll_period_id: 1, status: 'approved' }
const PAYSLIPS = [
  { payroll_item_id: 100, employee: { name: 'أحمد المصري', employee_number: 'EMP-1001' }, gross: '5000.00', deductions_total: '500.00', net: '4500.00', currency: 'SAR' },
]
const DETAIL = {
  employee: { name: 'أحمد المصري', employee_number: 'EMP-1001' },
  period: { year: 2026, month: 1 },
  currency: 'SAR',
  earnings: [{ name: 'الراتب الأساسي', amount: '5000.00' }, { name: 'بدل سكن', amount: '500.00' }],
  deductions: [{ name: 'خصم تأمين', amount: '500.00' }],
  gross: '5500.00', deductions_total: '500.00', net: '5000.00', status: 'approved',
}

function stub(payslips: unknown[] = PAYSLIPS) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('payroll-items') && url.includes('/payslip')) return ok(DETAIL)
    if (url.includes('payroll-runs') && url.includes('/payslips')) return ok(payslips)
    if (/payroll-runs\/\d+$/.test(url)) return ok(RUN)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

const opts = { route: '/payroll/runs/10/payslips', path: '/payroll/runs/:runId/payslips' }

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('PayrollPayslipsPage', () => {
  it('يعرض قائمة كشوف المسير مع الصافي', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PayrollPayslipsPage />, opts)

    expect(await screen.findByText('أحمد المصري')).toBeInTheDocument()
    expect(screen.getByText('EMP-1001')).toBeInTheDocument()
  })

  it('يفتح تفاصيل الكشف مع تفصيل الإضافات والاستقطاعات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollPayslipsPage />, opts)

    await user.click(await screen.findByRole('button', { name: 'عرض' }))
    // محتوى فريد للنافذة (لا يظهر في القائمة).
    expect(await screen.findByText('بدل سكن')).toBeInTheDocument()
    expect(screen.getByText('خصم تأمين')).toBeInTheDocument()
    expect(screen.getByText('الإضافات')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة حين لا كشوف', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub([])
    renderWithProviders(<PayrollPayslipsPage />, opts)
    expect(await screen.findByText(/لا توجد كشوف لهذا المسير/)).toBeInTheDocument()
  })
})
