import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '../auth/tokenStorage'
import { renderWithProviders } from '../test/renderWithProviders'
import { DashboardPage } from './DashboardPage'

const dashboardPayload = {
  data: {
    profile: {
      name: 'أحمد', employee_number: 'EMP-1', job_title: 'محامٍ',
      branch: 'الرياض', department: 'التقاضي', manager: null,
    },
    leave_balance: { year: 2027, total_remaining: 22, by_type: [{ type: 'سنوية', remaining: 22 }] },
    attendance_today: {
      date: '2027-03-01', status: 'late', check_in: null, check_out: null,
      worked_minutes: 420, late_minutes: 15, overtime_minutes: 0,
    },
    last_payslip: { payroll_item_id: 1, year: 2027, month: 2, net: 3400, currency: 'SAR' },
  },
  meta: null,
  errors: null,
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

afterEach(() => vi.restoreAllMocks())

describe('DashboardPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<DashboardPage />)
    expect(screen.getByTestId('dashboard-skeleton')).toBeInTheDocument()
  })

  it('يعرض بطاقات اللوحة عند النجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(dashboardPayload)))

    renderWithProviders(<DashboardPage />)

    expect(await screen.findByText('أحمد')).toBeInTheDocument()
    expect(screen.getByText('محامٍ')).toBeInTheDocument()
    expect(screen.getByText('الرياض')).toBeInTheDocument()
    expect(screen.getByText('متأخّر')).toBeInTheDocument() // حالة الحضور مترجمة
    expect(screen.getByText('3,400.00 SAR')).toBeInTheDocument() // صافي الراتب
    expect(screen.getByText('22')).toBeInTheDocument() // رصيد الإجازة
  })

  it('يعرض حالة «حساب غير مرتبط» عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)),
    )

    renderWithProviders(<DashboardPage />)

    expect(await screen.findByText(/غير مرتبط/)).toBeInTheDocument()
  })

  it('يعرض حالات فارغة عند غياب حضور/راتب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const empty = {
      ...dashboardPayload,
      data: { ...dashboardPayload.data, attendance_today: null, last_payslip: null },
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(empty)))

    renderWithProviders(<DashboardPage />)

    expect(await screen.findByText(/لم يُسجَّل حضور اليوم/)).toBeInTheDocument()
    expect(screen.getByText(/لا يوجد راتب معتمد بعد/)).toBeInTheDocument()
  })
})
