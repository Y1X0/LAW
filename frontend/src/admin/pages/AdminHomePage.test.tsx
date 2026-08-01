import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminHomePage } from './AdminHomePage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const SUMMARY = {
  users: { total: 12, active: 9, suspended: 2, locked: 1, without_roles: 3 },
  employees: { total: 20, active: 18, on_leave: 1, suspended: 1, terminated: 0 },
  rbac: { roles: 7, permissions: 40 },
  legal: { cases_total: 5, cases_open: 4, cases_closed: 1, hearings_upcoming: 2 },
  hr: { leave_pending: 4 },
  activity: [
    { id: 1, user_id: 1, action: 'user_created', auditable_type: 'User', created_at: '2026-08-01T10:00:00Z' },
    { id: 2, user_id: 1, action: 'role_permissions_synced', auditable_type: 'Role', created_at: '2026-08-01T09:00:00Z' },
  ],
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminHomePage (لوحة النظام)', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminHomePage />)
    expect(screen.getByTestId('admin-summary-skeleton')).toBeInTheDocument()
  })

  it('يعرض المؤشّرات والتوزيعات وآخر النشاط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: SUMMARY })))
    renderWithProviders(<AdminHomePage />)

    // مؤشّرات (عدّاد متحرّك ينتهي للقيمة النهائية).
    expect(await screen.findByText('المستخدمون')).toBeInTheDocument()
    expect(screen.getByText('القضايا')).toBeInTheDocument()
    // تنبيه المستخدمين بلا أدوار.
    expect(screen.getByText('مستخدمون بلا أدوار')).toBeInTheDocument()
    // نشاط مترجَم.
    expect(await screen.findByText('إنشاء مستخدم')).toBeInTheDocument()
    expect(screen.getByText('مزامنة صلاحيات دور')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: null, errors: { code: 'SERVER_ERROR' } }, 500)))
    renderWithProviders(<AdminHomePage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })
})
