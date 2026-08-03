import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { CaseHearingsSection } from './CaseHearingsSection'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })
const HEARINGS = [
  { id: 1, case_id: 1, scheduled_at: '2026-09-01T10:00:00', type: 'مرافعة', status: 'scheduled', location: 'محكمة الرياض' },
  { id: 2, case_id: 1, scheduled_at: '2026-08-01T09:00:00', type: 'تمهيدية', status: 'held', outcome: 'أُجّلت للمرافعة' },
]

function stub(hearings: unknown[] = HEARINGS) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (/hearings\/\d+\/cancel/.test(url) && method === 'POST') return ok({ id: 1, status: 'cancelled' })
    if (/hearings\/\d+\/postpone/.test(url) && method === 'POST') return ok({ id: 3, status: 'scheduled' })
    if (/cases\/1\/hearings/.test(url) && method === 'POST') return json({ data: { id: 3, case_id: 1, scheduled_at: '2026-10-01T10:00:00', status: 'scheduled' }, meta: null, errors: null }, 201)
    if (/cases\/1\/hearings/.test(url)) return ok(hearings)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('CaseHearingsSection', () => {
  it('يعرض الجلسات، والمنعقدة للقراءة فقط بلا أزرار إجراء', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<CaseHearingsSection caseId={1} />)

    expect(await screen.findByText('2026-09-01 10:00')).toBeInTheDocument()
    expect(screen.getByText('مجدولة')).toBeInTheDocument()
    // الجلسة المنعقدة (held): شارة + «للقراءة فقط» بلا أزرار تعديل.
    expect(screen.getByText('منعقدة')).toBeInTheDocument()
    expect(screen.getByText('للقراءة فقط (منعقدة)')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'تعديل' })).toHaveLength(1) // المجدولة فقط
  })

  it('يجدول جلسة جديدة عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseHearingsSection caseId={1} />)

    await user.click(await screen.findByRole('button', { name: 'جدولة جلسة' }))
    fireEvent.change(await screen.findByLabelText('التاريخ والوقت *'), { target: { value: '2026-10-01T10:00' } })
    await user.click(screen.getByRole('button', { name: 'جدولة' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/hearings$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })

  it('يُلغي جلسة مجدولة بتأكيد عبر POST cancel', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseHearingsSection caseId={1} />)

    await user.click(await screen.findByRole('button', { name: 'إلغاء الجلسة' }))
    await user.click(await screen.findByRole('button', { name: 'تأكيد الإلغاء' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /hearings\/1\/cancel/.test(String(c[0])))).toBe(true)
    })
  })

  it('يؤجّل جلسة عبر POST postpone', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseHearingsSection caseId={1} />)

    await user.click(await screen.findByRole('button', { name: 'تأجيل' }))
    fireEvent.change(await screen.findByLabelText('الموعد الجديد *'), { target: { value: '2026-11-01T10:00' } })
    await user.type(screen.getByLabelText('سبب التأجيل *'), 'ظرف طارئ')
    await user.click(screen.getByRole('button', { name: 'تأكيد التأجيل' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /hearings\/1\/postpone/.test(String(c[0])))).toBe(true)
    })
  })
})
