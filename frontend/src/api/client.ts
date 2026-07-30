import { API_BASE_URL } from '../lib/env'
import { tokenStorage } from '../auth/tokenStorage'
import { ApiEnvelope, ApiError } from './types'

/** يُستدعى عند فشل المصادقة نهائياً (401 بعد تعذّر التجديد) — تضبطه طبقة المصادقة. */
let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

interface RequestOptions {
  method?: string
  body?: unknown
  /** لا تحاول تجديد التوكن (يُستخدم داخلياً لنداء التجديد نفسه). */
  skipRefresh?: boolean
  headers?: Record<string, string>
}

function buildUrl(path: string): string {
  const base = API_BASE_URL.replace(/\/$/, '')
  return `${base}/${path.replace(/^\//, '')}`
}

async function parse<T>(res: Response): Promise<ApiEnvelope<T>> {
  const text = await res.text()
  if (!text) return { data: null, meta: null, errors: null }
  try {
    return JSON.parse(text) as ApiEnvelope<T>
  } catch {
    return { data: null, meta: null, errors: { code: 'INVALID_JSON', message: text } }
  }
}

function toError(status: number, env: ApiEnvelope<unknown>): ApiError {
  const err = env.errors
  return new ApiError(
    status,
    err?.code ?? `HTTP_${status}`,
    err?.message ?? 'حدث خطأ غير متوقّع.',
    err?.fields,
  )
}

/** يحاول تجديد التوكن مرة واحدة عبر refresh_token. يعيد true عند النجاح. */
async function tryRefresh(): Promise<boolean> {
  const tokens = tokenStorage.get()
  if (!tokens?.refresh_token) return false

  const res = await fetch(buildUrl('auth/refresh'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ refresh_token: tokens.refresh_token }),
  })
  if (!res.ok) return false

  const env = await parse<{ tokens: typeof tokens }>(res)
  if (!env.data?.tokens?.access_token) return false

  tokenStorage.set(env.data.tokens)
  return true
}

async function raw(path: string, options: RequestOptions): Promise<Response> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(options.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
    ...options.headers,
  }
  const token = tokenStorage.accessToken()
  if (token) headers.Authorization = `Bearer ${token}`

  return fetch(buildUrl(path), {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })
}

/** نداء API موحّد: يرفق التوكن، يعالج الغلاف، ويجدّد التوكن مرة عند 401. */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  let res = await raw(path, options)

  // 401: حاول التجديد مرة واحدة ثم أعد المحاولة؛ وإلا سجّل خروجاً.
  if (res.status === 401 && !options.skipRefresh) {
    const refreshed = await tryRefresh()
    if (refreshed) {
      res = await raw(path, { ...options, skipRefresh: true })
    }
    if (res.status === 401) {
      tokenStorage.clear()
      onUnauthorized?.()
      const env = await parse<T>(res)
      throw toError(401, env)
    }
  }

  const env = await parse<T>(res)
  if (!res.ok) throw toError(res.status, env)
  return env.data as T
}

export const api = {
  get: <T>(path: string) => apiRequest<T>(path, { method: 'GET' }),
  post: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'POST', body }),
  patch: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'PATCH', body }),
}
