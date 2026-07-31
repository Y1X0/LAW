import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from './AuthContext'
import { tokenStorage } from './tokenStorage'
import { useAuth } from './useAuth'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

function Consumer() {
  const { status, logout } = useAuth()
  return (
    <div>
      <span data-testid="status">{status}</span>
      <button onClick={() => void logout()}>خروج</button>
    </div>
  )
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('AuthProvider — تنظيف الكاش عند الخروج', () => {
  it('يمسح كاش React Query بالكامل عند تسجيل الخروج (لا تسريب بيانات المستخدم السابق)', async () => {
    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    // محاكاة بيانات محامي A في الكاش (قضية سرّية).
    qc.setQueryData(['case', 7, 'detail'], { internal_number: 'A-SECRET', title: 'قضية المحامي A' })
    expect(qc.getQueryCache().getAll().length).toBeGreaterThan(0)

    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        const url = String(input)
        if (url.includes('auth/me')) return json({ data: { user: { id: 1, name: 'محامي A', email: 'a@a.co' } } })
        if (url.includes('auth/logout')) return json({ data: null })
        return json({ data: null })
      }),
    )

    render(
      <QueryClientProvider client={qc}>
        <AuthProvider>
          <Consumer />
        </AuthProvider>
      </QueryClientProvider>,
    )

    // مصادَق عليه بعد التحقّق من التوكن.
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'))

    await userEvent.click(screen.getByRole('button', { name: 'خروج' }))

    // بعد الخروج: الحالة نظيفة + الكاش فارغ تماماً.
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'))
    expect(qc.getQueryCache().getAll().length).toBe(0)
    expect(tokenStorage.accessToken()).toBeNull()
  })
})
