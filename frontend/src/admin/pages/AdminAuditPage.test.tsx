import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminAuditPage } from './AdminAuditPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const ENTRY = {
  id: 1,
  action: 'user_created',
  user: { id: 2, name: 'مدير المنصّة' },
  auditable_type: 'User',
  auditable_id: 5,
  ip_address: '10.0.0.1',
  created_at: '2026-08-01T10:00:00Z',
}

function listBody(items: unknown[]) {
  return json({ data: items, meta: { page: 1, per_page: 20, total: items.length, total_pages: 1 }, errors: null })
}

function stub({ items = [ENTRY], listStatus = 200 }: { items?: unknown[]; listStatus?: number } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    void input // مُستخدَم في تأكيدات معامل التصفية.
    if (listStatus !== 200) return json({ data: null, meta: null, errors: { code: 'SERVER_ERROR' } }, listStatus)
    return listBody(items)
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminAuditPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminAuditPage />)
    expect(screen.getByTestId('audit-skeleton')).toBeInTheDocument()
  })

  it('يعرض أحداث التدقيق بترجمة الإجراء', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminAuditPage />)
    expect(await screen.findByText('إنشاء مستخدم')).toBeInTheDocument()
    expect(screen.getByText('مدير المنصّة')).toBeInTheDocument()
  })

  it('يرسل معامل تصفية الإجراء', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminAuditPage />)
    await screen.findByText('إنشاء مستخدم')

    await user.type(screen.getByLabelText('تصفية بالإجراء'), 'login')
    await user.click(screen.getByRole('button', { name: 'تصفية' }))
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('action=login'))).toBe(true))
  })

  it('يعرض حالة فارغة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ items: [] })
    renderWithProviders(<AdminAuditPage />)
    expect(await screen.findByText('لا يوجد نشاط بعد.')).toBeInTheDocument()
  })
})
