import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminCustomFieldsPage } from './AdminCustomFieldsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const META = {
  entities: [{ key: 'case', label: 'القضايا' }],
  types: [
    { key: 'text', label: 'نص' },
    { key: 'dropdown', label: 'قائمة منسدلة' },
  ],
  contexts: [
    { key: 'create', label: 'الإنشاء' },
    { key: 'edit', label: 'التعديل' },
    { key: 'details', label: 'التفاصيل' },
    { key: 'list', label: 'الجدول' },
  ],
  roles: [
    { id: 'admin', name: 'المدير العام' },
    { id: 'lawyer', name: 'محامٍ' },
  ],
}

const FIELD = {
  id: 7,
  entity: 'case',
  key: 'contract_number',
  label: 'رقم العقد',
  description: 'رقم العقد الموقّع',
  type: 'text',
  required: true,
  options: null,
  default_value: null,
  display_in: ['create', 'edit', 'details'],
  view_roles: ['admin', 'lawyer'],
  edit_roles: ['admin'],
  search_roles: [],
  export_roles: [],
  sort_order: 1,
  is_active: true,
}

/** يوجّه fetch: meta → البيانات الوصفية، list → قائمة الحقول، والباقي حسب المُمرَّر. */
function router(rest: (url: string, init?: RequestInit) => Response, fields: unknown[] = [FIELD]) {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    if (url.includes('custom-fields/meta')) return json({ data: META })
    if (url.includes('custom-fields?entity=')) return json({ data: fields })
    return rest(url, init)
  })
}

beforeEach(() => tokenStorage.set({ access_token: 't', refresh_token: 'r' }))
afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminCustomFieldsPage', () => {
  it('يعرض حقول الكيان من الخادم مع نوعها وشارة الإلزام', async () => {
    vi.stubGlobal('fetch', router(() => json({ data: null })))
    renderWithProviders(<AdminCustomFieldsPage />)

    expect(await screen.findByText('رقم العقد')).toBeInTheDocument()
    expect(screen.getByText('نص')).toBeInTheDocument()
    expect(screen.getByText('إلزامي')).toBeInTheDocument()
  })

  it('حالة فارغة عند غياب الحقول', async () => {
    vi.stubGlobal('fetch', router(() => json({ data: null }), []))
    renderWithProviders(<AdminCustomFieldsPage />)

    expect(await screen.findByText(/لا توجد حقول مخصّصة بعد/)).toBeInTheDocument()
  })

  it('نموذج الإضافة يرسم الأنواع ومصفوفة الأدوار من meta، وخيارات القائمة عند اختيارها', async () => {
    vi.stubGlobal('fetch', router(() => json({ data: null }), []))
    const user = userEvent.setup()
    renderWithProviders(<AdminCustomFieldsPage />)

    await user.click(await screen.findByRole('button', { name: 'إضافة حقل' }))

    const dialog = await screen.findByRole('dialog')
    // مصفوفة الأدوار × الإجراءات مرسومة من meta.roles.
    expect(within(dialog).getByRole('checkbox', { name: 'محامٍ — عرض' })).toBeInTheDocument()
    // خيارات القائمة تظهر فقط عند اختيار نوع «قائمة منسدلة».
    expect(within(dialog).queryByLabelText(/خيارات القائمة/)).not.toBeInTheDocument()
    await user.selectOptions(within(dialog).getByLabelText('النوع'), 'dropdown')
    expect(within(dialog).getByLabelText(/خيارات القائمة/)).toBeInTheDocument()
  })

  it('إنشاء حقل ينادي مسار الإنشاء بالبيانات الصحيحة', async () => {
    const fetchMock = router((url, init) =>
      url.endsWith('admin/custom-fields') && init?.method === 'POST'
        ? json({ data: { ...FIELD, id: 9, key: 'court_type', label: 'نوع المحكمة' } })
        : json({ data: null }),
      [],
    )
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()
    renderWithProviders(<AdminCustomFieldsPage />)

    await user.click(await screen.findByRole('button', { name: 'إضافة حقل' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('اسم الحقل'), 'نوع المحكمة')
    await user.type(within(dialog).getByLabelText(/المُعرّف التقني/), 'court_type')
    await user.click(within(dialog).getByRole('checkbox', { name: 'المدير العام — عرض' }))
    await user.click(within(dialog).getByRole('button', { name: 'حفظ' }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        (c) => String(c[0]).endsWith('admin/custom-fields') && (c[1] as RequestInit | undefined)?.method === 'POST',
      )
      expect(post).toBeTruthy()
      const body = JSON.parse(String((post![1] as RequestInit).body))
      expect(body).toMatchObject({ entity: 'case', key: 'court_type', label: 'نوع المحكمة' })
      expect(body.view_roles).toContain('admin')
    })
  })
})
