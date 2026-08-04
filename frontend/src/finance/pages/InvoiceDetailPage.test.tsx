import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { InvoiceDetailPage } from './InvoiceDetailPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const INVOICE = {
  id: 1, invoice_no: 'INV-000001', client_id: 2, issue_date: '2026-08-04T00:00:00.000000Z', due_date: null,
  subtotal: '250.00', tax_amount: '30.00', discount: '0.00', total: '280.00', paid_amount: '0.00', balance: '280.00',
  status: 'draft', notes: null, journal_entry_id: null, client: { id: 2, name: 'شركة الأمل' },
  items: [{ id: 1, description: 'أتعاب', quantity: '2.00', unit_price: '100.00', tax_rate: '15.00', line_total: '200.00' }],
  journalEntry: null,
}

function caps(over: Record<string, boolean> = {}) {
  return { data: { can_view: true, can_create: true, can_approve: true, can_record_payment: true, can_record_expense: true, ...over }, meta: null, errors: null }
}

function stub(capsOver: Record<string, boolean> = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('finance/capabilities')) return json(caps(capsOver))
    if (/\/invoices\/1$/.test(url.split('?')[0])) return json({ data: INVOICE, meta: null, errors: null })
    return json({ data: [], meta: null, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

const AT = { route: '/finance/invoices/1', path: '/finance/invoices/:id' }

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('InvoiceDetailPage', () => {
  it('يعرض البنود والإجماليات من الخادم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<InvoiceDetailPage />, AT)
    expect(await screen.findByText('أتعاب')).toBeInTheDocument()
    expect(screen.getByText('250.00 SAR')).toBeInTheDocument() // المجموع قبل الضريبة (قيمة فريدة)
  })

  it('يُظهر زر الاعتماد لمن يملك صلاحية الاعتماد (ومسودّة)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<InvoiceDetailPage />, AT)
    expect(await screen.findByRole('button', { name: 'اعتماد' })).toBeInTheDocument()
  })

  it('يُخفي زر الاعتماد عند غياب صلاحية الاعتماد', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ can_approve: false })
    renderWithProviders(<InvoiceDetailPage />, AT)
    await screen.findByText('أتعاب')
    expect(screen.queryByRole('button', { name: 'اعتماد' })).toBeNull()
  })
})
