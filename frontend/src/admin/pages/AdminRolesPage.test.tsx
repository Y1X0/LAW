import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminRolesPage } from './AdminRolesPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const ROLES = [
  {
    id: 1,
    name: 'admin',
    display_name: 'المدير العام',
    is_system: true,
    permissions: [
      { id: 10, name: 'users.manage', module: 'users' },
      { id: 11, name: 'roles.manage', module: 'roles' },
    ],
  },
  { id: 2, name: 'auditor', display_name: 'مدقّق', is_system: false, permissions: [{ id: 12, name: 'audit.view', module: 'audit' }] },
]

const PERMISSIONS = [
  { id: 10, name: 'users.manage', module: 'users', description: null },
  { id: 11, name: 'roles.manage', module: 'roles', description: null },
  { id: 12, name: 'audit.view', module: 'audit', description: null },
]

function stub({ roles = ROLES }: { roles?: unknown[] } = {}) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.includes('permissions')) return json({ data: PERMISSIONS })
    if (/roles\/\d+\/permissions/.test(url) && method === 'PUT') return json({ data: ROLES[1] })
    if (/roles\/\d+/.test(url) && method === 'DELETE') return json({ data: null })
    if (/roles\/\d+/.test(url) && method === 'PUT') return json({ data: { ...ROLES[1], display_name: 'محدّث' } })
    if (url.includes('roles') && method === 'POST') return json({ data: { id: 99, name: 'new', display_name: 'جديد', is_system: false, permissions: [] } }, 201)
    if (url.includes('roles')) return json({ data: roles })
    return json({ data: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminRolesPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminRolesPage />)
    expect(screen.getByTestId('roles-skeleton')).toBeInTheDocument()
  })

  it('يعرض الأدوار مع عدد الصلاحيات وشارة النظامي', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminRolesPage />)

    expect(await screen.findByText('المدير العام')).toBeInTheDocument()
    expect(screen.getByText('مدقّق')).toBeInTheDocument()
    expect(screen.getByText('نظامي')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة عند غياب الأدوار', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub({ roles: [] })
    renderWithProviders(<AdminRolesPage />)
    expect(await screen.findByText('لا توجد أدوار بعد.')).toBeInTheDocument()
  })

  it('ينشئ دوراً عبر النموذج (POST /roles)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminRolesPage />)
    await screen.findByText('المدير العام')

    await user.click(screen.getByRole('button', { name: 'إنشاء دور' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText(/المعرّف/), 'clerk')
    await user.type(within(dialog).getByLabelText('الاسم الظاهر'), 'كاتب')
    await user.click(within(dialog).getByRole('button', { name: 'إنشاء' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).endsWith('/roles')),
      ).toBe(true),
    )
  })

  it('يحرّر مصفوفة الصلاحيات ويحفظها (PUT /roles/{id}/permissions)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminRolesPage />)
    await screen.findByText('مدقّق')

    // فتح مصفوفة صلاحيات دور «مدقّق» (الصفّ الثاني).
    const auditorRow = screen.getByRole('row', { name: /مدقّق/ })
    await user.click(within(auditorRow).getByRole('button', { name: 'الصلاحيات' }))

    const dialog = await screen.findByRole('dialog')
    // الصلاحيات ظهرت مجمّعة حسب الوحدة.
    expect(await within(dialog).findByText('users.manage')).toBeInTheDocument()
    await user.click(within(dialog).getByText('users.manage'))
    await user.click(within(dialog).getByRole('button', { name: 'حفظ' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(
          (c) => (c[1]?.method ?? 'GET') === 'PUT' && /roles\/2\/permissions/.test(String(c[0])),
        ),
      ).toBe(true),
    )
  })

  it('يحذف دوراً غير نظامي بعد التأكيد (DELETE /roles/{id})', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminRolesPage />)
    await screen.findByText('مدقّق')

    const auditorRow = screen.getByRole('row', { name: /مدقّق/ })
    await user.click(within(auditorRow).getByRole('button', { name: 'حذف' }))
    await user.click(within(auditorRow).getByRole('button', { name: 'تأكيد' }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'DELETE' && /roles\/2$/.test(String(c[0]))),
      ).toBe(true),
    )
  })

  it('لا يُظهر زر الحذف للأدوار النظامية', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminRolesPage />)
    await screen.findByText('المدير العام')

    const adminRow = screen.getByRole('row', { name: /المدير العام/ })
    expect(within(adminRow).queryByRole('button', { name: 'حذف' })).not.toBeInTheDocument()
  })
})
