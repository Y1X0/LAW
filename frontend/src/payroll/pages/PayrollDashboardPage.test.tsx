import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollDashboardPage } from './PayrollDashboardPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })

const TOTALS = { headcount: 12, runs: 3, basic: '30000.00', allowances: '5000.00', deductions: '2000.00', gross: '35000.00', net: '33000.00' }
const PERIODS = [
  { id: 1, year: 2026, month: 7, status: 'approved', runs_count: 2, branch: { id: 1, name: 'المكتب الرئيسي' } },
  { id: 2, year: 2026, month: 6, status: 'draft', runs_count: 0, branch: null },
]

function stub(periods: unknown[] = PERIODS) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('payroll-reports/cost')) return ok({ filters: {}, totals: TOTALS, groups: [] })
    if (url.includes('payroll-periods')) return ok(periods, { total: periods.length })
    return ok(null)
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('PayrollDashboardPage', () => {
  it('يعرض مؤشّرات التكلفة وأحدث الفترات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PayrollDashboardPage />)

    // مؤشّرات مالية من تقرير التكلفة
    expect(await screen.findByText('إجمالي الرواتب')).toBeInTheDocument()
    expect(screen.getByText('إجمالي الإضافات')).toBeInTheDocument()
    expect(screen.getByText('إجمالي الخصومات')).toBeInTheDocument()
    expect(screen.getByText('صافي الرواتب')).toBeInTheDocument()
    expect(screen.getByText('الموظفون المشمولون')).toBeInTheDocument()
    expect(screen.getByText('12')).toBeInTheDocument() // headcount
    expect(screen.getByText('3')).toBeInTheDocument() // runs

    // أحدث الفترات
    expect(screen.getByText('المكتب الرئيسي')).toBeInTheDocument()
    expect(screen.getByText('معتمدة')).toBeInTheDocument()
    expect(screen.getByText('كل الفروع')).toBeInTheDocument() // branch null → fallback
  })

  it('يعرض حالة فارغة حين لا فترات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub([])
    renderWithProviders(<PayrollDashboardPage />)

    expect(await screen.findByText('لا توجد فترات رواتب بعد.')).toBeInTheDocument()
    // المؤشّرات تظهر رغم غياب الفترات
    expect(screen.getByText('إجمالي الرواتب')).toBeInTheDocument()
  })
})
