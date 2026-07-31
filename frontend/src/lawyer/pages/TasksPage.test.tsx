import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { TasksPage } from './TasksPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
function pageOf(items: unknown[], total = items.length) {
  return json({ data: items, meta: { page: 1, per_page: 15, total, total_pages: Math.max(1, Math.ceil(total / 15)) }, errors: null })
}

const OPEN_TASK = {
  id: 5,
  title: 'إعداد مذكرة الرد',
  priority: 'high',
  due_date: '2026-08-01',
  status: 'open',
  assignee: { id: 9, full_name_ar: 'أحمد' },
  case: { id: 7, internal_number: 'C-100', title: 'نزاع' },
}
const DONE_TASK = { ...OPEN_TASK, id: 6, title: 'تبليغ الخصم', status: 'done', completed_at: '2026-07-20T09:00:00Z' }

/** يوجّه fetch حسب status وإكمال المهمة. */
function routeFetch() {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    if (init?.method === 'PATCH') return json({ data: { ...OPEN_TASK, status: 'done', completed_at: '2026-07-31T00:00:00Z' }, meta: null, errors: null })
    if (url.includes('status=done')) return pageOf([DONE_TASK])
    return pageOf([OPEN_TASK])
  })
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('TasksPage', () => {
  it('يعرض المهام المعلّقة مع زر الإكمال', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())

    renderWithProviders(<TasksPage />)

    expect(await screen.findByText('إعداد مذكرة الرد')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'C-100' })).toHaveAttribute('href', '/cases/7')
    expect(screen.getByRole('button', { name: 'إكمال' })).toBeInTheDocument()
  })

  it('يُكمل المهمة (PATCH) ويحدّث القائمة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = routeFetch()
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<TasksPage />)
    await screen.findByText('إعداد مذكرة الرد')

    await user.click(screen.getByRole('button', { name: 'إكمال' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => c[1]?.method === 'PATCH' && String(c[0]).includes('tasks/5/complete'))).toBe(true),
    )
  })

  it('يتنقّل إلى تبويب المكتملة (status=done)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = routeFetch()
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<TasksPage />)
    await screen.findByText('إعداد مذكرة الرد')

    await user.click(screen.getByRole('tab', { name: 'المهام المكتملة' }))
    expect(await screen.findByText('تبليغ الخصم')).toBeInTheDocument()
    expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('status=done'))).toBe(true)
  })

  it('يعرض حالة فارغة للمعلّقة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(pageOf([], 0)))

    renderWithProviders(<TasksPage />)
    expect(await screen.findByText('لا توجد مهام معلّقة.')).toBeInTheDocument()
  })

  it('يعرض حالة الحرمان عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))

    renderWithProviders(<TasksPage />)
    expect(await screen.findByText(/لا تملك صلاحية الوصول/)).toBeInTheDocument()
  })
})
