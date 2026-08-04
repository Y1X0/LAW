import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { FinanceReportsPage } from './FinanceReportsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const TRIAL = { data: { accounts: [{ account_id: 1, code: '1200', name: 'ذمم العملاء', type: 'asset', debit: '1000.00', credit: '400.00' }], total_debit: '1600.00', total_credit: '1600.00' }, meta: null, errors: null }
const RECEIVABLES = { data: { clients: [{ client_id: 2, client_name: 'شركة الأمل', invoiced: '1000.00', paid: '400.00', outstanding: '600.00' }], total_outstanding: '600.00' }, meta: null, errors: null }
const PNL = { data: { revenue: '1000.00', expenses: '200.00', net: '800.00', revenue_accounts: [{ code: '4100', name: 'إيرادات الأتعاب', amount: '1000.00' }], expense_accounts: [{ code: '5100', name: 'مصروفات عامة', amount: '200.00' }] }, meta: null, errors: null }

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('reports/trial-balance')) return json(TRIAL)
    if (url.includes('reports/receivables')) return json(RECEIVABLES)
    if (url.includes('reports/income-expense')) return json(PNL)
    return json({ data: {}, meta: null, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('FinanceReportsPage', () => {
  it('يعرض ميزان المراجعة متوازناً من الخادم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<FinanceReportsPage />)
    expect(await screen.findByText('ذمم العملاء')).toBeInTheDocument()
    expect(screen.getByText('متوازن ✓')).toBeInTheDocument()
    expect(screen.getAllByText('1,600.00 SAR').length).toBeGreaterThan(0)
  })

  it('يتنقّل إلى المستحقات ثم الأرباح/الخسائر', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<FinanceReportsPage />)
    await screen.findByText('ذمم العملاء')

    await user.click(screen.getByRole('button', { name: 'المستحقات' }))
    expect(await screen.findByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getAllByText('600.00 SAR').length).toBeGreaterThan(0) // متبقّي العميل + إجمالي المستحقات

    await user.click(screen.getByRole('button', { name: 'الأرباح والخسائر' }))
    expect(await screen.findByText('صافي الربح/الخسارة')).toBeInTheDocument()
    expect(screen.getByText('800.00 SAR')).toBeInTheDocument()
  })
})
