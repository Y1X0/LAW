import { describe, expect, it } from 'vitest'
import { mapApiError } from './mapApiError'
import { ApiError } from './types'

describe('mapApiError', () => {
  it('422 متعدد الحقول: يوزّع أوّل رسالة لكل حقل ويرشد للتحقّق', () => {
    const err = new ApiError(422, 'VALIDATION_ERROR', 'بيانات غير صحيحة.', {
      email: ['يجب أن يكون البريد الإلكتروني بريداً إلكترونياً صحيحاً.'],
      password: ['يجب أن تحتوي كلمة المرور على رمز واحد على الأقل.', 'رسالة ثانية تُتجاهَل'],
    })

    const m = mapApiError(err)

    expect(m.kind).toBe('validation')
    expect(m.hasFieldErrors).toBe(true)
    expect(m.fieldErrors.email).toContain('البريد الإلكتروني')
    expect(m.fieldErrors.password).toContain('رمز واحد')
    expect(m.formMessage).toBe('بيانات غير صحيحة.')
  })

  it('422 بمفاتيح متداخلة: يضيف مفتاح الأب (role_ids.0 → role_ids)', () => {
    const err = new ApiError(422, 'VALIDATION_ERROR', '', {
      'role_ids.0': ['القيمة المحدّدة للدور غير موجودة.'],
    })

    const m = mapApiError(err)

    const msg = 'القيمة المحدّدة للدور غير موجودة.'
    expect(m.fieldErrors['role_ids.0']).toBe(msg)
    expect(m.fieldErrors.role_ids).toBe(msg) // مربوط بالحقل المجمّع
    expect(m.formMessage).toBe('تحقّق من الحقول المميّزة بالأحمر.') // لا رسالة خادم → افتراضي 422
  })

  it('409: يُبقي رسالة الخادم ويصنّفها conflict', () => {
    const err = new ApiError(409, 'CONFLICT_DUPLICATE', 'القيمة المُدخلة مستخدمة مسبقاً.')
    const m = mapApiError(err)
    expect(m.kind).toBe('conflict')
    expect(m.hasFieldErrors).toBe(false)
    expect(m.formMessage).toBe('القيمة المُدخلة مستخدمة مسبقاً.')
  })

  it('403: رسالة صلاحية واضحة', () => {
    const err = new ApiError(403, 'FORBIDDEN', 'لا تملك صلاحية تنفيذ هذا الإجراء.')
    const m = mapApiError(err)
    expect(m.kind).toBe('forbidden')
    expect(m.formMessage).toContain('صلاحية')
  })

  it('404: رسالة عنصر غير موجود', () => {
    const m = mapApiError(new ApiError(404, 'HTTP_404', ''))
    expect(m.kind).toBe('notfound')
    expect(m.formMessage).toContain('غير موجود')
  })

  it('429: رسالة محاولات كثيرة', () => {
    const m = mapApiError(new ApiError(429, 'HTTP_429', ''))
    expect(m.kind).toBe('ratelimited')
    expect(m.formMessage).toContain('محاولات كثيرة')
  })

  it('انقطاع الشبكة (ApiError موحّد): رسالة اتصال واضحة', () => {
    const m = mapApiError(new ApiError(0, 'NETWORK_ERROR', 'x'))
    expect(m.kind).toBe('offline')
    expect(m.formMessage).toContain('تعذّر الاتصال بالخادم')
  })

  it('المهلة: رسالة مختلفة عن الانقطاع', () => {
    const m = mapApiError(new ApiError(408, 'TIMEOUT', 'x'))
    expect(m.kind).toBe('timeout')
    expect(m.formMessage).toContain('وقتاً طويلاً')
    expect(m.formMessage).not.toBe(mapApiError(new ApiError(0, 'NETWORK_ERROR', 'x')).formMessage)
  })

  it('استجابة غير JSON (HTML): لا تُعرض خام بل رسالة آمنة', () => {
    const m = mapApiError(new ApiError(500, 'INVALID_JSON', '<!DOCTYPE html><h1>500</h1>'))
    expect(m.formMessage).not.toContain('DOCTYPE')
    expect(m.formMessage).toContain('استجابة الخادم')
  })

  it('5xx: رسالة خادم عامّة', () => {
    const m = mapApiError(new ApiError(500, 'SERVER_ERROR', 'حدث خطأ غير متوقّع.'))
    expect(m.kind).toBe('server')
    expect(m.formMessage).toBe('حدث خطأ غير متوقّع.')
  })

  it('TypeError خام (fetch): يُعامَل كانقطاع شبكة', () => {
    const m = mapApiError(new TypeError('Failed to fetch'))
    expect(m.kind).toBe('offline')
  })

  it('AbortError خام: يُعامَل كمهلة', () => {
    const m = mapApiError(new DOMException('aborted', 'AbortError'))
    expect(m.kind).toBe('timeout')
  })

  it('خطأ غير معروف: رسالة عامّة آمنة', () => {
    const m = mapApiError(new Error('boom'))
    expect(m.kind).toBe('unknown')
    expect(m.formMessage).toContain('تعذّرت العملية')
  })
})
