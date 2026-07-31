import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { ProfilePage } from './ProfilePage'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const profile = {
  data: {
    name: 'سارة', employee_number: 'EMP-9', job_title: 'محامٍ', branch: 'الرياض', department: 'التقاضي', manager: 'أحمد',
    phone: '0500000000', address: 'الرياض', photo_path: null, emergency_contact_name: null, emergency_contact_phone: null,
  },
  meta: null, errors: null,
}

const ALLOWED = ['phone', 'address', 'photo_path', 'emergency_contact_name', 'emergency_contact_phone']

afterEach(() => vi.restoreAllMocks())

describe('ProfilePage', () => {
  it('يعرض بيانات الملف (وظيفية + قابلة للتعديل)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(profile)))
    renderWithProviders(<ProfilePage />)

    expect(await screen.findByText('سارة')).toBeInTheDocument()
    expect(screen.getByText('محامٍ')).toBeInTheDocument() // للعرض فقط
    expect(screen.getByDisplayValue('0500000000')).toBeInTheDocument() // حقل قابل للتعديل
  })

  it('يحفظ الحقول المسموحة فقط (لا يرسل حقولاً حسّاسة)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    const fetchMock = vi.fn().mockImplementation((_url: string, init?: RequestInit) => {
      if ((init?.method ?? 'GET') === 'PATCH') return Promise.resolve(jsonResponse({ data: profile.data, meta: null, errors: null }))
      return Promise.resolve(jsonResponse(profile))
    })
    vi.stubGlobal('fetch', fetchMock)
    renderWithProviders(<ProfilePage />)

    fireEvent.change(await screen.findByDisplayValue('0500000000'), { target: { value: '0555555555' } })
    await userEvent.click(screen.getByRole('button', { name: 'حفظ' }))

    await waitFor(() => expect(screen.getByText(/تم حفظ بياناتك/)).toBeInTheDocument())

    // جسم PATCH يحوي الحقول المسموحة فقط — لا راتب/قسم/وظيفة/مدير.
    const patchCall = fetchMock.mock.calls.find((c) => (c[1]?.method ?? 'GET') === 'PATCH')
    const body = JSON.parse(patchCall![1].body as string)
    expect(Object.keys(body).sort()).toEqual([...ALLOWED].sort())
    expect(body).not.toHaveProperty('basic_salary')
    expect(body).not.toHaveProperty('department')
    expect(body).not.toHaveProperty('job_title')
  })

  it('لا يوفّر حقولاً لتعديل البيانات الحسّاسة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(profile)))
    renderWithProviders(<ProfilePage />)

    await screen.findByText('سارة')
    // لا حقول إدخال للراتب/القسم/الوظيفة/المدير.
    expect(screen.queryByLabelText(/الراتب/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/القسم/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/المسمّى الوظيفي/)).not.toBeInTheDocument()
  })

  it('يعرض 403 حساب غير مرتبط', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE' } }, 403)))
    renderWithProviders(<ProfilePage />)
    expect(await screen.findByText(/غير مرتبط/)).toBeInTheDocument()
  })
})
