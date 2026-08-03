import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollRunsPage } from './PayrollRunsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })
const PERIOD_META = { page: 1, per_page: 100, total: 1, total_pages: 1 }
const PERIODS = [{ id: 1, year: 2026, month: 1, status: 'draft', runs_count: 1, branch: null }]

/** مسير مسوّدة بلا لقطات ولا احتساب — لاختبار الترتيب والحرّاس. */
function stub(runStatus = 'draft', attCount = 0, leaveCount = 0, itemCount = 0) {
  const runs = [{ id: 10, payroll_period_id: 1, status: runStatus }]
  const run = { id: 10, payroll_period_id: 1, status: runStatus }
  const arr = (n: number) => Array.from({ length: n }, (_, i) => ({ id: i + 1 }))
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (method === 'POST' && /\/(attendance-snapshot|leave-snapshot|calculate)/.test(url)) return ok({ payroll_run_id: 10, employees: 3 })
    if (method === 'POST' && url.includes('/approve')) return ok({ ...run, status: 'approved' })
    if (method === 'POST' && url.includes('/lock')) return ok({ ...run, status: 'locked' })
    if (method === 'POST' && /payroll-periods\/\d+\/runs/.test(url)) return json({ data: { id: 11, payroll_period_id: 1, status: 'draft' }, meta: null, errors: null }, 201)
    if (url.includes('/attendance-summaries')) return ok(arr(attCount))
    if (url.includes('/leave-summaries')) return ok(arr(leaveCount))
    if (url.includes('/items')) return ok(arr(itemCount), { total: itemCount })
    if (/payroll-runs\/\d+$/.test(url)) return ok(run)
    if (/payroll-periods\/\d+\/runs/.test(url)) return ok(runs)
    if (url.includes('payroll-periods')) return ok(PERIODS, PERIOD_META)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

async function openRun(user: ReturnType<typeof userEvent.setup>) {
  await screen.findByRole('option', { name: /يناير 2026/ })
  await user.selectOptions(screen.getByLabelText('الفترة'), '1')
  await user.click(await screen.findByRole('button', { name: 'فتح' }))
}

describe('PayrollRunsPage', () => {
  it('يفتح مسيراً ويعرض خطوات الدورة مع حرّاس الحالة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub('draft', 0, 0, 0)
    const user = userEvent.setup()
    renderWithProviders(<PayrollRunsPage />)

    await openRun(user)

    expect(await screen.findByText('لقطة الحضور')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'احتساب الرواتب' })).toBeEnabled()
    // الاعتماد معطّل قبل الاحتساب؛ القفل معطّل قبل الاعتماد (حرّاس الخادم).
    expect(screen.getByRole('button', { name: 'اعتماد' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'قفل' })).toBeDisabled()
    // اللقطة والاحتساب متاحان لمسير مسوّدة.
    expect(screen.getByRole('button', { name: 'بناء لقطة الحضور' })).toBeEnabled()
  })

  it('يحذّر عند الاحتساب دون لقطات ثم ينفّذ POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub('draft', 0, 0, 0)
    const user = userEvent.setup()
    renderWithProviders(<PayrollRunsPage />)

    await openRun(user)
    await user.click(await screen.findByRole('button', { name: 'احتساب الرواتب' }))
    // تحذير «الراتب الأساسي فقط» يعكس أن الخادم يسمح بالاحتساب بلا لقطات.
    expect(await screen.findByText(/سيُحتسب الراتب الأساسي فقط/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'احتساب' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('/calculate'))).toBe(true)
    })
  })

  it('مسير مقفل: كل أفعال الدورة معطّلة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub('locked', 3, 3, 3)
    const user = userEvent.setup()
    renderWithProviders(<PayrollRunsPage />)

    await openRun(user)
    await screen.findByText('لقطة الحضور')
    // لقطتان (حضور/إجازات) بنفس التسمية — كلتاهما معطّلتان.
    const snapshotBtns = screen.getAllByRole('button', { name: 'إعادة بناء اللقطة' })
    expect(snapshotBtns).toHaveLength(2)
    snapshotBtns.forEach((b) => expect(b).toBeDisabled())
    expect(screen.getByRole('button', { name: 'إعادة الاحتساب' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'اعتماد' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'قفل' })).toBeDisabled()
  })
})
