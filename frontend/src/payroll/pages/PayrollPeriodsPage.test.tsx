import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollPeriodsPage } from './PayrollPeriodsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })
const META = { page: 1, per_page: 15, total: 2, total_pages: 1 }
const BRANCHES = [{ id: 1, name: 'المكتب الرئيسي', code: 'HQ', is_active: true, departments_count: 1 }]
const PERIODS = [
  { id: 1, year: 2026, month: 1, status: 'draft', runs_count: 0, branch: { id: 1, name: 'المكتب الرئيسي' } },
  { id: 2, year: 2025, month: 12, status: 'approved', runs_count: 2, branch: null },
]

function stub(periods: unknown[] = PERIODS) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('branches')) return ok(BRANCHES)
    if (url.includes('payroll-periods') && method === 'POST') {
      return json({ data: { id: 99, year: 2026, month: 3, status: 'draft', runs_count: 0 }, meta: null, errors: null }, 201)
    }
    if (url.includes('payroll-periods')) return ok(periods, META)
    return ok(null)
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('PayrollPeriodsPage', () => {
  it('يعرض قائمة الفترات مع الحالة والفرع', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PayrollPeriodsPage />)

    expect(await screen.findByText('يناير 2026')).toBeInTheDocument()
    expect(screen.getByText('ديسمبر 2025')).toBeInTheDocument()
    // محتوى بطاقة فريد (تسميات الحالة/الفرع تتكرّر في خيارات الفلتر).
    expect(screen.getByText('عدد المسيرات: 2')).toBeInTheDocument()
    expect(screen.getByText('عدد المسيرات: 0')).toBeInTheDocument()
  })

  it('ينشئ فترة جديدة عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollPeriodsPage />)

    await screen.findByText('يناير 2026')
    await user.click(screen.getByRole('button', { name: 'إنشاء فترة' }))

    // النموذج ظهر بالقيم الافتراضية (سنة/شهر) — نُنفّذ مباشرة.
    await user.click(await screen.findByRole('button', { name: 'إنشاء' }))

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('payroll-periods')),
      ).toBe(true)
    })
  })

  it('يعرض حالة فارغة حين لا فترات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub([])
    renderWithProviders(<PayrollPeriodsPage />)
    expect(await screen.findByText('لا توجد فترات مطابقة. أنشئ فترة جديدة للبدء.')).toBeInTheDocument()
  })
})
