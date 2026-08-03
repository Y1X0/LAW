import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { CaseDocumentsSection } from './CaseDocumentsSection'
import { CasePartiesSection } from './CasePartiesSection'
import { CaseTimelineSection } from './CaseTimelineSection'
import { CaseArchiveSection } from './CaseArchiveSection'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const ok = (data: unknown) => json({ data, meta: null, errors: null })

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

function auth() {
  tokenStorage.set({ access_token: 't', refresh_token: 'r' })
}

describe('CaseDocumentsSection', () => {
  const DOCS = [{ id: 5, title: 'لائحة الدعوى', document_type: 'لائحة', original_name: 'laeha.pdf', size_bytes: 2048 }]
  function stub() {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'
      if (/documents\/\d+\/download/.test(url)) return new Response(new Blob(['pdf']), { status: 200 })
      if (/documents\/\d+/.test(url) && method === 'DELETE') return ok({ message: 'ok' })
      if (/cases\/1\/documents/.test(url) && method === 'POST') return json({ data: { id: 6, title: 'مذكرة', original_name: 'memo.pdf' }, meta: null, errors: null }, 201)
      if (/cases\/1\/documents/.test(url)) return ok(DOCS)
      return ok([])
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('يعرض الوثيقة باسمها وحجمها', async () => {
    auth(); stub()
    renderWithProviders(<CaseDocumentsSection caseId={1} />)
    expect(await screen.findByText('لائحة الدعوى')).toBeInTheDocument()
    expect(screen.getByText(/laeha\.pdf/)).toBeInTheDocument()
    // لم تعد الواجهة تدّعي أن الرفع غير مدعوم.
    expect(screen.queryByText(/غير مدعوم/)).toBeNull()
  })

  it('يرفع وثيقة بملف عبر POST (multipart)', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseDocumentsSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'إضافة وثيقة' }))
    await user.type(await screen.findByLabelText('العنوان *'), 'مذكرة')
    const file = new File(['data'], 'memo.pdf', { type: 'application/pdf' })
    await user.upload(screen.getByLabelText('الملف *'), file)
    await user.click(screen.getByRole('button', { name: 'رفع' }))
    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/documents/.test(String(c[0])))
      expect(call).toBeTruthy()
      expect(call?.[1]?.body).toBeInstanceOf(FormData)
    })
  })

  it('ينزّل وثيقة عبر endpoint محروس', async () => {
    auth(); const fetchMock = stub()
    vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(() => 'blob:x'), revokeObjectURL: vi.fn() })
    const user = userEvent.setup()
    renderWithProviders(<CaseDocumentsSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'تنزيل' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => /documents\/5\/download/.test(String(c[0])))).toBe(true)
    })
  })

  it('يحذف وثيقة بتأكيد عبر DELETE', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseDocumentsSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'حذف' }))
    await user.click(await screen.findByRole('button', { name: 'تأكيد الحذف' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'DELETE' && /documents\/5$/.test(String(c[0]).split('?')[0]))).toBe(true)
    })
  })
})

describe('CasePartiesSection', () => {
  const PARTIES = [{ id: 3, name: 'شركة الأمل', type: 'plaintiff', phone: '0501234567' }]
  function stub() {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'
      if (/cases\/1\/parties/.test(url) && method === 'POST') return json({ data: { id: 4, name: 'خالد', type: 'witness' }, meta: null, errors: null }, 201)
      if (/cases\/1\/parties/.test(url)) return ok(PARTIES)
      return ok([])
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('يعرض الطرف بصفته، دون أزرار تعديل/حذف', async () => {
    auth(); stub()
    renderWithProviders(<CasePartiesSection caseId={1} />)
    expect(await screen.findByText('شركة الأمل')).toBeInTheDocument()
    expect(screen.getByText('مدّعٍ')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'حذف' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'تعديل' })).toBeNull()
  })

  it('يضيف طرفًا عبر POST', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CasePartiesSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'إضافة طرف' }))
    await user.type(await screen.findByLabelText('الاسم *'), 'خالد')
    await user.click(screen.getByRole('button', { name: 'إضافة' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/parties/.test(String(c[0])))).toBe(true)
    })
  })
})

describe('CaseTimelineSection', () => {
  const EVENTS = [{ id: 7, title: 'إيداع اللائحة', event_type: 'filing', event_date: '2026-05-01', description: 'لدى المحكمة' }]
  function stub() {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'
      if (/cases\/1\/timeline/.test(url) && method === 'POST') return json({ data: { id: 8, title: 'جلسة', event_date: '2026-06-01' }, meta: null, errors: null }, 201)
      if (/cases\/1\/timeline/.test(url)) return ok(EVENTS)
      return ok([])
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('يعرض الحدث مع ملاحظة السجل الدائم، دون تعديل/حذف', async () => {
    auth(); stub()
    renderWithProviders(<CaseTimelineSection caseId={1} />)
    expect(await screen.findByText('إيداع اللائحة')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'حذف' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'تعديل' })).toBeNull()
  })

  it('يضيف حدثًا عبر POST', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseTimelineSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'إضافة حدث' }))
    await user.type(await screen.findByLabelText('العنوان *'), 'جلسة')
    await user.type(screen.getByLabelText('التاريخ *'), '2026-06-01')
    await user.click(screen.getByRole('button', { name: 'إضافة' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/timeline/.test(String(c[0])))).toBe(true)
    })
  })
})

describe('CaseArchiveSection', () => {
  const LOCS = [{ id: 9, file_title: 'ملف القضية الأصلي', archive_room: 'غرفة أ', cabinet: 'خزانة 3', file_number: 'A-12' }]
  function stub() {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'
      if (/archive-locations\/\d+/.test(url) && method === 'DELETE') return ok({ message: 'ok' })
      if (/archive-locations\/\d+/.test(url) && method === 'PUT') return ok({ id: 9, file_title: 'محدّث' })
      if (/cases\/1\/archive-locations/.test(url) && method === 'POST') return json({ data: { id: 10, file_title: 'جديد' }, meta: null, errors: null }, 201)
      if (/cases\/1\/archive-locations/.test(url)) return ok(LOCS)
      return ok([])
    })
    vi.stubGlobal('fetch', fetchMock)
    return fetchMock
  }

  it('يعرض موقع الأرشيف مع تعديل وحذف', async () => {
    auth(); stub()
    renderWithProviders(<CaseArchiveSection caseId={1} />)
    expect(await screen.findByText('ملف القضية الأصلي')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'تعديل' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'حذف' })).toBeInTheDocument()
  })

  it('يضيف موقعًا عبر POST', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseArchiveSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'إضافة موقع' }))
    await user.type(await screen.findByLabelText('عنوان الملف *'), 'جديد')
    await user.click(screen.getByRole('button', { name: 'إضافة' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'POST' && /cases\/1\/archive-locations/.test(String(c[0])))).toBe(true)
    })
  })

  it('يعدّل موقعًا موجودًا عبر PUT', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseArchiveSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'تعديل' }))
    await user.click(await screen.findByRole('button', { name: 'حفظ' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'PUT' && /archive-locations\/9/.test(String(c[0])))).toBe(true)
    })
  })

  it('يحذف موقعًا بتأكيد عبر DELETE', async () => {
    auth(); const fetchMock = stub()
    const user = userEvent.setup()
    renderWithProviders(<CaseArchiveSection caseId={1} />)
    await user.click(await screen.findByRole('button', { name: 'حذف' }))
    await user.click(await screen.findByRole('button', { name: 'تأكيد الحذف' }))
    await waitFor(() => {
      expect(fetchMock.mock.calls.some((c) => (c[1]?.method ?? 'GET') === 'DELETE' && /archive-locations\/9/.test(String(c[0])))).toBe(true)
    })
  })
})
