import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { AdminBackupPage } from './AdminBackupPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

const BACKUPS = [
  { id: 2, filename: 'law-backup-20260805-060000-daily.dump', kind: 'daily', status: 'completed', trigger: 'scheduled', size_bytes: 2_500_000, created_by: null, created_at: '2026-08-05T06:00:00+00:00' },
  { id: 1, filename: 'law-backup-20260804-060000-daily.dump', kind: 'daily', status: 'failed', trigger: 'scheduled', size_bytes: null, created_by: 'المالك', created_at: '2026-08-04T06:00:00+00:00' },
]

beforeEach(() => tokenStorage.set({ access_token: 't', refresh_token: 'r' }))
afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AdminBackupPage', () => {
  it('يعرض آخر نسخة ناجحة وجدول النسخ بحالاتها', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ok(BACKUPS)))
    renderWithProviders(<AdminBackupPage />)

    expect(await screen.findByText(/آخر نسخة ناجحة/)).toBeInTheDocument()
    // الصفّ الناجح يتيح التنزيل؛ الفاشل لا.
    expect(screen.getByText('ناجحة')).toBeInTheDocument()
    expect(screen.getByText('فاشلة')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تنزيل' })).toBeInTheDocument()
    // الحجم مُنسَّق (يظهر في سطر الحالة وفي الجدول).
    expect(screen.getAllByText('2.4 MB').length).toBeGreaterThan(0)
  })

  it('«إنشاء نسخة الآن» ينادي POST ويحدّث القائمة', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      if ((init?.method ?? 'GET') === 'POST') {
        return json({ data: { id: 3, filename: 'new.dump', kind: 'manual', status: 'completed', trigger: 'manual', size_bytes: 100, created_by: 'المالك', created_at: '2026-08-05T07:00:00+00:00' }, meta: null, errors: null }, 201)
      }
      return ok(BACKUPS)
    })
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<AdminBackupPage />)
    await screen.findByText(/آخر نسخة ناجحة/)
    await user.click(screen.getByRole('button', { name: 'إنشاء نسخة الآن' }))

    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).endsWith('admin/backups') && (c[1] as RequestInit | undefined)?.method === 'POST')).toBe(true),
    )
  })

  it('حالة فارغة عند غياب النسخ', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ok([])))
    renderWithProviders(<AdminBackupPage />)
    expect(await screen.findByText('لا توجد نسخ احتياطية بعد.')).toBeInTheDocument()
  })
})
