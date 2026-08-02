import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminOnboardingPage } from './AdminOnboardingPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

const BRANCHES = [{ id: 1, name: 'المكتب الرئيسي', code: 'HQ', is_active: true, departments_count: 1 }]
const DEPTS = [{ id: 1, branch_id: 1, name: 'الموارد البشرية', is_active: true }]
const ROLES = [{ id: 2, name: 'hr', display_name: 'الموارد البشرية', permissions: [] }]

/** يوجّه الـfetch عبر السلسلة الكاملة: org → employees → users → link. */
function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('/branches')) return ok(BRANCHES)
    if (url.includes('/departments')) return ok(DEPTS)
    if (url.includes('/roles')) return ok(ROLES)
    const employeeObj = { id: 50, employee_no: 'EMP-1001', full_name_ar: 'أحمد المصري', national_id: '1000001', status: 'active' }
    const userObj = { id: 9, name: 'أحمد المصري', email: 'new@firm.test', username: null, status: 'active', roles: [], employee: null }
    if (url.includes('/employees') && method === 'POST') return json({ data: employeeObj }, 201)
    if (url.includes('/users') && url.includes('/employee') && method === 'POST') return ok({ ...userObj, employee: { id: 50, employee_no: 'EMP-1001', full_name_ar: 'أحمد المصري' } })
    if (url.endsWith('/users') && method === 'POST') return json({ data: userObj }, 201)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminOnboardingPage', () => {
  it('يُنهي رحلة التهيئة الكاملة: موظف ← حساب ← دور ← ربط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminOnboardingPage />)

    // الخطوة 1: بيانات الموظف (ننتظر تحميل خيارات الفرع/القسم)
    await screen.findByRole('option', { name: 'المكتب الرئيسي' })
    await user.selectOptions(screen.getByLabelText('الفرع *'), '1')
    await screen.findByRole('option', { name: 'الموارد البشرية' })
    await user.selectOptions(screen.getByLabelText('القسم *'), '1')
    await user.type(screen.getByLabelText('الاسم بالعربية *'), 'أحمد المصري')
    await user.type(screen.getByLabelText('الرقم الوطني *'), '1000001')
    await user.click(screen.getByRole('button', { name: 'التالي' }))

    // الخطوة 2: الحساب
    await user.type(await screen.findByLabelText('البريد الإلكتروني (للدخول) *'), 'new@firm.test')
    await user.type(screen.getByLabelText('كلمة المرور المبدئية *'), 'Passw0rd!')
    await user.click(screen.getByRole('button', { name: 'التالي' }))

    // الخطوة 3: الدور
    await screen.findByLabelText('دور الموظف *')
    await screen.findByRole('option', { name: 'الموارد البشرية' })
    await user.selectOptions(screen.getByLabelText('دور الموظف *'), '2')
    await user.click(screen.getByRole('button', { name: 'التالي' }))

    // الخطوة 4: تنفيذ
    await user.click(await screen.findByRole('button', { name: 'تنفيذ التهيئة' }))

    // النجاح + السلسلة الكاملة نُفّذت.
    expect(await screen.findByText('✓ الموظف جاهز للعمل')).toBeInTheDocument()
    const posted = (u: string) => fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes(u))
    await waitFor(() => {
      expect(posted('employees')).toBe(true)
      expect(posted('/users')).toBe(true)
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('/employee'))).toBe(true)
    })
  })

  it('يمنع التقدّم دون إكمال بيانات الموظف', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<AdminOnboardingPage />)

    await screen.findByLabelText('الفرع *')
    await user.click(screen.getByRole('button', { name: 'التالي' }))
    // ما زلنا على الخطوة 1 (لم يظهر حقل البريد).
    expect(screen.queryByLabelText('البريد الإلكتروني (للدخول) *')).not.toBeInTheDocument()
  })
})
