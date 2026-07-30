import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '../auth/tokenStorage'
import { renderWithProviders } from '../test/renderWithProviders'
import { LeavePage } from './LeavePage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const balance = {
  data: { year: 2027, total_remaining: 22, by_type: [{ leave_type_id: 1, type: 'سنوية', entitled: 30, consumed: 8, remaining: 22 }] },
  meta: null, errors: null,
}
const requests = {
  data: [{ id: 5, type: 'سنوية', start_date: '2027-03-01', end_date: '2027-03-03', days: 3, status: 'pending', reason: null }],
  meta: null, errors: null,
}

/** موجّه fetch افتراضي: balance + requests. postHandler يخصّص استجابة التقديم. */
function stubFetch(postHandler?: () => Promise<Response>) {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockImplementation((url: string, init?: RequestInit) => {
      const method = init?.method ?? 'GET'
      if (url.endsWith('/leave/balance')) return Promise.resolve(jsonResponse(balance))
      if (url.endsWith('/leave/requests') && method === 'GET') return Promise.resolve(jsonResponse(requests))
      if (url.endsWith('/leave/requests') && method === 'POST') return (postHandler ?? (() => Promise.resolve(jsonResponse({ data: { id: 9, status: 'pending', days: 3 }, meta: null, errors: null }, 201))))()
      return Promise.resolve(jsonResponse({ data: null, meta: null, errors: null }))
    }),
  )
}

async function fillAndSubmit() {
  await userEvent.selectOptions(await screen.findByLabelText('نوع الإجازة'), '1')
  fireEvent.change(screen.getByLabelText('من'), { target: { value: '2027-03-01' } })
  fireEvent.change(screen.getByLabelText('إلى'), { target: { value: '2027-03-03' } })
  await userEvent.click(screen.getByRole('button', { name: 'تقديم الطلب' }))
}

afterEach(() => vi.restoreAllMocks())

describe('LeavePage', () => {
  it('يعرض الرصيد والطلبات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch()
    renderWithProviders(<LeavePage />)

    expect(await screen.findByText('22')).toBeInTheDocument() // الرصيد المتبقّي
    expect(await screen.findByText('قيد المراجعة')).toBeInTheDocument() // حالة الطلب
  })

  it('يقدّم طلباً بنجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch()
    renderWithProviders(<LeavePage />)

    await fillAndSubmit()
    expect(await screen.findByText(/تم تقديم طلبك بنجاح/)).toBeInTheDocument()
  })

  it('يعرض أخطاء التحقّق (422)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch(() =>
      Promise.resolve(jsonResponse({ data: null, meta: null, errors: { code: 'VALIDATION_ERROR', fields: { start_date: ['الرصيد غير كافٍ.'] } } }, 422)),
    )
    renderWithProviders(<LeavePage />)

    await fillAndSubmit()
    await waitFor(() => expect(screen.getByText('الرصيد غير كافٍ.')).toBeInTheDocument())
  })

  it('يعرض 403 حساب غير مرتبط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))
    renderWithProviders(<LeavePage />)
    expect(await screen.findByText(/غير مرتبط/)).toBeInTheDocument()
  })
})
