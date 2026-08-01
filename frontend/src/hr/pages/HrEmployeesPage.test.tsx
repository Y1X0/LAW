import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { HrEmployeesPage } from './HrEmployeesPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const EMP = {
  id: 7,
  employee_no: 'EMP-1001',
  full_name_ar: 'محمد أحمد',
  job_title: 'محامٍ',
  status: 'active',
  department: { id: 1, name: 'التقاضي' },
}

function listBody(items: unknown[], total = items.length) {
  return json({ data: items, meta: { page: 1, per_page: 15, total, total_pages: 1 }, errors: null })
}

/** يوجّه fetch: DELETE ⇒ نجاح؛ GET employees ⇒ القائمة (أو خطأ). */
function stub({ items = [EMP], listStatus = 200 }: { items?: unknown[]; listStatus?: number } = {}) {
  const fetchMock = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
    if ((init?.method ?? 'GET') === 'DELETE') return json({ data: null })
    if (listStatus !== 200) return json({ data: null, meta: null, errors: { code: 'SERVER_ERROR' } }, listStatus)
    return listBody(items)
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('HrEmployeesPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<HrEmployeesPage />)
    expect(screen.getByTestId('employees-skeleton')).toBeInTheDocument()
  })

  it('يعرض صفوف الموظفين عند النجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<HrEmployeesPage />)

    expect(await screen.findByRole('link', { name: 'محمد أحمد' })).toHaveAttribute('href', '/hr/employees/7')
    expect(screen.getByText('EMP-1001')).toBeInTheDocument()
    expect(screen.getByText('التقاضي')).toBeInTheDocument()
    expect(screen.getByText('نشط', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByText(/صفحة 1 من 1/)).toBeInTheDocument()
  })

  it('يرسل معامل البحث إلى الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<HrEmployeesPage />)
    await screen.findByText('EMP-1001')

    await user.type(screen.getByLabelText('بحث (الاسم أو الرقم الوظيفي)'), 'محمد')
    await user.click(screen.getByRole('button', { name: 'بحث' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('search=%D9%85%D8%AD%D9%85%D8%AF'))).toBe(true),
    )
  })

  it('يرسل فلتر الحالة إلى الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<HrEmployeesPage />)
    await screen.findByText('EMP-1001')

    await user.selectOptions(screen.getByLabelText('الحالة'), 'active')
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('status=active')).toString()).toBe('true'))
  })

  it('يعرض حالة فارغة عند غياب الموظفين', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ items: [] })
    renderWithProviders(<HrEmployeesPage />)
    expect(await screen.findByText('لا يوجد موظفون بعد.')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ listStatus: 500 })
    renderWithProviders(<HrEmployeesPage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })

  it('يعطّل موظفاً بعد التأكيد عبر DELETE', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<HrEmployeesPage />)
    await screen.findByText('EMP-1001')

    await user.click(screen.getByRole('button', { name: 'تعطيل' }))
    await user.click(screen.getByRole('button', { name: 'تأكيد' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'DELETE' && String(c[0]).includes('employees/7')),
      ).toBe(true),
    )
  })
})
