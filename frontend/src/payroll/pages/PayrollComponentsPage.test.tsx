import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { PayrollComponentsPage } from './PayrollComponentsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

const COMPONENTS = [
  { id: 1, name: 'بدل سكن', code: 'HOUSING', type: 'allowance', value_type: 'fixed', is_active: true },
  { id: 2, name: 'خصم تأمين', code: 'INSUR', type: 'deduction', value_type: 'percentage', is_active: false },
]

function stub(list: unknown[] = COMPONENTS) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'
    if (url.includes('salary-components') && method === 'POST') {
      return json({ data: { id: 9, name: 'بدل نقل', code: 'TRANS', type: 'allowance', value_type: 'fixed', is_active: true }, meta: null, errors: null }, 201)
    }
    if (url.includes('salary-components')) return ok(list)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('PayrollComponentsPage', () => {
  it('يعرض كتالوج المكوّنات مع النوع وحالة التعطيل', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<PayrollComponentsPage />)

    expect(await screen.findByText('بدل سكن')).toBeInTheDocument()
    expect(screen.getByText('خصم تأمين')).toBeInTheDocument()
    // المكوّن المعطّل يحمل شارة «معطّل».
    expect(screen.getByText('معطّل')).toBeInTheDocument()
    // رمز + نوع القيمة يظهران في البطاقة.
    expect(screen.getByText('HOUSING · مبلغ ثابت')).toBeInTheDocument()
  })

  it('ينشئ مكوّناً جديداً عبر POST', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<PayrollComponentsPage />)

    await screen.findByText('بدل سكن')
    await user.click(screen.getByRole('button', { name: 'إنشاء مكوّن' }))
    await user.type(await screen.findByLabelText('الاسم *'), 'بدل نقل')
    await user.type(screen.getByLabelText('الرمز *'), 'TRANS')
    await user.click(screen.getByRole('button', { name: 'إنشاء' }))

    await waitFor(() => {
      expect(
        fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && String(c[0]).includes('salary-components')),
      ).toBe(true)
    })
  })

  it('يعرض حالة فارغة حين لا مكوّنات', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub([])
    renderWithProviders(<PayrollComponentsPage />)
    expect(await screen.findByText('لا توجد مكوّنات مطابقة. أنشئ مكوّناً جديداً للبدء.')).toBeInTheDocument()
  })
})
