import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { InvoicesListPage } from './InvoicesListPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const META = { page: 1, per_page: 15, total: 1, total_pages: 1 }
const INVOICES = [
  { id: 1, invoice_no: 'INV-000001', client_id: 2, subtotal: '250.00', tax_amount: '30.00', discount: '0.00', total: '280.00', paid_amount: '0.00', balance: '280.00', status: 'draft', client: { id: 2, name: 'شركة الأمل' } },
]

function caps(over: Record<string, boolean> = {}) {
  return { data: { can_view: true, can_create: true, can_approve: true, ...over }, meta: null, errors: null }
}

function stub(capsOver: Record<string, boolean> = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('finance/capabilities')) return json(caps(capsOver))
    if (/\/invoices(\?|$)/.test(url)) return json({ data: INVOICES, meta: META, errors: null })
    return json({ data: [], meta: META, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('InvoicesListPage', () => {
  it('يعرض الفواتير بالرقم والعميل والإجمالي', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<InvoicesListPage />)
    expect(await screen.findByText('INV-000001')).toBeInTheDocument()
    expect(screen.getByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getByText('280.00 SAR')).toBeInTheDocument()
  })

  it('يُظهر زر الإنشاء عند امتلاك صلاحية الإنشاء', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<InvoicesListPage />)
    expect(await screen.findByRole('button', { name: 'إنشاء فاتورة' })).toBeInTheDocument()
  })

  it('يُخفي زر الإنشاء عند غياب صلاحية الإنشاء', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ can_create: false })
    renderWithProviders(<InvoicesListPage />)
    await screen.findByText('INV-000001')
    expect(screen.queryByRole('button', { name: 'إنشاء فاتورة' })).toBeNull()
  })
})
