import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { HrLeavePage } from './HrLeavePage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const REQ = {
  id: 5,
  status: 'pending',
  days: 3,
  start_date: '2026-08-10',
  end_date: '2026-08-12',
  reason: 'سفر',
  employee: { id: 7, full_name_ar: 'محمد أحمد', employee_no: 'EMP-1001' },
  leaveType: { id: 1, name: 'سنوية' },
}

function listBody(items: unknown[]): Response {
  return json({ data: items, meta: { page: 1, per_page: 15, total: items.length, total_pages: 1 }, errors: null })
}

function stub({ pending = [REQ], listStatus = 200 }: { pending?: unknown[]; listStatus?: number } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (method === 'POST' && (url.includes('/approve') || url.includes('/reject'))) return json({ data: null })
    if (listStatus !== 200) return json({ data: null, meta: null, errors: { code: 'SERVER_ERROR' } }, listStatus)
    if (url.includes('status=approved') || url.includes('status=rejected')) return listBody([])
    return listBody(pending) // pending
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('HrLeavePage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<HrLeavePage />)
    expect(screen.getByTestId('leave-skeleton')).toBeInTheDocument()
  })

  it('يعرض طلبات الإجازات المعلّقة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<HrLeavePage />)

    expect(await screen.findByText('محمد أحمد')).toBeInTheDocument()
    expect(screen.getByText('سنوية')).toBeInTheDocument()
    expect(screen.getByText('سفر')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'موافقة' })).toBeInTheDocument()
  })

  it('يبدّل إلى تبويب المقبولة فيستعلم بالحالة الصحيحة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrLeavePage />)
    await screen.findByText('محمد أحمد')

    await user.click(screen.getByRole('tab', { name: 'المقبولة' }))
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('status=approved'))).toBe(true))
  })

  it('يوافق على طلب فيرسل POST approve ويظهر توست نجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrLeavePage />)
    await screen.findByText('محمد أحمد')

    await user.click(screen.getByRole('button', { name: 'موافقة' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('leave-requests/5/approve'))).toBe(true),
    )
    expect(await screen.findByText('تمت الموافقة على الطلب')).toBeInTheDocument()
  })

  it('يرفض طلباً بسبب فيرسل POST reject مع السبب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrLeavePage />)
    await screen.findByText('محمد أحمد')

    await user.click(screen.getByRole('button', { name: 'رفض' }))
    await user.type(screen.getByLabelText('سبب الرفض'), 'الرصيد غير كافٍ')
    await user.click(screen.getByRole('button', { name: 'تأكيد الرفض' }))

    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('leave-requests/5/reject'))
      expect(call).toBeTruthy()
      expect(JSON.parse(String(call?.[1]?.body ?? '{}')).reason).toBe('الرصيد غير كافٍ')
    })
    expect(await screen.findByText('تم رفض الطلب')).toBeInTheDocument()
  })

  it('يصفّي بالاسم على الصفحة المحمّلة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<HrLeavePage />)
    await screen.findByText('محمد أحمد')

    await user.type(screen.getByLabelText('تصفية بالاسم'), 'خالد')
    expect(await screen.findByText('لا توجد نتائج مطابقة.')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة عند غياب الطلبات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ pending: [] })
    renderWithProviders(<HrLeavePage />)
    expect(await screen.findByText('لا توجد طلبات في هذه الحالة.')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ listStatus: 500 })
    renderWithProviders(<HrLeavePage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })
})
