import { fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { NotificationsBell } from './NotificationsBell'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const ITEMS = [
  { id: 2, type: 'task_assigned', title: 'أُسندت إليك مهمة', body: 'إعداد مذكرة', related_type: 'CaseTask', related_id: 9, read_at: null, created_at: '2026-08-04T10:00:00Z' },
  { id: 1, type: 'leave_approved', title: 'اعتُمد طلب إجازتك', body: null, related_type: 'LeaveRequest', related_id: 3, read_at: '2026-08-03T09:00:00Z', created_at: '2026-08-03T08:00:00Z' },
]

interface Handlers {
  unread?: () => Response | Promise<Response>
  list?: (url: string) => Response | Promise<Response>
  patch?: (url: string) => Response | Promise<Response>
}

function stubFetch(h: Handlers = {}): ReturnType<typeof vi.fn> {
  const fn = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (method === 'PATCH') return (h.patch ?? (() => json({ data: { marked: 2 } })))(url)
    if (url.includes('notifications/unread-count')) return (h.unread ?? (() => json({ data: { unread: 3 } })))()
    if (url.includes('notifications')) {
      return (h.list ?? (() => json({ data: ITEMS, meta: { page: 1, per_page: 20, total: 2, total_pages: 1, unread: 1 } })))(url)
    }
    return json({ data: null }, 404)
  })
  vi.stubGlobal('fetch', fn)
  return fn
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

function ready(): void {
  tokenStorage.set({ access_token: 't', refresh_token: 'r' })
}

describe('NotificationsBell (مركز الإشعارات)', () => {
  it('يعرض شارة غير المقروء من الخادم (لا يحسبها)', async () => {
    ready()
    stubFetch({ unread: () => json({ data: { unread: 3 } }) })
    renderWithProviders(<NotificationsBell />)
    expect(await screen.findByText('3')).toBeInTheDocument()
  })

  it('يفتح اللوحة ويعرض قائمة الإشعارات', async () => {
    ready()
    stubFetch()
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByText('أُسندت إليك مهمة')).toBeInTheDocument()
    expect(screen.getByText('اعتُمد طلب إجازتك')).toBeInTheDocument()
  })

  it('يعرض هيكل التحميل أثناء جلب القائمة', async () => {
    ready()
    stubFetch({ list: () => new Promise<Response>(() => {}) })
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByTestId('notifications-skeleton')).toBeInTheDocument()
  })

  it('يعرض الحالة الفارغة حين لا إشعارات', async () => {
    ready()
    stubFetch({
      unread: () => json({ data: { unread: 0 } }),
      list: () => json({ data: [], meta: { page: 1, per_page: 20, total: 0, total_pages: 1, unread: 0 } }),
    })
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByText('لا إشعارات بعد.')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل جلب القائمة', async () => {
    ready()
    stubFetch({ list: () => json({ data: null, errors: { code: 'SERVER_ERROR' } }, 500) })
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })

  it('«تعليم الكل كمقروء» يستدعي نقطة read-all', async () => {
    ready()
    const fetchMock = stubFetch()
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    // ننتظر تحميل القائمة (unread=1) كي يُفعَّل زر «تعليم الكل».
    await screen.findByText('أُسندت إليك مهمة')
    fireEvent.click(screen.getByText('تعليم الكل كمقروء'))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(([u, init]) => String(u).includes('notifications/read-all') && init?.method === 'PATCH'),
      ).toBe(true),
    )
  })

  it('فلتر «غير المقروءة» يعيد الجلب بـ unread=1', async () => {
    ready()
    const fetchMock = stubFetch()
    renderWithProviders(<NotificationsBell />)
    fireEvent.click(await screen.findByRole('button', { name: /الإشعارات/ }))
    await screen.findByText('أُسندت إليك مهمة')
    fireEvent.click(screen.getByRole('tab', { name: 'غير المقروءة' }))

    await waitFor(() =>
      expect(fetchMock.mock.calls.some(([u]) => String(u).includes('unread=1'))).toBe(true),
    )
  })
})
