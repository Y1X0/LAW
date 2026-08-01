import { screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { HrDashboardPage } from './HrDashboardPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

/** غلاف قائمة مرقّمة بإجمالي محدّد (نحتاج meta.total فقط). */
function page(total: number): Response {
  return json({ data: [], meta: { page: 1, per_page: 1, total, total_pages: 1 }, errors: null })
}

/** يوجّه fetch حسب المورد؛ counts = [employees, pendingLeave, todayAbsent]. */
function stubCounts(counts: [number, number, number]) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('employees')) return page(counts[0])
    if (url.includes('leave-requests')) return page(counts[1])
    if (url.includes('attendance')) return page(counts[2])
    return json({ data: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('HrDashboardPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<HrDashboardPage />)
    expect(screen.getByTestId('hr-dashboard-skeleton')).toBeInTheDocument()
  })

  it('يعرض المؤشّرات الثلاثة عند النجاح ويستعلم بالمعاملات الصحيحة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stubCounts([35, 4, 2])

    renderWithProviders(<HrDashboardPage />)

    expect(await screen.findByText('الموظفون')).toBeInTheDocument()
    expect(screen.getByText('إجازات معلّقة')).toBeInTheDocument()
    expect(screen.getByText('غياب اليوم')).toBeInTheDocument()

    // المصادر الصحيحة (APIs موجودة فقط).
    const urls = fetchMock.mock.calls.map((c) => String(c[0]))
    expect(urls.some((u) => u.includes('employees'))).toBe(true)
    expect(urls.some((u) => u.includes('leave-requests') && u.includes('status=pending'))).toBe(true)
    expect(urls.some((u) => u.includes('attendance') && u.includes('status=absent') && u.includes('date='))).toBe(true)
  })

  it('يعرض صفراً عند غياب البيانات (empty)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubCounts([0, 0, 0])

    renderWithProviders(<HrDashboardPage />)

    expect(await screen.findByText('الموظفون')).toBeInTheDocument()
    // العدّاد للقيمة 0 يظهر «0» فوراً (لا حركة فعلية).
    await waitFor(() => expect(screen.getAllByText('0').length).toBeGreaterThanOrEqual(3))
  })

  it('يعرض حالة الخطأ عند فشل كل المصادر', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => json({ data: null, meta: null, errors: { code: 'SERVER_ERROR', message: 'خطأ' } }, 500)),
    )

    renderWithProviders(<HrDashboardPage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })
})
