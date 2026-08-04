import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { ManagementDashboardPage } from './ManagementDashboardPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

// القيم المالية تصل كسلاسل عشرية من الخادم — كما في FinancialJourneyTest (600/1000/200/800).
const SUMMARY = {
  legal: { cases_total: 2, cases_open: 1, cases_closed: 1, hearings_upcoming: 1, tasks_overdue: 1 },
  clients: { active: 1, total: 1 },
  hr: { employees_active: 1 },
  finance: { outstanding: '600.00', revenue: '1000.00', expenses: '200.00', net: '800.00', invoices_overdue: 0 },
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('ManagementDashboardPage (المؤشّرات الإدارية)', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<ManagementDashboardPage />)
    expect(screen.getByTestId('dashboard-summary-skeleton')).toBeInTheDocument()
  })

  it('يعرض المؤشّرات العددية والمالية من الخادم (بلا حساب في الواجهة)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: SUMMARY })))
    renderWithProviders(<ManagementDashboardPage />)

    // تسميات المؤشّرات العددية (العدّاد المتحرّك ينتهي للقيمة النهائية).
    expect(await screen.findByText('إجمالي القضايا')).toBeInTheDocument()
    expect(screen.getByText('العملاء النشطون')).toBeInTheDocument()
    expect(screen.getByText('الموظفون النشطون')).toBeInTheDocument()

    // القيم المالية مُنسّقة من السلاسل العشرية (SAR) — كل قيمة فريدة.
    expect(screen.getByText('1,000.00 SAR')).toBeInTheDocument()
    expect(screen.getByText('200.00 SAR')).toBeInTheDocument()
    expect(screen.getByText('800.00 SAR')).toBeInTheDocument()
    expect(screen.getByText('600.00 SAR')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: null, errors: { code: 'SERVER_ERROR' } }, 500)))
    renderWithProviders(<ManagementDashboardPage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })
})
