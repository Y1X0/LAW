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
import { RequireAdmin } from './RequireAdmin'
import { RequireHr } from './RequireHr'
import { RequireLawyer } from './RequireLawyer'

const future = { v7_startTransition: true, v7_relativeSplatPath: true } as const

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

/** يوجّه fetch حسب المسار: مصادقة + كشوف قدرة المحامي/HR/Admin (200/403). */
function stubFetch({ isLawyer = false, isHr = false, isAdmin = false }: { isLawyer?: boolean; isHr?: boolean; isAdmin?: boolean }) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('auth/me')) return json({ data: { user: { id: 1, name: 'أحمد', email: 'a@a.co' } } })
      if (url.includes('auth/logout')) return json({ data: null })
      if (url.includes('me/legal-summary')) {
        return isLawyer ? json({ data: { cases: { total: 2 } } }) : json({ data: null, errors: { code: 'FORBIDDEN' } }, 403)
      }
      if (url.includes('roles')) {
        return isAdmin ? json({ data: [{ id: 1, name: 'admin' }] }) : json({ data: null, errors: { code: 'FORBIDDEN' } }, 403)
      }
      if (url.includes('employees')) {
        return isHr ? json({ data: [], meta: { total: 0 } }) : json({ data: null, errors: { code: 'FORBIDDEN' } }, 403)
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
                  <Route element={<RequireAdmin />}>
                    <Route path="admin" element={<div>محتوى وحدة التحكّم</div>} />
                  </Route>
                  <Route element={<RequireHr />}>
                    <Route path="hr" element={<div>محتوى لوحة HR</div>} />
                  </Route>
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

describe('توجيه الأدوار الثلاثة + الحماية', () => {
  it('غير المصادق يُعاد إلى /login', async () => {
    stubFetch({}) // لا توكن → لا نداءات فعلية
    renderAppAt('/')
    expect(await screen.findByText('شاشة الدخول')).toBeInTheDocument()
  })

  it('المحامي: «/» يوجّه إلى رئيسيته ويظهر تنقّل المحامي (قضاياي)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isLawyer: true })
    renderAppAt('/')
    expect(await screen.findByText('رئيسية المحامي')).toBeInTheDocument()
    expect(screen.getAllByText('قضاياي').length).toBeGreaterThan(0)
    expect(screen.getAllByText('الإنجازات اليومية').length).toBeGreaterThan(0)
    // المحامي لا يرى بوابة الموارد البشرية.
    expect(screen.queryByText('بوابة الموارد البشرية')).not.toBeInTheDocument()
  })

  it('الموظف العادي: «/» يوجّه إلى لوحته وتنقّله لا يحوي «قضاياي»', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({})
    renderAppAt('/')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.getAllByText('لوحتي').length).toBeGreaterThan(0)
    expect(screen.queryByText('قضاياي')).not.toBeInTheDocument()
    expect(screen.queryByText('بوابة الموارد البشرية')).not.toBeInTheDocument()
  })

  it('مستخدم HR: «/» يوجّه إلى /hr ويظهر هيكل الموارد البشرية', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isHr: true })
    renderAppAt('/')
    expect(await screen.findByText('محتوى لوحة HR')).toBeInTheDocument()
    // هيكل HR ظاهر (عنوان البوابة في الشعار) ولا يظهر تنقّل المحامي.
    expect(screen.getAllByText('بوابة الموارد البشرية').length).toBeGreaterThan(0)
    expect(screen.queryByText('قضاياي')).not.toBeInTheDocument()
  })

  it('المحامي يصل إلى /cases', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isLawyer: true })
    renderAppAt('/cases')
    expect(await screen.findByText('شاشة القضايا')).toBeInTheDocument()
  })

  it('الموظف يُمنع من مسار المحامي /cases ويُعاد إلى لوحته', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({})
    renderAppAt('/cases')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.queryByText('شاشة القضايا')).not.toBeInTheDocument()
  })

  it('غير الـ HR يُمنع من /hr ويُعاد إلى بوابته', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({}) // موظف عادي
    renderAppAt('/hr')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.queryByText('محتوى لوحة HR')).not.toBeInTheDocument()
  })

  it('مستخدم HR يصل إلى /hr', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isHr: true })
    renderAppAt('/hr')
    expect(await screen.findByText('محتوى لوحة HR')).toBeInTheDocument()
  })

  it('المسؤول: «/» يوجّه إلى /admin ويظهر هيكل وحدة التحكّم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isAdmin: true })
    renderAppAt('/')
    expect(await screen.findByText('محتوى وحدة التحكّم')).toBeInTheDocument()
    // هيكل وحدة التحكّم ظاهر (العنوان الفرعي في الشعار) ولا يظهر تنقّل المحامي/HR.
    expect(screen.getAllByText('وحدة التحكّم').length).toBeGreaterThan(0)
    expect(screen.queryByText('قضاياي')).not.toBeInTheDocument()
    expect(screen.queryByText('بوابة الموارد البشرية')).not.toBeInTheDocument()
  })

  it('المسؤول يصل إلى /admin مباشرة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isAdmin: true })
    renderAppAt('/admin')
    expect(await screen.findByText('محتوى وحدة التحكّم')).toBeInTheDocument()
  })

  it('الموظف العادي يُمنع من /admin ويُعاد إلى لوحته', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({})
    renderAppAt('/admin')
    expect(await screen.findByText('لوحة الموظف')).toBeInTheDocument()
    expect(screen.queryByText('محتوى وحدة التحكّم')).not.toBeInTheDocument()
  })

  it('المحامي يُمنع من /admin ولا يرى محتوى وحدة التحكّم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isLawyer: true })
    renderAppAt('/admin')
    expect(await screen.findByText('رئيسية المحامي')).toBeInTheDocument()
    expect(screen.queryByText('محتوى وحدة التحكّم')).not.toBeInTheDocument()
  })

  it('مستخدم HR يُمنع من /admin ولا يرى محتوى وحدة التحكّم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stubFetch({ isHr: true })
    renderAppAt('/admin')
    expect(await screen.findByText('محتوى لوحة HR')).toBeInTheDocument()
    expect(screen.queryByText('محتوى وحدة التحكّم')).not.toBeInTheDocument()
  })
})
