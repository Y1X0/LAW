import { useEffect, useRef, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { SETTINGS_GROUPS, fetchSettings, settingsValues, updateSettings } from '@/admin/api/settings'
import { ApiError } from '@/core/api/types'

/**
 * إعدادات المنصّة (ADMIN-5) — تحرير أقسام الإعدادات المُعرَّفة في SETTINGS_GROUPS عبر
 * النقاط القائمة `GET/PUT /admin/settings` (تعيد استخدام جدول settings الموجود). كل الأقسام
 * تُحفَظ دفعةً واحدة (الخادم يقبل دفعة). إضافة قسم = تعديل الإعداد فقط، بلا نموذج/قاعدة جديدة.
 */
export function AdminSettingsPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [values, setValues] = useState<Record<string, string>>({})

  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: fetchSettings,
  })

  // يملأ النموذج مرّة واحدة عند أوّل وصول للبيانات فقط — كي لا تمسح إعادة الجلب
  // الخلفية (عند تبديل النافذة) ما يكتبه المستخدم قبل الحفظ.
  const hydrated = useRef(false)
  useEffect(() => {
    if (data && !hydrated.current) {
      setValues(settingsValues(data))
      hydrated.current = true
    }
  }, [data])

  const save = useMutation({
    mutationFn: () =>
      updateSettings(
        SETTINGS_GROUPS.flatMap((g) => g.fields.map((f) => ({ group: g.group, key: f.key, value: values[f.key] ?? '' }))),
      ),
    onSuccess: () => {
      show('تم حفظ الإعدادات')
      void qc.invalidateQueries({ queryKey: ['admin', 'settings'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حفظ الإعدادات', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <div className="space-y-5">
      <PageHeader title="الإعدادات" subtitle="إعدادات المنصّة" />

      {isPending ? (
        <Card>
          <div data-testid="settings-skeleton" className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full" />
            ))}
          </div>
        </Card>
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : (
        <form onSubmit={onSubmit} className="space-y-5">
          {SETTINGS_GROUPS.map((g) => (
            <SectionCard key={g.group} title={g.title}>
              <div className="space-y-3">
                {g.fields.map((f) => (
                  <Field
                    key={f.key}
                    label={f.label}
                    value={values[f.key] ?? ''}
                    onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
                  />
                ))}
              </div>
            </SectionCard>
          ))}
          <div className="flex justify-end">
            <Button type="submit" disabled={save.isPending}>
              {save.isPending ? 'جارٍ…' : 'حفظ'}
            </Button>
          </div>
        </form>
      )}
    </div>
  )
}
