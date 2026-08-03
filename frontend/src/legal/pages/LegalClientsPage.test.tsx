import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalClientsPage } from './LegalClientsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const META = { page: 1, per_page: 15, total: 2, total_pages: 1 }
const CLIENTS = [
  { id: 1, name: 'شركة الأمل', type: 'company', phone: '0112223333', email: 'a@amal.sa', status: 'active' },
  { id: 2, name: 'خالد الحربي', type: 'individual', status: 'inactive' },
]

function stub() {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (/\/clients$/.test(url.split('?')[0]) && method === 'POST') return json({ data: { id: 3, name: 'عميل جديد', type: 'individual', status: 'active' }, meta: null, errors: null }, 201)
    if (/\/clients(\?|$)/.test(url) && method === 'GET') return json({ data: CLIENTS, meta: META, errors: null })
    return json({ data: [], meta: META, errors: null })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => { vi.restoreAllMocks(); tokenStorage.clear() })

describe('LegalClientsPage', () => {
  it('يعرض العملاء مع النوع والحالة (بما فيهم المعطّلون)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<LegalClientsPage />)
    const activeRow = (await screen.findByText('شركة الأمل')).closest('li') as HTMLElement
    expect(within(activeRow).getByText('نشط')).toBeInTheDocument() // شارة الحالة (لا خيار الفلتر)
    const inactiveRow = screen.getByText('خالد الحربي').closest('li') as HTMLElement
    expect(within(inactiveRow).getByText('معطّل')).toBeInTheDocument()
  })

  it('يمرّر البحث والفلاتر إلى الخادم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalClientsPage />)
    await screen.findByText('شركة الأمل')
    await user.type(screen.getByLabelText('بحث'), 'أمل')
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => /clients\?[^ ]*search=/.test(String(c[0])))).toBe(true)
    })
  })

  it('ينشئ عميلاً بنموذج كامل عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<LegalClientsPage />)
    await user.click(await screen.findByRole('button', { name: 'إنشاء عميل' }))
    await user.type(await screen.findByLabelText('الاسم *'), 'عميل جديد')
    await user.click(screen.getByRole('button', { name: 'إضافة' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /\/clients$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })
})
