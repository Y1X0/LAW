import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalWorklogPage } from './LegalWorklogPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const META = { page: 1, per_page: 15, total: 1, total_pages: 1 }
const LOGS = [
  { id: 1, employee_id: 7, work_date: '2026-08-02', done_today: 'مراجعة عقد', plan_tomorrow: 'جلسة الصباح', employee: { id: 7, full_name_ar: 'سارة القحطاني' } },
]
const EMPLOYEES = [{ id: 7, employee_no: 'E-7', full_name_ar: 'سارة القحطاني', status: 'active' }]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (/\/worklog/.test(url)) return json({ data: LOGS, meta: META, errors: null })
    if (/employees/.test(url)) return json({ data: EMPLOYEES, meta: { page: 1, per_page: 10, total: 1, total_pages: 1 }, errors: null })
    return json({ data: [], meta: META, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('LegalWorklogPage', () => {
  it('يعرض سجلّ إنجاز الموظف للاطّلاع (قراءة فقط، بلا حقول تسجيل)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<LegalWorklogPage />)
    expect(await screen.findByText('سارة القحطاني')).toBeInTheDocument()
    expect(screen.getByText('مراجعة عقد')).toBeInTheDocument()
    expect(screen.getByText('جلسة الصباح')).toBeInTheDocument()
    // إشراف فقط: لا زر تسجيل/حفظ إنجاز.
    expect(screen.queryByRole('button', { name: /تسجيل|حفظ/ })).toBeNull()
  })

  it('يفلتر حسب الموظف فيمرّر employee_id للخادم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalWorklogPage />)
    await screen.findByText('مراجعة عقد')
    await user.type(screen.getByLabelText('تصفية بموظف'), 'سارة')
    await user.click(await screen.findByRole('button', { name: /سارة القحطاني/ }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => /worklog\?[^ ]*employee_id=7/.test(String(c[0])))).toBe(true)
    })
  })
})
