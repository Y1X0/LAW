import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminSettingsPage } from './AdminSettingsPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const SETTINGS = { general: [{ id: 1, key: 'org_name_ar', value: 'مكتب الحق' }] }

function stub() {
  const fetchMock = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
    if ((init?.method ?? 'GET') === 'PUT') return json({ data: SETTINGS })
    return json({ data: SETTINGS })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminSettingsPage', () => {
  it('يعرض هيكل التحميل أثناء الجلب', () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => {})))
    renderWithProviders(<AdminSettingsPage />)
    expect(screen.getByTestId('settings-skeleton')).toBeInTheDocument()
  })

  it('يملأ النموذج بالقيم الحالية', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<AdminSettingsPage />)
    expect(await screen.findByDisplayValue('مكتب الحق')).toBeInTheDocument()
  })

  it('يحفظ الإعدادات عبر PUT', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = stub()
    const user = userEvent.setup()

    renderWithProviders(<AdminSettingsPage />)
    await screen.findByDisplayValue('مكتب الحق')

    await user.click(screen.getByRole('button', { name: 'حفظ' }))
    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some(
          (c) => (c[1]?.method ?? 'GET') === 'PUT' && String(c[0]).includes('admin/settings'),
        ),
      ).toBe(true),
    )
  })
})
