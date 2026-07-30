import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '../auth/tokenStorage'
import { api, apiRequest, setUnauthorizedHandler } from './client'
import { ApiError } from './types'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

afterEach(() => {
  vi.restoreAllMocks()
  setUnauthorizedHandler(null)
})

describe('apiRequest', () => {
  it('يرفق التوكن ويعيد data من الغلاف', async () => {
    tokenStorage.set({ access_token: 'tok', refresh_token: 'r' })
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { value: 42 }, meta: null, errors: null }))
    vi.stubGlobal('fetch', fetchMock)

    const data = await api.get<{ value: number }>('me/dashboard')

    expect(data).toEqual({ value: 42 })
    const headers = fetchMock.mock.calls[0][1].headers
    expect(headers.Authorization).toBe('Bearer tok')
  })

  it('يرمي ApiError مع الرمز عند 403 (حساب غير مرتبط)', async () => {
    tokenStorage.set({ access_token: 'tok', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ data: null, meta: null, errors: { code: 'NO_LINKED_EMPLOYEE', message: 'غير مرتبط' } }, 403),
      ),
    )

    const err = (await api.get('me/dashboard').catch((e) => e)) as ApiError
    expect(err).toBeInstanceOf(ApiError)
    expect(err.status).toBe(403)
    expect(err.isForbidden).toBe(true)
    expect(err.isNotLinkedEmployee).toBe(true)
  })

  it('يجدّد التوكن عند 401 ثم يعيد المحاولة بنجاح', async () => {
    tokenStorage.set({ access_token: 'old', refresh_token: 'r' })
    const fetchMock = vi.fn().mockImplementation((url: string, init: RequestInit) => {
      const method = init.method ?? 'GET'
      if (url.endsWith('/auth/refresh')) {
        return Promise.resolve(jsonResponse({ data: { tokens: { access_token: 'new', refresh_token: 'r2' } }, meta: null, errors: null }))
      }
      const auth = (init.headers as Record<string, string>).Authorization
      if (auth === 'Bearer old' && method === 'GET') return Promise.resolve(jsonResponse({ errors: { code: 'UNAUTH' } }, 401))
      return Promise.resolve(jsonResponse({ data: { ok: true }, meta: null, errors: null }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const data = await api.get<{ ok: boolean }>('me/attendance')

    expect(data).toEqual({ ok: true })
    expect(tokenStorage.accessToken()).toBe('new') // خُزّن التوكن الجديد
  })

  it('يسجّل خروجاً عند فشل التجديد على 401', async () => {
    tokenStorage.set({ access_token: 'old', refresh_token: 'r' })
    const onUnauthorized = vi.fn()
    setUnauthorizedHandler(onUnauthorized)
    vi.stubGlobal(
      'fetch',
      vi.fn().mockImplementation((url: string) =>
        url.endsWith('/auth/refresh')
          ? Promise.resolve(jsonResponse({ errors: { code: 'INVALID' } }, 401))
          : Promise.resolve(jsonResponse({ errors: { code: 'UNAUTH' } }, 401)),
      ),
    )

    const err = (await apiRequest('me/leave/balance').catch((e) => e)) as ApiError

    expect(err).toBeInstanceOf(ApiError)
    expect(err.status).toBe(401)
    expect(onUnauthorized).toHaveBeenCalledOnce()
    expect(tokenStorage.get()).toBeNull() // مُسحت التوكنات
  })
})
