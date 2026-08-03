import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { LegalCasesPage } from './LegalCasesPage'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown, meta: unknown = null) => json({ data, meta, errors: null })
const META = { page: 1, per_page: 15, total: 2, total_pages: 1 }
const CASES = [
  { id: 1, internal_number: 'C-100', title: 'نزاع عقد إيجار', status: 'open', progress: 40, client: { id: 5, name: 'شركة الأمل' }, responsibleLawyer: { id: 9, full_name_ar: 'سارة القحطاني' } },
  { id: 2, internal_number: 'C-101', title: 'قضية عمالية', status: 'closed', progress: 100, client: { id: 6, name: 'مؤسسة النور' }, responsibleLawyer: null },
]

function stub(cases: unknown[] = CASES) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('cases')) return ok(cases, META)
    return ok([])
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('LegalCasesPage', () => {
  it('يعرض كل القضايا مع العميل والمحامي المسؤول والحالة', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub()
    renderWithProviders(<LegalCasesPage />)

    expect(await screen.findByText('نزاع عقد إيجار')).toBeInTheDocument()
    expect(screen.getByText('قضية عمالية')).toBeInTheDocument()
    // العميل والمحامي المسؤول ظاهران (كلٌّ في span مستقلّ).
    expect(screen.getByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getByText('سارة القحطاني')).toBeInTheDocument()
    expect(screen.getByText('التقدّم: 100%')).toBeInTheDocument()
  })

  it('يعرض حالة فارغة حين لا قضايا', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    stub([])
    renderWithProviders(<LegalCasesPage />)
    expect(await screen.findByText('لا توجد قضايا مطابقة.')).toBeInTheDocument()
  })
})
