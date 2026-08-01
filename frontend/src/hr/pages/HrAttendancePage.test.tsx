import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { HrAttendancePage } from './HrAttendancePage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const REC = {
  id: 3,
  work_date: '2026-07-29',
  check_in: '2026-07-29T08:00:00Z',
  check_out: '2026-07-29T17:00:00Z',
  late_minutes: 0,
  status: 'present',
  approved_at: null,
  employee: { id: 7, full_name_ar: 'محمد أحمد', employee_no: 'EMP-1001' },
}
const EMP = { id: 7, employee_no: 'EMP-1001', full_name_ar: 'محمد أحمد', job_title: 'محامٍ', status: 'active', department: { id: 1, name: 'التقاضي' } }

function listBody(items: unknown[]): Response {
  return json({ data: items, meta: { page: 1, per_page: 15, total: items.length, total_pages: 1 }, errors: null })
}

function stub({ records = [REC], listStatus = 200 }: { records?: unknown[]; listStatus?: number } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (method === 'POST') return json({ data: null }, url.includes('/manual') ? 201 : 200)
    if (url.includes('employees')) return listBody([EMP])
    if (listStatus !== 200) return json({ data: null, meta: null, errors: { code: 'SERVER_ERROR' } }, listStatus)
    return listBody(records) // attendance
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('HrAttendancePage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<HrAttendancePage />)
    expect(screen.getByTestId('attendance-skeleton')).toBeInTheDocument()
  })

  it('يعرض سجلات الحضور', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<HrAttendancePage />)
    expect(await screen.findByText('محمد أحمد')).toBeInTheDocument()
    expect(screen.getByText('حاضر', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'اعتماد' })).toBeInTheDocument()
  })

  it('يفلتر بالحالة فيستعلم بالمعامل الصحيح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrAttendancePage />)
    await screen.findByText('محمد أحمد')

    await user.selectOptions(screen.getByLabelText('الحالة'), 'late')
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('status=late'))).toBe(true))
  })

  it('يفلتر بالتاريخ فيستعلم بالمعامل الصحيح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    renderWithProviders(<HrAttendancePage />)
    await screen.findByText('محمد أحمد')

    fireEvent.change(screen.getByLabelText('التاريخ'), { target: { value: '2026-07-29' } })
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('date=2026-07-29'))).toBe(true))
  })

  it('يعتمد سجلاً فيرسل POST approve ويظهر توست', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrAttendancePage />)
    await screen.findByText('محمد أحمد')

    await user.click(screen.getByRole('button', { name: 'اعتماد' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('attendance/3/approve'))).toBe(true),
    )
    expect(await screen.findByText('تم اعتماد السجل')).toBeInTheDocument()
  })

  it('يسجّل قيداً يدوياً فيرسل POST manual', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<HrAttendancePage />)
    await screen.findByText('محمد أحمد')

    await user.click(screen.getByRole('button', { name: 'قيد يدوي' }))
    const formCard = (await screen.findByText('قيد حضور يدوي')).closest('div') as HTMLElement
    await within(formCard).findByRole('option', { name: 'محمد أحمد' })
    await user.selectOptions(within(formCard).getByLabelText('الموظف'), '7')
    fireEvent.change(within(formCard).getByLabelText('التاريخ'), { target: { value: '2026-07-29' } })
    await user.click(within(formCard).getByRole('button', { name: 'حفظ القيد' }))

    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('attendance/manual'))
      expect(call).toBeTruthy()
      const body = JSON.parse(String(call?.[1]?.body ?? '{}'))
      expect(body.employee_id).toBe(7)
      expect(body.work_date).toBe('2026-07-29')
    })
    expect(await screen.findByText('تم حفظ القيد اليدوي')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة عند غياب السجلات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ records: [] })
    renderWithProviders(<HrAttendancePage />)
    expect(await screen.findByText('لا توجد سجلات حضور.')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ listStatus: 500 })
    renderWithProviders(<HrAttendancePage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })
})
