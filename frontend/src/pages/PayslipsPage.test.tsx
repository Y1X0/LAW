import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '../auth/tokenStorage'
import { renderWithProviders } from '../test/renderWithProviders'
import { PayslipsPage } from './PayslipsPage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const listPayload = {
  data: [
    { payroll_item_id: 1, year: 2027, month: 3, status: 'approved', gross: 3500, deductions_total: 100, net: 3400, currency: 'SAR' },
    { payroll_item_id: 2, year: 2027, month: 2, status: 'locked', gross: 3000, deductions_total: 0, net: 3000, currency: 'SAR' },
  ],
  meta: null,
  errors: null,
}

afterEach(() => vi.restoreAllMocks())

describe('PayslipsPage', () => {
  it('يعرض هيكل التحميل', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<PayslipsPage />)
    expect(screen.getByTestId('payslips-skeleton')).toBeInTheDocument()
  })

  it('يعرض قائمة الكشوف', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(listPayload)))

    renderWithProviders(<PayslipsPage />)

    expect(await screen.findByText('فترة 03/2027')).toBeInTheDocument()
    expect(screen.getByText('3,400.00 SAR')).toBeInTheDocument()
    expect(screen.getByText('معتمد')).toBeInTheDocument()
    expect(screen.getByText('مقفل')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة عند غياب الكشوف', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [], meta: null, errors: null })))

    renderWithProviders(<PayslipsPage />)
    expect(await screen.findByText(/لا توجد كشوف/)).toBeInTheDocument()
  })

  it('يعرض حالة «حساب غير مرتبط» عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))

    renderWithProviders(<PayslipsPage />)
    expect(await screen.findByText(/غير مرتبط/)).toBeInTheDocument()
  })
})
