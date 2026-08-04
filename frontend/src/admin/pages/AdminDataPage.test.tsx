import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminDataPage } from './AdminDataPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const CLIENTS_PREVIEW = {
  total: 2,
  create: 1,
  update: 1,
  invalid: 0,
  errors: [],
  fields: [
    { key: 'name', required: true },
    { key: 'type', required: true },
    { key: 'phone', required: false },
    { key: 'national_id', required: false },
  ],
  match_keys: ['national_id', 'email', 'name'],
  detected_headers: ['name', 'type', 'phone', 'national_id'],
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

const xlsx = () => new File(['x'], 'clients.xlsx', { type: 'application/octet-stream' })

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

  it('معاينة العملاء تعرض الملخّص وواجهة مطابقة الأعمدة وتفعّل التأكيد', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ data: CLIENTS_PREVIEW })))
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />) // العملاء هو الكيان الافتراضي
    await user.upload(screen.getByLabelText('ملف الاستيراد'), xlsx())
    await user.click(screen.getByRole('button', { name: 'معاينة' }))

    // ملخّص + واجهة مطابقة (الحقل الإلزامي name يظهر بنجمة) + مفتاح مطابقة.
    expect(await screen.findByText(/إضافة:/)).toBeInTheDocument()
    expect(screen.getByText('name *')).toBeInTheDocument()
    expect(screen.getByText('مفتاح المطابقة (لتحديث السجلّات المطابقة)')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تأكيد الاستيراد' })).toBeEnabled()
  })

  it('تأكيد استيراد العملاء ينادي مسار commit', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL) =>
      String(input).includes('/commit') ? json({ data: { created: 1, updated: 1 } }) : json({ data: CLIENTS_PREVIEW }),
    )
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />)
    await user.upload(screen.getByLabelText('ملف الاستيراد'), xlsx())
    await user.click(screen.getByRole('button', { name: 'معاينة' }))
    await screen.findByText(/إضافة:/)
    await user.click(screen.getByRole('button', { name: 'تأكيد الاستيراد' }))

    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('import/clients/commit'))).toBe(true),
    )
  })

  it('المعاينة بأخطاء تُبقي التأكيد معطّلاً وتعرض الصفوف', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () =>
        json({ data: { total: 1, create: 0, update: 0, invalid: 1, errors: [{ row: 2, message: 'النوع غير صحيح' }], fields: CLIENTS_PREVIEW.fields, match_keys: CLIENTS_PREVIEW.match_keys, detected_headers: CLIENTS_PREVIEW.detected_headers } }),
      ),
    )
    const user = userEvent.setup()

    renderWithProviders(<AdminDataPage />)
    await user.upload(screen.getByLabelText('ملف الاستيراد'), xlsx())
    await user.click(screen.getByRole('button', { name: 'معاينة' }))

    expect(await screen.findByText(/النوع غير صحيح/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تأكيد الاستيراد' })).toBeDisabled()
  })
})
