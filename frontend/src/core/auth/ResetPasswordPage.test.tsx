import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { ResetPasswordPage } from './ResetPasswordPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

afterEach(() => vi.restoreAllMocks())

describe('ResetPasswordPage', () => {
  it('رابط ناقص (بلا token) يعرض رسالة خطأ', () => {
    vi.stubGlobal('fetch', vi.fn())
    renderWithProviders(<ResetPasswordPage />, { route: '/reset-password' })
    expect(screen.getByText(/الرابط غير صالح أو ناقص/)).toBeInTheDocument()
  })

  it('يرسل token وكلمة المرور إلى reset-password', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) => json({ data: { message: 'ok' }, meta: null, errors: null }))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<ResetPasswordPage />, { route: '/reset-password?token=TK&email=owner%40firm.com' })
    await user.type(screen.getByLabelText('كلمة المرور الجديدة'), 'newpass123')
    await user.type(screen.getByLabelText('تأكيد كلمة المرور'), 'newpass123')
    await user.click(screen.getByRole('button', { name: 'تعيين كلمة المرور' }))

    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => String(c[0]).includes('auth/reset-password'))
      expect(call).toBeTruthy()
      const body = JSON.parse(String((call![1] as RequestInit).body))
      expect(body).toMatchObject({ token: 'TK', email: 'owner@firm.com', password: 'newpass123', password_confirmation: 'newpass123' })
    })
  })

  it('عدم تطابق كلمتي المرور يمنع الإرسال', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL) => json({ data: {}, meta: null, errors: null }))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<ResetPasswordPage />, { route: '/reset-password?token=TK&email=a%40b.com' })
    await user.type(screen.getByLabelText('كلمة المرور الجديدة'), 'newpass123')
    await user.type(screen.getByLabelText('تأكيد كلمة المرور'), 'different1')
    await user.click(screen.getByRole('button', { name: 'تعيين كلمة المرور' }))

    expect(await screen.findByText('كلمتا المرور غير متطابقتين.')).toBeInTheDocument()
    expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('reset-password'))).toBe(false)
  })
})
