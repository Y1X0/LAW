import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '@/core/auth/AuthContext'
import { ProtectedRoute } from '@/core/auth/ProtectedRoute'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { RoleLayout } from '@/core/layout/RoleLayout'
import { IndexRedirect } from '@/core/layout/IndexRedirect'
import { CapabilitiesProvider } from './CapabilitiesProvider'
import { RequireLawyer } from './RequireLawyer'

const future = { v7_startTransition: true, v7_relativeSplatPath: true } as const

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

/** يوجّه fetch حسب المسار: مصادقة + كشف قدرة المحامي (200/403). */
function stubFetch(isLawyer: boolean) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('auth/me')) return json({ data: { user: { id: 1, name: 'أحمد', email: 'a@a.co' } } })
      if (url.includes('auth/logout')) return json({ data: null })
      if (url.includes('me/legal-summary')) {
        return isLawyer ? json({ data: { cases: { total: 2 } } }) : json({ data: null, errors: { code: 'FORBIDDEN' } }, 403)
      }
      return json({ data: null })
    }),
  )
}

/** يركّب نفس طبقات routes.tsx (بأوراق واسمة) داخل MemoryRouter للاختبار. */
function renderAppAt(route: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={[route]} future={future}>
          <Routes>
            <Route path="/login" element={<div>شاشة الدخول</div>} />
            <Route element={<ProtectedRoute />}>
              <Route element={<CapabilitiesProvider />}>
                <Route element={<RoleLayout />}>
                  <Route index element={<IndexRedirect />} />
                  <Route path="dashboard" element={<div>لوحة الموظف</div>} />
                  <Route path="leave" element={<div>إجازاتي</div>} />
                  <Route element={<RequireLawyer />}>
                    <Route path="home" element={<div>رئيسية المحامي</div>} />
                    <Route path="cases" element={<div>شاشة القضايا</div>} />
                  </Route>
                </Route>
              </Route>
            </Route>
          </Routes>
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  )
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('توجيه الدورين + الحماية', () => {
  it('غير المصادق يُعاد إلى /login', async () => {
    stubFetch(false) // لا توكن → لا نداءات فعلية
    renderAppAt('/')
    expect(await screen.findByText('شاشة الدخول')).toBeInTheDocument()
  })

  it('المحامي: «/» يوجّه إلى رئيسيته ويظهر تنقّل المحامي (قضاياي)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch(true)
    renderAppAt('/')
    expect(await screen.findByText('رئيسية المحامي')).toBeInTheDocument()
    // التنقّل الموحّد للمحامي يحوي «قضاياي» و«الإنجازات اليومية».
    expect(screen.getAllByText('قضاياي').length).toBeGreaterThan(0)
    expect(screen.getAllByText('الإنجازات اليومية').length).toBeGreaterThan(0)
  })

  it('الموظف العادي: «/» يوجّه إلى لوحته وتنقّله لا يحوي «قضاياي»', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch(false)
    renderAppAt('/')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.getAllByText('لوحتي').length).toBeGreaterThan(0)
    expect(screen.queryByText('قضاياي')).not.toBeInTheDocument()
  })

  it('المحامي يصل إلى /cases', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch(true)
    renderAppAt('/cases')
    expect(await screen.findByText('شاشة القضايا')).toBeInTheDocument()
  })

  it('الموظف يُمنع من مسار المحامي /cases ويُعاد إلى لوحته', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch(false)
    renderAppAt('/cases')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.queryByText('شاشة القضايا')).not.toBeInTheDocument()
  })
})
