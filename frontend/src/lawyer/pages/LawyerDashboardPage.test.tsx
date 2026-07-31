import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LawyerDashboardPage } from './LawyerDashboardPage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const FULL = {
  data: {
    cases: { total: 4, open: 2, pending: 1, closed: 1 },
    tasks: { pending: 3 },
    next_hearing: {
      id: 9,
      scheduled_at: '2026-08-05T09:30:00Z',
      type: 'مرافعة',
      status: 'scheduled',
      location: 'قاعة 3',
      case: { id: 100, internal_number: 'C-100', title: 'نزاع عقد إيجار' },
    },
    recent_events: [
      { id: 1, case_id: 100, title: 'إيداع لائحة الدعوى', event_date: '2026-07-20' },
      { id: 2, case_id: 100, title: 'تبليغ الخصم', event_date: '2026-07-18' },
    ],
    last_worklog: { id: 5, work_date: '2026-07-30', done_today: 'مراجعة مذكرة الرد' },
  },
  meta: null,
  errors: null,
}

const EMPTY = {
  data: {
    cases: { total: 0, open: 0, pending: 0, closed: 0 },
    tasks: { pending: 0 },
    next_hearing: null,
    recent_events: [],
    last_worklog: null,
  },
  meta: null,
  errors: null,
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('LawyerDashboardPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<LawyerDashboardPage />)
    expect(screen.getByTestId('lawyer-dashboard-skeleton')).toBeInTheDocument()
  })

  it('يعرض البطاقات وأقرب جلسة والنشاط والإنجاز عند النجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(FULL)))

    renderWithProviders(<LawyerDashboardPage />)

    // البطاقات
    expect(await screen.findByText('إجمالي القضايا')).toBeInTheDocument()
    expect(screen.getByText('القضايا المفتوحة')).toBeInTheDocument()
    expect(screen.getByText('المهام المعلّقة')).toBeInTheDocument()
    // أقرب جلسة
    expect(screen.getByText('C-100')).toBeInTheDocument()
    expect(screen.getByText('نزاع عقد إيجار')).toBeInTheDocument()
    // النشاط الأخير
    expect(screen.getByText('إيداع لائحة الدعوى')).toBeInTheDocument()
    // الإنجاز اليومي + رابط
    expect(screen.getByText('مراجعة مذكرة الرد')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'الانتقال للإنجازات' })).toHaveAttribute('href', '/worklog')
  })

  it('يعرض رسائل الحالة الفارغة عند غياب البيانات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(EMPTY)))

    renderWithProviders(<LawyerDashboardPage />)

    expect(await screen.findByText('لا توجد قضايا مسندة إليك بعد.')).toBeInTheDocument()
    expect(screen.getByText('لا توجد جلسات قادمة.')).toBeInTheDocument()
    expect(screen.getByText('لا يوجد نشاط حديث.')).toBeInTheDocument()
    expect(screen.getByText('لم تسجّل إنجازاً بعد.')).toBeInTheDocument()
  })

  it('يعرض حالة عدم ارتباط الحساب بموظف عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)),
    )

    renderWithProviders(<LawyerDashboardPage />)

    expect(await screen.findByText(/حسابك غير مرتبط بسجلّ موظف/)).toBeInTheDocument()
  })
})
