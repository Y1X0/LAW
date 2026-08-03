import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalClientDetailPage } from './LegalClientDetailPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })
const CLIENT = { id: 5, name: 'شركة الأمل', type: 'company', phone: '0112223333', email: 'a@amal.sa', national_id: 'CR-1', address: 'الرياض', status: 'active', notes: 'عميل مميّز' }
const CASES = [{ id: 9, internal_number: 'C-100', title: 'نزاع تجاري', status: 'open', progress: 40 }]

function stub(client = CLIENT) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (/clients\/5\/status/.test(url) && method === 'PATCH') {
      const body = JSON.parse(String(init?.body))
      return ok({ ...client, status: body.status })
    }
    if (/cases\?/.test(url)) return json({ data: CASES, meta: { page: 1, per_page: 100, total: 1, total_pages: 1 }, errors: null })
    if (/clients\/5$/.test(url.split('?')[0])) return ok(client)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function renderDetail() {
  return renderWithProviders(<LegalClientDetailPage />, { route: '/legal/clients/5', path: '/legal/clients/:id' })
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('LegalClientDetailPage', () => {
  it('يعرض بيانات العميل وقضاياه المرتبطة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderDetail()
    expect(await screen.findByRole('heading', { name: 'شركة الأمل' })).toBeInTheDocument()
    expect(screen.getByText('a@amal.sa')).toBeInTheDocument()
    expect(await screen.findByText('نزاع تجاري')).toBeInTheDocument()
    expect(screen.getByText('C-100')).toBeInTheDocument()
  })

  it('يعطّل العميل بتأكيد يوضّح أنه ليس حذفًا، عبر PATCH status=inactive', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderDetail()
    await user.click(await screen.findByRole('button', { name: 'تعطيل' }))
    // رسالة التأكيد توضّح أنه تعطيل لا حذف.
    expect(screen.getByText(/ليس حذفًا/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'تأكيد التعطيل' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'PATCH' && /clients\/5\/status/.test(String(c[0])) && /inactive/.test(String(c[1]?.body)))).toBe(true)
    })
  })

  it('يفعّل العميل المعطّل عبر PATCH status=active', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub({ ...CLIENT, status: 'inactive' })
    const user = userEvent.setup()
    renderDetail()
    await user.click(await screen.findByRole('button', { name: 'تفعيل' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'PATCH' && /clients\/5\/status/.test(String(c[0])) && /active/.test(String(c[1]?.body)))).toBe(true)
    })
  })
})
