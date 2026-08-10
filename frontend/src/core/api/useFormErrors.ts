import { useCallback, useState } from 'react'
import { useToast } from '@/core/ui/useToast'
import { mapApiError, type MappedError } from './mapApiError'

export interface FormErrorsApi {
  /** خطأ واحد واضح لكل حقل (يُربَط بـ Field/SelectField عبر fieldError). */
  fieldErrors: Record<string, string>
  /** الرسالة العامّة الحالية (أو null) — تُعرض أيضاً في التوست. */
  formError: string | null
  /** آخر خطأ مُخطَّط بالكامل (status/code/kind) عند الحاجة لتمييز إضافي. */
  mapped: MappedError | null
  /** معالج جاهز لتمريره إلى useMutation({ onError }) — يعرض توستاً ويملأ أخطاء الحقول. */
  onError: (error: unknown) => void
  /** رسالة خطأ حقلٍ بعينه (للتمرير إلى prop error). */
  fieldError: (name: string) => string | undefined
  /** تصفير الأخطاء (يُستدعى قبل كل إرسال). */
  reset: () => void
}

/**
 * المعالج المركزي الموحّد لأخطاء النماذج/الإجراءات في كامل الواجهة.
 * يعتمد المُخطِّط المركزي mapApiError: يعرض رسالة عامّة واضحة في التوست (لا فشل صامت)،
 * ويملأ أخطاء الحقول لعرضها تحت الحقول نفسها (422). يُستبدَل به نمط
 * «onError: () => show("رسالة ثابتة")» الذي كان يتجاهل رسالة الخادم.
 */
export function useFormErrors(): FormErrorsApi {
  const { show } = useToast()
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [mapped, setMapped] = useState<MappedError | null>(null)

  const reset = useCallback(() => {
    setFieldErrors({})
    setMapped(null)
  }, [])

  const onError = useCallback(
    (error: unknown) => {
      const result = mapApiError(error)
      setFieldErrors(result.fieldErrors)
      setMapped(result)
      // التوست يحمل الرسالة العامّة دائماً؛ تفاصيل الحقول تظهر تحت الحقول.
      show(result.formMessage, 'error')
    },
    [show],
  )

  const fieldError = useCallback((name: string) => fieldErrors[name], [fieldErrors])

  return {
    fieldErrors,
    formError: mapped?.formMessage ?? null,
    mapped,
    onError,
    fieldError,
    reset,
  }
}
