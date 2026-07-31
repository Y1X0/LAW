import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { MyCasesPage } from './MyCasesPage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const CASE = {
  id: 7,
  internal_number: 'C-100',
  title: 'نزاع عقد إيجار',
  status: 'open',
  progress: 50,
  opened_date: '2026-07-01',
  client: { id: 1, name: 'شركة الأمل' },
}

function page(items: unknown[], meta: Record<string, number>) {
  return { data: items, meta, errors: null }
}

const META1 = { page: 1, per_page: 15, total: 1, total_pages: 1 }

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('MyCasesPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<MyCasesPage />)
    expect(screen.getByTestId('cases-skeleton')).toBeInTheDocument()
  })

  it('يعرض صفوف القضايا والترقيم عند النجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(page([CASE], META1))))

    renderWithProviders(<MyCasesPage />)

    expect(await screen.findByText('نزاع عقد إيجار')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'C-100' })).toHaveAttribute('href', '/cases/7')
    expect(screen.getByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getByText('مفتوحة', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByText('50%')).toBeInTheDocument()
    expect(screen.getByText(/صفحة 1 من 1/)).toBeInTheDocument()
  })

  it('يعرض حالة فارغة عند غياب القضايا', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(page([], { page: 1, per_page: 15, total: 0, total_pages: 1 }))))

    renderWithProviders(<MyCasesPage />)
    expect(await screen.findByText('لا توجد قضايا مسندة إليك.')).toBeInTheDocument()
  })

  it('يعرض حالة عدم ارتباط الحساب بموظف عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))

    renderWithProviders(<MyCasesPage />)
    expect(await screen.findByText(/حسابك غير مرتبط بسجلّ موظف/)).toBeInTheDocument()
  })

  it('يرسل معاملات البحث والفلتر إلى الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(page([CASE], META1)))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<MyCasesPage />)
    await screen.findByText('نزاع عقد إيجار')

    await user.type(screen.getByLabelText('بحث (رقم أو عنوان القضية)'), 'C-100')
    await user.click(screen.getByRole('button', { name: 'بحث' }))
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('search=C-100'))).toBe(true))

    await user.selectOptions(screen.getByLabelText('الحالة'), 'open')
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('status=open'))).toBe(true))
  })

  it('ينتقل إلى الصفحة التالية عبر الترقيم الخادمي', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(page([CASE], { page: 1, per_page: 15, total: 30, total_pages: 2 })))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<MyCasesPage />)
    await screen.findByText('نزاع عقد إيجار')

    await user.click(screen.getByRole('button', { name: 'التالي' }))
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('page=2'))).toBe(true))
  })
})
