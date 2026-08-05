import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { ForgotPasswordPage } from './ForgotPasswordPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

afterEach(() => vi.restoreAllMocks())

describe('ForgotPasswordPage', () => {
  it('يرسل البريد إلى forgot-password ويعرض رسالة عامّة', async () => {
    const fetchMock = vi.fn(async (_input: RequestInfo | URL) => json({ data: { message: 'ok' }, meta: null, errors: null }))
    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()

    renderWithProviders(<ForgotPasswordPage />)
    await user.type(screen.getByLabelText('البريد الإلكتروني'), 'owner@firm.com')
    await user.click(screen.getByRole('button', { name: 'إرسال رابط إعادة التعيين' }))

    await waitFor(() =>
      expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('auth/forgot-password'))).toBe(true),
    )
    expect(await screen.findByText(/إن كان البريد مسجّلاً/)).toBeInTheDocument()
  })
})
