import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminUsersPage } from './AdminUsersPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const USER = {
  id: 5,
  name: 'سارة عبدالله',
  username: 'sara',
  email: 'sara@law.test',
  status: 'active',
  roles: [{ id: 1, name: 'admin', display_name: 'مدير' }],
  employee: null,
}

const EMP = { id: 9, employee_no: 'EMP-2001', full_name_ar: 'موظف للربط', status: 'active' }

function usersBody(items: unknown[], total = items.length) {
  return json({ data: items, meta: { page: 1, per_page: 15, total, total_pages: 1 }, errors: null })
}

/** يوجّه fetch حسب المسار/الفعل لكل نقاط إدارة المستخدمين. */
function stub({ items = [USER], listStatus = 200 }: { items?: unknown[]; listStatus?: number } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.includes('users/5/disable')) return json({ data: { ...USER, status: 'suspended' } })
    if (url.includes('users/5/enable')) return json({ data: { ...USER, status: 'active' } })
    if (url.includes('users/5/reset-password')) return json({ data: { message: 'ok' } })
    if (url.includes('users/5/employee') && method === 'POST')
      return json({ data: { ...USER, employee: { id: 9, employee_no: 'EMP-2001', full_name_ar: 'موظف للربط' } } })
    if (url.includes('/employees')) return usersBody([EMP])
    if (url.includes('users') && method === 'POST')
      return json({ data: { ...USER, id: 99, name: 'مستخدم جديد', email: 'new@law.test' } }, 201)
    if (url.includes('users')) {
      if (listStatus !== 200) return json({ data: null, meta: null, errors: { code: 'SERVER_ERROR' } }, listStatus)
      return usersBody(items)
    }
    return json({ data: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminUsersPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminUsersPage />)
    expect(screen.getByTestId('users-skeleton')).toBeInTheDocument()
  })

  it('يعرض صفوف المستخدمين عند النجاح', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminUsersPage />)

    expect(await screen.findByText('سارة عبدالله')).toBeInTheDocument()
    expect(screen.getByText('sara@law.test')).toBeInTheDocument()
    expect(screen.getByText('مدير')).toBeInTheDocument()
    expect(screen.getByText('نشط', { selector: 'span' })).toBeInTheDocument()
    expect(screen.getByText(/صفحة 1 من 1/)).toBeInTheDocument()
  })

  it('يرسل معامل البحث إلى الـ API', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminUsersPage />)
    await screen.findByText('سارة عبدالله')

    await user.type(screen.getByLabelText('بحث (الاسم أو البريد أو اسم المستخدم)'), 'سارة')
    await user.click(screen.getByRole('button', { name: 'بحث' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('search='))).toBe(true),
    )
  })

  it('يعرض حالة فارغة عند غياب المستخدمين', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ items: [] })
    renderWithProviders(<AdminUsersPage />)
    expect(await screen.findByText('لا يوجد مستخدمون بعد.')).toBeInTheDocument()
  })

  it('يعرض حالة الخطأ عند فشل الجلب', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ listStatus: 500 })
    renderWithProviders(<AdminUsersPage />)
    expect(await screen.findByText('إعادة المحاولة')).toBeInTheDocument()
  })

  it('ينشئ مستخدماً عبر النموذج (POST /users)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminUsersPage />)
    await screen.findByText('سارة عبدالله')

    await user.click(screen.getByRole('button', { name: 'إنشاء مستخدم' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('الاسم'), 'مستخدم جديد')
    await user.type(within(dialog).getByLabelText('البريد الإلكتروني'), 'new@law.test')
    await user.type(within(dialog).getByLabelText('كلمة المرور'), 'Secret123')
    await user.click(within(dialog).getByRole('button', { name: 'إنشاء' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(
          (c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).endsWith('/users'),
        ),
      ).toBe(true),
    )
  })

  it('يعطّل مستخدماً عبر POST /users/{id}/disable', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminUsersPage />)
    await screen.findByText('سارة عبدالله')

    await user.click(screen.getByRole('button', { name: 'تعطيل' }))
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('users/5/disable'))).toBe(true),
    )
  })

  it('يربط موظفاً بالحساب عبر البحث ثم الربط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminUsersPage />)
    await screen.findByText('سارة عبدالله')

    await user.click(screen.getByRole('button', { name: 'ربط موظف' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText(/ابحث عن موظف/), 'موظف')
    await user.click(within(dialog).getByRole('button', { name: 'بحث' }))

    await within(dialog).findByText('موظف للربط')
    await user.click(within(dialog).getByRole('button', { name: 'ربط' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(
          (c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('users/5/employee'),
        ),
      ).toBe(true),
    )
  })
})
