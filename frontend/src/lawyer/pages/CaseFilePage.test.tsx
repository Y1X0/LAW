import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { CaseFilePage } from './CaseFilePage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

const CASE = {
  id: 7,
  internal_number: 'C-500',
  court_case_number: '2026/33',
  title: 'قضية تجارية',
  case_type: 'تجاري',
  court_name: 'محكمة الرياض',
  value: '50000.00',
  status: 'open',
  progress: 30,
  opened_date: '2026-05-01',
  description: 'وصف القضية',
  client: { id: 1, name: 'شركة الأمل', phone: '0500000000', email: 'c@c.co' },
  responsibleLawyer: { id: 9, full_name_ar: 'أحمد المصري' },
  assignments: [{ id: 1, role: 'lead', employee: { id: 9, full_name_ar: 'أحمد المصري' } }],
}

/** يوجّه fetch حسب مورد القضية (المسارات الفرعية قبل الأساس). */
function routeFetch(overrides: { parties?: unknown[]; caseStatus?: number } = {}) {
  return vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/parties')) return ok(overrides.parties ?? [{ id: 1, name: 'خالد الشهري', type: 'plaintiff', phone: '0551112223', notes: null }])
    if (url.includes('/hearings')) return ok([{ id: 1, scheduled_at: '2026-08-05T09:30:00Z', type: 'مرافعة', status: 'scheduled', location: 'قاعة 3' }])
    if (url.includes('/timeline')) return ok([{ id: 1, title: 'إيداع اللائحة', event_type: 'filing', event_date: '2026-05-02' }])
    if (url.includes('/documents')) return ok([{ id: 1, title: 'صحيفة الدعوى', document_type: 'لائحة' }])
    if (url.includes('/archive-locations')) return ok([{ id: 1, file_title: 'الملف الأصلي', archive_room: 'غرفة 1', cabinet: 'A' }])
    if (url.includes('tasks?')) return json({ data: [{ id: 1, title: 'إعداد مذكرة الرد', priority: 'high', due_date: '2026-08-01', status: 'open', assignee: { id: 9, full_name_ar: 'أحمد المصري' } }], meta: { page: 1, per_page: 100, total: 1, total_pages: 1 }, errors: null })
    // الأساس: تفاصيل القضية
    if (overrides.caseStatus === 403) return json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)
    return ok(CASE)
  })
}

function renderCase() {
  return renderWithProviders(<CaseFilePage />, { route: '/cases/7', path: '/cases/:id' })
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('CaseFilePage', () => {
  it('يحمّل رأس القضية وتبويب النظرة العامة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())

    renderCase()

    expect(await screen.findByRole('heading', { name: 'قضية تجارية' })).toBeInTheDocument()
    expect(screen.getByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getAllByText('أحمد المصري').length).toBeGreaterThan(0)
    // تبويبات موجودة
    expect(screen.getByRole('tab', { name: 'الأطراف' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'المهام' })).toBeInTheDocument()
  })

  it('يتنقّل بين التبويبات ويعرض بيانات كل مورد', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch())
    const user = userEvent.setup()

    renderCase()
    await screen.findByRole('heading', { name: 'قضية تجارية' })

    await user.click(screen.getByRole('tab', { name: 'الأطراف' }))
    expect(await screen.findByText('خالد الشهري')).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'الجلسات' }))
    expect(await screen.findByText('مرافعة')).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'المهام' }))
    expect(await screen.findByText('إعداد مذكرة الرد')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة لتبويب بلا بيانات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch({ parties: [] }))
    const user = userEvent.setup()

    renderCase()
    await screen.findByRole('heading', { name: 'قضية تجارية' })

    await user.click(screen.getByRole('tab', { name: 'الأطراف' }))
    expect(await screen.findByText('لا يوجد أطراف مسجّلون لهذه القضية.')).toBeInTheDocument()
  })

  it('يعرض حالة الحرمان عند قضية غير مسندة (403)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', routeFetch({ caseStatus: 403 }))

    renderCase()
    expect(await screen.findByText(/لا تملك صلاحية الوصول/)).toBeInTheDocument()
  })
})
