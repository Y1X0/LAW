import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { probeIsLawyer } from './probe'

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

  it('يُعيد false عند خطأ شبكة', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network')))
    expect(await probeIsLawyer()).toBe(false)
  })
})
