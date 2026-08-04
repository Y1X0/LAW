import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PaymentsSection } from './PaymentsSection'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const PAYMENTS = [
  { id: 5, receipt_no: 'RCP-000005', invoice_id: 1, amount: '400.00', method: 'cash', account_id: 7, payment_date: '2026-08-04T00:00:00.000000Z', reversal_of_id: null },
]
const ACCOUNTS = [{ id: 7, name: 'الصندوق الرئيسي', type: 'cash', currency: 'SAR' }]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (/\/invoices\/1\/payments$/.test(url.split('?')[0]) && method === 'POST') {
      return json({ data: { id: 9, receipt_no: 'RCP-000009', invoice_id: 1, amount: '100.00', method: 'cash', account_id: 7, reversal_of_id: null }, meta: null, errors: null }, 201)
    }
    if (/\/invoices\/1\/payments$/.test(url.split('?')[0])) return json({ data: PAYMENTS, meta: null, errors: null })
    if (url.includes('finance/accounts')) return json({ data: ACCOUNTS, meta: null, errors: null })
    if (/\/payments\/\d+\/reverse$/.test(url.split('?')[0])) return json({ data: { id: 10, receipt_no: 'RCP-000010', invoice_id: 1, amount: '-400.00', method: 'cash', account_id: 7, reversal_of_id: 5 }, meta: null, errors: null }, 201)
    return json({ data: [], meta: null, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('PaymentsSection', () => {
  it('يعرض السندات وزر التسجيل عند الصلاحية والحالة القابلة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PaymentsSection invoiceId={1} invoiceStatus="sent" canRecordPayment={true} />)
    expect(await screen.findByText('RCP-000005')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تسجيل دفعة' })).toBeInTheDocument()
  })

  it('يُخفي زر التسجيل عند غياب الصلاحية', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PaymentsSection invoiceId={1} invoiceStatus="sent" canRecordPayment={false} />)
    await screen.findByText('RCP-000005')
    expect(screen.queryByRole('button', { name: 'تسجيل دفعة' })).toBeNull()
  })

  it('يُخفي زر التسجيل لفاتورة مسدّدة (حالة غير قابلة)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PaymentsSection invoiceId={1} invoiceStatus="paid" canRecordPayment={true} />)
    await screen.findByText('RCP-000005')
    expect(screen.queryByRole('button', { name: 'تسجيل دفعة' })).toBeNull()
  })

  it('يسجّل دفعة عبر POST مع ترويسة Idempotency-Key', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PaymentsSection invoiceId={1} invoiceStatus="sent" canRecordPayment={true} />)

    await user.click(await screen.findByRole('button', { name: 'تسجيل دفعة' }))
    await user.type(await screen.findByLabelText('المبلغ *'), '100')
    await user.selectOptions(screen.getByLabelText('الحساب المستلِم *'), '7')
    await user.click(screen.getByRole('button', { name: 'تسجيل' }))

    await waitFor(() => {
      const call = fetchMock.mock.calls.find(
        (c) => (c[1]?.method ?? 'GET') === 'POST' && /\/invoices\/1\/payments$/.test(String(c[0]).split('?')[0]),
      )
      expect(call).toBeTruthy()
      expect((call![1]?.headers as Record<string, string>)['Idempotency-Key']).toBeTruthy()
    })
  })

  it('يُظهر زر العكس للسند القابل للعكس ويستدعي reverse', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PaymentsSection invoiceId={1} invoiceStatus="partial" canRecordPayment={true} />)

    await user.click(await screen.findByRole('button', { name: 'عكس' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => /\/payments\/5\/reverse$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })
})
