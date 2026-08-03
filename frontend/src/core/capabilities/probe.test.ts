import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { probeCanManageHr, probeCanManageLegal, probeCanManagePayroll, probeIsAdmin, probeIsLawyer } from './probe'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

describe('probeIsLawyer', () => {
  it('يُعيد true عند 200 مع data غير فارغة (محامٍ)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: { cases: { total: 3 } }, meta: null, errors: null })))
    expect(await probeIsLawyer()).toBe(true)
  })

  it('يُعيد false عند data=null (ليس محامياً)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: null })))
    expect(await probeIsLawyer()).toBe(false)
  })

  it('يُعيد false عند 403 (تدرّج آمن)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))
    expect(await probeIsLawyer()).toBe(false)
  })

  it('يرمي عند خطأ شبكة (ليعيد React Query المحاولة)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    await expect(probeIsLawyer()).rejects.toThrow()
  })
})

describe('probeCanManageHr', () => {
  it('يُعيد true عند 200 ولو بمصفوفة فارغة (يملك employees.view)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: [], meta: { total: 0 }, errors: null })))
    expect(await probeCanManageHr()).toBe(true)
  })

  it('يُعيد false عند data=null (لا وصول إداري)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: null })))
    expect(await probeCanManageHr()).toBe(false)
  })

  it('يُعيد false عند 403 (تدرّج آمن)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))
    expect(await probeCanManageHr()).toBe(false)
  })

  it('يرمي عند خطأ شبكة (ليعيد React Query المحاولة)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    await expect(probeCanManageHr()).rejects.toThrow()
  })
})

describe('probeIsAdmin', () => {
  it('يُعيد true عند 200 على /roles (يملك roles.manage)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: [{ id: 1, name: 'admin' }], meta: null, errors: null })))
    expect(await probeIsAdmin()).toBe(true)
  })

  it('يُعيد false عند 403 (ليس Admin)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))
    expect(await probeIsAdmin()).toBe(false)
  })

  it('يرمي عند خطأ شبكة (ليعيد React Query المحاولة)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    await expect(probeIsAdmin()).rejects.toThrow()
  })
})

describe('probeCanManagePayroll', () => {
  it('يُعيد true عند 200 ولو بمصفوفة فارغة (يملك payroll.view)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: [], meta: { total: 0 }, errors: null })))
    expect(await probeCanManagePayroll()).toBe(true)
  })

  it('يُعيد false عند 403 (لا وصول للرواتب)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))
    expect(await probeCanManagePayroll()).toBe(false)
  })

  it('يرمي عند خطأ شبكة (ليعيد React Query المحاولة)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    await expect(probeCanManagePayroll()).rejects.toThrow()
  })
})

describe('probeCanManageLegal', () => {
  it('يُعيد true عند 200 (يملك clients.view الإدارية)', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: [], meta: { total: 0 }, errors: null })))
    expect(await probeCanManageLegal()).toBe(true)
  })

  it('يُعيد false عند 403 (محامٍ/غير إداري)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, meta: null, errors: { code: 'FORBIDDEN' } }, 403)))
    expect(await probeCanManageLegal()).toBe(false)
  })

  it('يرمي عند خطأ شبكة (ليعيد React Query المحاولة)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    await expect(probeCanManageLegal()).rejects.toThrow()
  })
})
