import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalCaseDetailPage } from './LegalCaseDetailPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })
const CASE = {
  id: 1, internal_number: 'C-100', title: 'نزاع عقد إيجار', status: 'open', progress: 40,
  client: { id: 5, name: 'شركة الأمل' }, responsibleLawyer: { id: 9, full_name_ar: 'سارة القحطاني' },
  court_name: 'محكمة الرياض', case_type: 'تجاري', value: '50000', opened_date: '2026-01-01',
  description: 'نزاع حول بند الإيجار', assignments: [{ id: 1, role: 'lead', employee: { id: 9, full_name_ar: 'سارة القحطاني' } }],
}

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('/close') && method === 'POST') return ok({ ...CASE, status: 'closed' })
    if (url.includes('clients')) return ok([])
    if (/cases\/1$/.test(url)) return ok(CASE)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

const opts = { route: '/legal/cases/1', path: '/legal/cases/:id' }

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('LegalCaseDetailPage', () => {
  it('يعرض تفاصيل القضية والمحامي المسند', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<LegalCaseDetailPage />, opts)

    expect(await screen.findByText('نزاع عقد إيجار')).toBeInTheDocument()
    expect(screen.getByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getByText('محكمة الرياض')).toBeInTheDocument()
    // المحامي المسند بدور «رئيسي».
    expect(screen.getByText('رئيسي')).toBeInTheDocument()
  })

  it('يُغلق القضية بتأكيد عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalCaseDetailPage />, opts)

    await user.click(await screen.findByRole('button', { name: 'إغلاق القضية' }))
    await user.click(await screen.findByRole('button', { name: 'تأكيد الإغلاق' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('/close'))).toBe(true)
    })
  })
})
