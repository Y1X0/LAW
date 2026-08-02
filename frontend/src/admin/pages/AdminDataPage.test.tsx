import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminDataPage } from './AdminDataPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

beforeEach(() => {
  tokenStorage.set({ access_token: 't', refresh_token: 'r' })
  // jsdom لا يوفّر هذه — يحتاجها منطق التنزيل.
  vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(() => 'blob:x'), revokeObjectURL: vi.fn() })
})

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminDataPage', () => {
  it('يعرض أزرار التصدير للكيانات الستّة', () => {
    vi.stubGlobal('fetch', vi.fn())
    renderWithProviders(<AdminDataPage />)
    expect(screen.getByRole('button', { name: 'تصدير: الموظفون' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تصدير: القضايا' })).toBeInTheDocument()
  })

  it('التصدير ينادي مسار تصدير الكيان', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL) => new Response('xlsxbytes', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />)
    await user.click(screen.getByRole('button', { name: 'تصدير: الموظفون' }))

    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('admin/data/export/employees'))).toBe(true),
    )
  })

  it('المعاينة تعرض الملخّص وتفعّل التأكيد عند عدم وجود أخطاء', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL) =>
      json({ data: { total: 2, create: 1, update: 1, invalid: 0, errors: [] } }),
    )
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />)

    const file = new File(['x'], 'employees.xlsx', { type: 'application/octet-stream' })
    await user.upload(screen.getByLabelText('ملف الموظفين'), file)
    await user.click(screen.getByRole('button', { name: 'معاينة' }))

    expect(await screen.findByText(/إضافة:/)).toBeInTheDocument()
    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('import/employees/preview'))).toBe(true),
    )
    expect(screen.getByRole('button', { name: 'تأكيد الاستيراد' })).toBeEnabled()
  })

  it('المعاينة بأخطاء تُبقي التأكيد معطّلاً وتعرض الصفوف', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () =>
        json({ data: { total: 1, create: 0, update: 0, invalid: 1, errors: [{ row: 2, message: 'الفرع غير موجود' }] } }),
      ),
    )
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />)
    const file = new File(['x'], 'employees.xlsx', { type: 'application/octet-stream' })
    await user.upload(screen.getByLabelText('ملف الموظفين'), file)
    await user.click(screen.getByRole('button', { name: 'معاينة' }))

    expect(await screen.findByText(/الفرع غير موجود/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تأكيد الاستيراد' })).toBeDisabled()
  })
})
