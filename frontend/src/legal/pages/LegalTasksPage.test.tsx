import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalTasksPage } from './LegalTasksPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const META = { page: 1, per_page: 15, total: 2, total_pages: 1 }
const TASKS = [
  { id: 1, title: 'إعداد مذكرة', priority: 'high', status: 'open', due_date: '2026-09-10', assigned_to: 7, assignee: { id: 7, full_name_ar: 'سارة القحطاني' }, case: { id: 3, internal_number: 'C-100', title: 'نزاع تجاري' } },
  { id: 2, title: 'أرشفة ملف', priority: 'low', status: 'done', assignee: { id: 8, full_name_ar: 'خالد' }, case: null },
]
const EMPLOYEES = [{ id: 9, employee_no: 'E-9', full_name_ar: 'موظف جديد', status: 'active' }]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (/tasks\/\d+\/complete/.test(url) && method === 'PATCH') return json({ data: { ...TASKS[0], status: 'done' }, meta: null, errors: null })
    if (/tasks\/\d+\/assign/.test(url) && method === 'PATCH') return json({ data: TASKS[0], meta: null, errors: null })
    if (/tasks\/\d+$/.test(url.split('?')[0]) && method === 'PUT') return json({ data: TASKS[0], meta: null, errors: null })
    if (/\/tasks$/.test(url.split('?')[0]) && method === 'POST') return json({ data: { id: 3, title: 'جديدة', priority: 'normal', status: 'open', assignee: null, case: null }, meta: null, errors: null }, 201)
    if (/\/tasks(\?|$)/.test(url) && method === 'GET') return json({ data: TASKS, meta: META, errors: null })
    if (/employees/.test(url)) return json({ data: EMPLOYEES, meta: { page: 1, per_page: 10, total: 1, total_pages: 1 }, errors: null })
    return json({ data: [], meta: META, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('LegalTasksPage', () => {
  it('يعرض المهام مع المُسنَد إليه والأولوية والحالة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<LegalTasksPage />)
    const openRow = (await screen.findByText('إعداد مذكرة')).closest('li') as HTMLElement
    expect(within(openRow).getByText('سارة القحطاني')).toBeInTheDocument()
    expect(within(openRow).getByText('عالية')).toBeInTheDocument() // شارة الأولوية (لا خيار الفلتر)
    // المهمة المنجزة: بلا أزرار إجراء (مكتملة).
    const doneRow = screen.getByText('أرشفة ملف').closest('li') as HTMLElement
    expect(within(doneRow).getByText('مكتملة')).toBeInTheDocument()
    expect(within(doneRow).queryByRole('button', { name: 'إكمال' })).toBeNull()
  })

  it('يُكمل مهمة مفتوحة عبر PATCH complete', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalTasksPage />)
    const openRow = (await screen.findByText('إعداد مذكرة')).closest('li') as HTMLElement
    await user.click(within(openRow).getByRole('button', { name: 'إكمال' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'PATCH' && /tasks\/1\/complete/.test(String(c[0])))).toBe(true)
    })
  })

  it('ينشئ مهمة مع اختيار موظف عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalTasksPage />)
    await user.click(await screen.findByRole('button', { name: 'إنشاء مهمة' }))
    await user.type(await screen.findByLabelText('العنوان *'), 'مهمة جديدة')
    await user.type(screen.getByLabelText('الموظف المسؤول *'), 'موظف')
    await user.click(await screen.findByRole('button', { name: /موظف جديد/ }))
    await user.click(screen.getByRole('button', { name: 'إضافة' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /\/tasks$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
    const body = JSON.parse(String(fetchMock.mock.calls.find((c) => (c[1]?.method ?? 'GET') === 'POST' && /\/tasks$/.test(String(c[0]).split('?')[0]))?.[1]?.body))
    expect(body.assigned_to).toBe(9)
  })
})
