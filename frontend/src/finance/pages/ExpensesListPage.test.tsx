import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { ExpensesListPage } from './ExpensesListPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const META = { page: 1, per_page: 15, total: 1, total_pages: 1 }
const EXPENSES = [
  { id: 3, voucher_no: 'EXP-000003', category_id: 1, amount: '500.00', method: 'cash', account_id: 7, expense_date: '2026-08-04T00:00:00.000000Z', reversal_of_id: null, category: { id: 1, name: 'رسوم محكمة' } },
]
const CATEGORIES = [{ id: 1, name: 'رسوم محكمة' }]

function caps(over: Record<string, boolean> = {}) {
  return { data: { can_view: true, can_create: true, can_approve: true, can_record_payment: true, can_record_expense: true, can_view_reports: true, ...over }, meta: null, errors: null }
}

function stub(capsOver: Record<string, boolean> = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('finance/capabilities')) return json(caps(capsOver))
    if (url.includes('finance/expense-categories')) return json({ data: CATEGORIES, meta: null, errors: null })
    if (url.includes('finance/accounts')) return json({ data: [{ id: 7, name: 'الصندوق', type: 'cash' }], meta: null, errors: null })
    if (/\/cases(\?|$)/.test(url)) return json({ data: [], meta: META, errors: null })
    if (/\/expenses(\?|$)/.test(url) && method === 'POST') return json({ data: { id: 9, voucher_no: 'EXP-000009', category_id: 1, amount: '250.00', method: 'cash', account_id: 7, reversal_of_id: null }, meta: null, errors: null }, 201)
    if (/\/expenses(\?|$)/.test(url)) return json({ data: EXPENSES, meta: META, errors: null })
    return json({ data: [], meta: META, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('ExpensesListPage', () => {
  it('يعرض المصروفات بالرقم والتصنيف والمبلغ', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<ExpensesListPage />)
    expect(await screen.findByText('EXP-000003')).toBeInTheDocument()
    expect(screen.getByText('500.00 SAR')).toBeInTheDocument()
  })

  it('يُخفي زر التسجيل عند غياب الصلاحية', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ can_record_expense: false })
    renderWithProviders(<ExpensesListPage />)
    await screen.findByText('EXP-000003')
    expect(screen.queryByRole('button', { name: 'تسجيل مصروف' })).toBeNull()
  })

  it('يسجّل مصروفاً عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<ExpensesListPage />)

    await user.click(await screen.findByRole('button', { name: 'تسجيل مصروف' }))
    await user.selectOptions(await screen.findByLabelText('التصنيف *'), '1')
    await user.type(screen.getByLabelText('المبلغ *'), '250')
    await user.selectOptions(screen.getByLabelText('الحساب الدافع *'), '7')
    await user.click(screen.getByRole('button', { name: 'تسجيل' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /\/expenses$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })

  it('يُظهر زر العكس ويستدعي reverse', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<ExpensesListPage />)

    await user.click(await screen.findByRole('button', { name: 'عكس' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => /\/expenses\/3\/reverse$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })
})
