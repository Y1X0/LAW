import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminOrgPage } from './AdminOrgPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const BRANCHES = [
  { id: 1, name: 'المكتب الرئيسي', code: 'HQ', city: 'الرياض', is_active: true, departments_count: 2 },
]
const DEPARTMENTS = [
  { id: 10, branch_id: 1, name: 'التقاضي', is_active: true, branch: { id: 1, name: 'المكتب الرئيسي' } },
]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('/branches') && method === 'POST') return json({ data: { id: 2, name: 'فرع جدة', code: 'JED', is_active: true, departments_count: 0 } }, 201)
    if (url.includes('/branches')) return json({ data: BRANCHES })
    if (url.includes('/departments')) return json({ data: DEPARTMENTS })
    return json({ data: [] })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminOrgPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminOrgPage />)
    expect(screen.getByTestId('branches-skeleton')).toBeInTheDocument()
  })

  it('يعرض الفروع والأقسام', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminOrgPage />)
    expect(await screen.findByText('HQ')).toBeInTheDocument()
    expect(await screen.findByText('التقاضي')).toBeInTheDocument()
    expect(screen.getByText('HQ')).toBeInTheDocument()
  })

  it('ينشئ فرعاً جديداً عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminOrgPage />)
    await screen.findByText('HQ')

    await user.click(screen.getByRole('button', { name: 'إضافة فرع' }))
    await user.type(screen.getByLabelText('اسم الفرع'), 'فرع جدة')
    await user.type(screen.getByLabelText('الرمز (فريد)'), 'JED')
    await user.click(screen.getByRole('button', { name: 'حفظ' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(
          (c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('branches'),
        ),
      ).toBe(true),
    )
  })

  it('يطلب فرعاً أولاً قبل السماح بإضافة قسم حين لا فروع', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: [] })))
    renderWithProviders(<AdminOrgPage />)
    expect(await screen.findByText('لا توجد فروع بعد. أضِف أول فرع للبدء.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'إضافة قسم' })).toBeDisabled()
  })
})
