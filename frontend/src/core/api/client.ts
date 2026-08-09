import { API_BASE_URL } from '@/core/lib/env'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { ApiEnvelope, ApiError } from './types'

/** يُستدعى عند فشل المصادقة نهائياً (401 بعد تعذّر التجديد) — تضبطه طبقة المصادقة. */
let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

interface RequestOptions {
  method?: string
  body?: unknown
  /** جسم خام (مثل FormData) يُرسَل كما هو دون JSON ودون ضبط Content-Type (للرفع). */
  rawBody?: BodyInit
  /** لا تحاول تجديد التوكن (يُستخدم داخلياً لنداء التجديد نفسه). */
  skipRefresh?: boolean
  headers?: Record<string, string>
  /**
   * مهلة الطلب بالملّي ثانية. الافتراضي 20s لنداءات JSON العادية.
   * `null` = بلا مهلة عميل — لنقل الملفات (رفع/تنزيل/تصدير) التي قد تطول
   * مشروعاً على الشبكات البطيئة، فلا تُلغى خطأً. أعطال الشبكة تبقى مُلتقَطة.
   */
  timeoutMs?: number | null
}

/** المهلة القصوى لنداء JSON العادي قبل إلغائه (يُميَّز عن انقطاع الشبكة). */
const REQUEST_TIMEOUT_MS = 20000

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
    // استجابة ليست JSON (صفحة HTML خطأ من البوابة مثلاً) — لا نمرّر المحتوى الخام؛
    // المُخطِّط المركزي يعرض رسالة آمنة لرمز INVALID_JSON.
    return { data: null, meta: null, errors: { code: 'INVALID_JSON' } }
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

/** نداء التجديد الفعلي (يُستدعى مرة واحدة فقط عبر البوابة أدناه). */
async function doRefresh(): Promise<boolean> {
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

/**
 * تجديد التوكن مع «single-flight»: عند وصول عدّة طلبات 401 متزامنة، يُنفَّذ نداء
 * تجديد **واحد** فقط ويتشاركه الجميع. بدون هذا، الطلب الأول يُدوّر (ويُبطِل) الـ
 * refresh token، فتفشل بقيّة نداءات التجديد بالتوكن المُبطَل → تسجيل خروج زائف
 * رغم صحّة الجلسة (سباق حقيقي مع نداءات اللوحات المتوازية).
 */
let refreshInFlight: Promise<boolean> | null = null
function tryRefresh(): Promise<boolean> {
  refreshInFlight ??= doRefresh().finally(() => {
    refreshInFlight = null
  })
  return refreshInFlight
}

async function raw(path: string, options: RequestOptions): Promise<Response> {
  const isJson = options.body !== undefined
  const headers: Record<string, string> = {
    Accept: 'application/json',
    // FormData يضبط Content-Type (مع الحدّ) تلقائياً — لا نضبطه للـ rawBody.
    ...(isJson ? { 'Content-Type': 'application/json' } : {}),
    ...options.headers,
  }
  const token = tokenStorage.accessToken()
  if (token) headers.Authorization = `Bearer ${token}`

  // مهلة عبر AbortController: عند تجاوزها نُلغي الطلب ونُطبّع الخطأ إلى مهلة واضحة،
  // ونُميّز انقطاع الشبكة (TypeError) عن المهلة — كلاهما ApiError برمز موحّد.
  // نقل الملفات (timeoutMs=null) بلا مهلة عميل كي لا تُلغى الطلبات الطويلة المشروعة.
  const timeoutMs = options.timeoutMs === undefined ? REQUEST_TIMEOUT_MS : options.timeoutMs
  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | null = null
  if (timeoutMs !== null) {
    controller = new AbortController()
    const c = controller
    timer = setTimeout(() => c.abort(new DOMException('timeout', 'TimeoutError')), timeoutMs)
  }
  try {
    return await fetch(buildUrl(path), {
      method: options.method ?? 'GET',
      headers,
      body: isJson ? JSON.stringify(options.body) : options.rawBody,
      signal: controller?.signal,
    })
  } catch (e) {
    if (e instanceof DOMException && (e.name === 'TimeoutError' || e.name === 'AbortError')) {
      throw new ApiError(408, 'TIMEOUT', 'استغرق الطلب وقتاً طويلاً. تحقّق من اتصالك وحاول مرة أخرى.')
    }
    // فشل الشبكة (TypeError: Failed to fetch) أو انقطاع الإنترنت.
    throw new ApiError(0, 'NETWORK_ERROR', 'تعذّر الاتصال بالخادم. تحقق من اتصال الإنترنت وحاول مرة أخرى.')
  } finally {
    if (timer) clearTimeout(timer)
  }
}

/** ينفّذ النداء ويجدّد التوكن مرة واحدة عند 401 (ويسجّل خروجاً إن تعذّر). */
async function requestWithRefresh(path: string, options: RequestOptions): Promise<Response> {
  let res = await raw(path, options)

  if (res.status === 401 && !options.skipRefresh) {
    const refreshed = await tryRefresh()
    if (refreshed) {
      res = await raw(path, { ...options, skipRefresh: true })
    }
    if (res.status === 401) {
      tokenStorage.clear()
      onUnauthorized?.()
    }
  }

  return res
}

/** نداء API يعيد `data` من الغلاف الموحّد (JSON). */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const res = await requestWithRefresh(path, options)
  const env = await parse<T>(res)
  if (!res.ok) throw toError(res.status, env)
  return env.data as T
}

/** نداء يعيد الغلاف كاملاً `{data, meta, errors}` — يُستخدم عند الحاجة إلى meta (الترقيم). */
export async function apiRequestEnvelope<T>(path: string, options: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  const res = await requestWithRefresh(path, options)
  const env = await parse<T>(res)
  if (!res.ok) throw toError(res.status, env)
  return env
}

/** نداء يعيد نصاً خاماً (مثل مستند HTML) بمصادقة — يرمي ApiError عند الفشل. */
export async function apiText(path: string): Promise<string> {
  // بلا مهلة عميل: قد يطول توليد/تنزيل المستند مشروعاً على الشبكات البطيئة.
  const res = await requestWithRefresh(path, { method: 'GET', headers: { Accept: 'text/html' }, timeoutMs: null })
  if (!res.ok) {
    throw toError(res.status, await parse<unknown>(res))
  }
  return res.text()
}

/** نداء يعيد Blob بمصادقة (لتنزيل الملفات مثل Excel) — يرمي ApiError عند الفشل. */
export async function apiBlob(path: string): Promise<Blob> {
  // بلا مهلة عميل: تصدير/تنزيل الملفات قد يطول مشروعاً — لا نُلغيه خطأً.
  const res = await requestWithRefresh(path, {
    method: 'GET',
    headers: { Accept: 'application/octet-stream' },
    timeoutMs: null,
  })
  if (!res.ok) {
    throw toError(res.status, await parse<unknown>(res))
  }
  return res.blob()
}

/** رفع ملف (FormData) بمصادقة — يعيد `data` من الغلاف، ويرمي ApiError عند الفشل. */
export async function apiUpload<T>(path: string, form: FormData): Promise<T> {
  // بلا مهلة عميل: رفع الملفات قد يطول مشروعاً على الشبكات البطيئة — لا نُلغيه خطأً.
  const res = await requestWithRefresh(path, { method: 'POST', rawBody: form, timeoutMs: null })
  const env = await parse<T>(res)
  if (!res.ok) throw toError(res.status, env)
  return env.data as T
}

export const api = {
  get: <T>(path: string) => apiRequest<T>(path, { method: 'GET' }),
  /** GET يعيد الغلاف كاملاً (data + meta) — للقوائم المرقّمة. */
  getPage: <T>(path: string) => apiRequestEnvelope<T>(path, { method: 'GET' }),
  post: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'POST', body }),
  patch: <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'PATCH', body }),
  text: (path: string) => apiText(path),
  blob: (path: string) => apiBlob(path),
  upload: <T>(path: string, form: FormData) => apiUpload<T>(path, form),
}
