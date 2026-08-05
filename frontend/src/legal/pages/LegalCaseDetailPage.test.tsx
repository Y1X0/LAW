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

const EMP_META = { page: 1, per_page: 10, total: 1, total_pages: 1 }
const EMPLOYEES = [{ id: 12, employee_no: 'EMP-1002', full_name_ar: 'خالد الشهري', status: 'active' }]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('/close') && method === 'POST') return ok({ ...CASE, status: 'closed' })
    if (/cases\/1\/assign\/\d+/.test(url) && method === 'DELETE') return ok({ message: 'ok' })
    if (/cases\/1\/assign$/.test(url) && method === 'POST') return json({ data: { id: 2, role: 'support' }, meta: null, errors: null }, 201)
    if (url.includes('employees')) return json({ data: EMPLOYEES, meta: EMP_META, errors: null })
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
    // العميل يظهر كرابط سريع لصفحة تفاصيله (تنقّل قضية → عميل).
    expect(screen.getByRole('link', { name: 'شركة الأمل' })).toHaveAttribute('href', '/legal/clients/5')
    expect(screen.getByText('محكمة الرياض')).toBeInTheDocument()
    // المحامي المسند بدور «رئيسي».
    expect(screen.getByText('رئيسي')).toBeInTheDocument()
  })

  it('يعرض قسم الحقول المخصّصة بقيمها المرئية من الخادم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const withCf = {
      ...CASE,
      custom_fields: [
        { key: 'contract_number', label: 'رقم العقد', type: 'text', value: 'C-777' },
        { key: 'contract_value', label: 'قيمة العقد', type: 'currency', value: 50000 },
      ],
    }
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (/cases\/1$/.test(url)) return ok(withCf)
      if (url.includes('employees')) return json({ data: EMPLOYEES, meta: EMP_META, errors: null })
      return ok([])
    }))
    renderWithProviders(<LegalCaseDetailPage />, opts)

    expect(await screen.findByText('الحقول المخصّصة')).toBeInTheDocument()
    expect(screen.getByText('رقم العقد')).toBeInTheDocument()
    expect(screen.getByText('C-777')).toBeInTheDocument()
    expect(screen.getByText('قيمة العقد')).toBeInTheDocument()
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

  it('يُلغي إسناد محامٍ بتأكيد عبر DELETE', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalCaseDetailPage />, opts)

    await user.click(await screen.findByRole('button', { name: 'إزالة' }))
    await user.click(await screen.findByRole('button', { name: 'تأكيد' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'DELETE' && /cases\/1\/assign\/9/.test(String(c[0])))).toBe(true)
    })
  })

  it('يُسند محامياً جديداً عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalCaseDetailPage />, opts)

    await user.click(await screen.findByRole('button', { name: 'إسناد محامٍ' }))
    await user.type(await screen.findByPlaceholderText('الاسم أو الرقم الوظيفي'), 'خالد')
    await user.click(await screen.findByRole('button', { name: /خالد الشهري/ }))
    await user.click(screen.getByRole('button', { name: 'إسناد' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/assign$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })
})
