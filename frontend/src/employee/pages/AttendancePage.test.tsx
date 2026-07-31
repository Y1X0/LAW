import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AttendancePage } from './AttendancePage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const rows = {
  data: [
    { date: '2027-03-02', status: 'late', check_in: null, check_out: null, worked_minutes: 420, late_minutes: 15, early_leave_minutes: 0, overtime_minutes: 0 },
    { date: '2027-03-01', status: 'present', check_in: null, check_out: null, worked_minutes: 480, late_minutes: 0, early_leave_minutes: 0, overtime_minutes: 30 },
  ],
  meta: null,
  errors: null,
}

afterEach(() => vi.restoreAllMocks())

describe('AttendancePage', () => {
  it('يعرض هيكل التحميل', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AttendancePage />)
    expect(screen.getByTestId('attendance-skeleton')).toBeInTheDocument()
  })

  it('يعرض سجلّ الحضور', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(rows)))

    renderWithProviders(<AttendancePage />)

    expect(await screen.findByText('2027-03-02')).toBeInTheDocument()
    expect(screen.getByText('متأخّر')).toBeInTheDocument()
    expect(screen.getByText('2027-03-01')).toBeInTheDocument()
    expect(screen.getByText('حاضر')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [], meta: null, errors: null })))
    renderWithProviders(<AttendancePage />)
    expect(await screen.findByText(/لا توجد سجلّات حضور/)).toBeInTheDocument()
  })

  it('يعرض 403 حساب غير مرتبط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))
    renderWithProviders(<AttendancePage />)
    expect(await screen.findByText(/غير مرتبط/)).toBeInTheDocument()
  })

  it('يعرض خطأ عند فشل الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'SERVER', message: 'خطأ في الخادم' } }, 500)))
    renderWithProviders(<AttendancePage />)
    expect(await screen.findByText('خطأ في الخادم')).toBeInTheDocument()
  })
})
