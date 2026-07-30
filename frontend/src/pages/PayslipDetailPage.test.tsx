import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '../auth/tokenStorage'
import { renderWithProviders } from '../test/renderWithProviders'
import { PayslipDetailPage } from './PayslipDetailPage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const detailPayload = {
  data: {
    employee: { name: 'سارة', employee_number: 'EMP-9' },
    period: { year: 2027, month: 3 },
    currency: 'SAR',
    earnings: [
      { code: 'basic', name: 'Basic Salary', amount: 3000 },
      { code: 'allowance:housing', name: 'Housing', amount: 500 },
    ],
    deductions: [{ code: 'absence', name: 'Absence', amount: 100 }],
    gross: 3500,
    deductions_total: 100,
    net: 3400,
    status: 'approved',
  },
  meta: null,
  errors: null,
}

function renderDetail() {
  return renderWithProviders(<PayslipDetailPage />, { route: '/payslips/1', path: '/payslips/:id' })
}

afterEach(() => vi.restoreAllMocks())

describe('PayslipDetailPage', () => {
  it('يعرض تفاصيل الكشف (مستحقات/خصومات/صافي)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(detailPayload)))

    renderDetail()

    expect(await screen.findByText('سارة')).toBeInTheDocument()
    expect(screen.getByText('Basic Salary')).toBeInTheDocument()
    expect(screen.getByText('Housing')).toBeInTheDocument()
    expect(screen.getByText('Absence')).toBeInTheDocument()
    // الصافي بارز
    expect(screen.getByText('3,400.00 SAR')).toBeInTheDocument()
  })

  it('يعرض حالة خطأ عند فشل الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NOT_FOUND', message: 'غير موجود' } }, 404)))

    renderDetail()
    expect(await screen.findByText('غير موجود')).toBeInTheDocument()
  })
})
