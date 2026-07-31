import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { todayISO } from '@/lawyer/api/worklog'
import { WorklogPage } from './WorklogPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, status = 200) => json({ data, meta: null, errors: null }, status)

const TODAY = todayISO()

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('WorklogPage', () => {
  it('يعرض حالة «ابدأ» وزر «حفظ» عند غياب سجل اليوم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(ok([])))

    renderWithProviders(<WorklogPage />)

    expect(await screen.findByText('ابدأ تسجيل إنجاز اليوم')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'حفظ' })).toBeInTheDocument()
  })

  it('يعرض سجل اليوم وزر «تحديث» إن وُجد', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        ok([{ id: 1, work_date: TODAY, done_today: 'مراجعة مذكرة', plan_tomorrow: 'جلسة', updated_at: '2026-07-31T10:00:00Z' }]),
      ),
    )

    renderWithProviders(<WorklogPage />)

    expect(await screen.findByText('إنجاز اليوم')).toBeInTheDocument()
    expect(screen.getByLabelText('ما أنجزته اليوم')).toHaveValue('مراجعة مذكرة')
    expect(screen.getByRole('button', { name: 'تحديث' })).toBeInTheDocument()
  })

  it('يحفظ إنجاز اليوم ويعرض إشعار نجاح (POST بالحمولة الصحيحة)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      if (init?.method === 'POST') return ok({ id: 9, work_date: TODAY, done_today: 'أنجزت اليوم', plan_tomorrow: null, updated_at: '2026-07-31T12:00:00Z' }, 201)
      return ok([])
    })
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<WorklogPage />)
    await screen.findByText('ابدأ تسجيل إنجاز اليوم')

    await user.type(screen.getByLabelText('ما أنجزته اليوم'), 'أنجزت اليوم')
    await user.click(screen.getByRole('button', { name: 'حفظ' }))

    expect(await screen.findByText('تم حفظ إنجاز اليوم.')).toBeInTheDocument()
    const post = fetchMock.mock.calls.find((c) => c[1]?.method === 'POST')
    expect(post).toBeTruthy()
    expect(String(post?.[0])).toContain('me/worklog')
    expect(String(post?.[1]?.body)).toContain('أنجزت اليوم')
  })

  it('يعرض حالة عدم ارتباط الحساب بموظف عند 403', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))

    renderWithProviders(<WorklogPage />)
    await waitFor(() => expect(screen.getByText(/حسابك غير مرتبط بسجلّ موظف/)).toBeInTheDocument())
  })
})
