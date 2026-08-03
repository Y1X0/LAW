import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollSalaryPage } from './PayrollSalaryPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })
const EMP_META = { page: 1, per_page: 10, total: 1, total_pages: 1 }
const EMPLOYEES = [{ id: 50, employee_no: 'EMP-1001', full_name_ar: 'أحمد المصري', status: 'active' }]
const PROFILES = [
  { id: 1, basic_salary: '5000.00', currency: 'SAR', payment_method: 'bank', effective_from: '2026-01-01', is_active: true },
]
const COMPONENTS = [
  { id: 7, salary_component_id: 3, value: '500.00', is_active: true, effective_from: '2026-01-01', component: { id: 3, name: 'بدل سكن', code: 'HOUSING', type: 'allowance', value_type: 'fixed' } },
]
const CATALOG = [{ id: 3, name: 'بدل سكن', code: 'HOUSING', type: 'allowance', value_type: 'fixed', is_active: true }]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('/salary-profiles') && method === 'POST') return json({ data: { ...PROFILES[0], id: 2 }, meta: null, errors: null }, 201)
    if (url.includes('/salary-profiles')) return ok(PROFILES)
    if (url.includes('/salary-components') && method === 'POST') return json({ data: COMPONENTS[0], meta: null, errors: null }, 201)
    if (url.match(/employees\/\d+\/salary-components/)) return ok(COMPONENTS)
    if (url.includes('salary-components')) return ok(CATALOG) // كتالوج
    if (url.includes('employees')) return ok(EMPLOYEES, EMP_META)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

async function selectEmployee(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByPlaceholderText('الاسم أو الرقم الوظيفي أو الرقم الوطني'), 'أحمد')
  await user.click(await screen.findByRole('button', { name: /أحمد المصري/ }))
}

describe('PayrollSalaryPage', () => {
  it('يبحث عن موظف ويعرض راتبه الأساسي ومكوّناته', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollSalaryPage />)

    await selectEmployee(user)

    // ملف الراتب النشط
    expect(await screen.findByText('تحويل بنكي')).toBeInTheDocument()
    // مكوّن مسند
    expect(screen.getByText('بدل سكن')).toBeInTheDocument()
  })

  it('يحدّث الراتب الأساسي عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollSalaryPage />)

    await selectEmployee(user)
    await user.click(await screen.findByRole('button', { name: 'تحديث الراتب الأساسي' }))

    await user.type(await screen.findByLabelText('الراتب الأساسي *'), '6000')
    await user.click(screen.getByRole('button', { name: 'حفظ' }))

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('/salary-profiles')),
      ).toBe(true)
    })
  })

  it('يُسند مكوّناً للموظف عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollSalaryPage />)

    await selectEmployee(user)
    await user.click(await screen.findByRole('button', { name: 'إسناد مكوّن' }))

    await screen.findByRole('option', { name: /بدل سكن/ })
    await user.selectOptions(screen.getByLabelText('المكوّن *'), '3')
    await user.type(screen.getByLabelText('القيمة *'), '700')
    await user.click(screen.getByRole('button', { name: 'إسناد' }))

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /employees\/\d+\/salary-components/.test(String(c[0]))),
      ).toBe(true)
    })
  })
})
