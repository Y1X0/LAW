import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Field } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { GENERAL_FIELDS, fetchSettings, generalValues, updateSettings } from '@/admin/api/settings'
import { ApiError } from '@/core/api/types'

/**
 * إعدادات المنصّة العامّة (ADMIN-5) — تحرير مجموعة «general» عبر النقاط القائمة
 * `GET/PUT /admin/settings` (تعيد استخدام جدول settings الموجود).
 */
export function AdminSettingsPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [values, setValues] = useState<Record<string, string>>({})

  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: fetchSettings,
  })

  // يملأ النموذج بقيم المجموعة العامّة عند وصولها.
  useEffect(() => {
    if (data) setValues(generalValues(data))
  }, [data])

  const save = useMutation({
    mutationFn: () =>
      updateSettings(
        GENERAL_FIELDS.map((f) => ({ group: 'general', key: f.key, value: values[f.key] ?? '' })),
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
      <PageHeader title="الإعدادات العامّة" subtitle="إعدادات المنصّة" />

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
        <SectionCard title="عام">
          <form onSubmit={onSubmit} className="space-y-3">
            {GENERAL_FIELDS.map((f) => (
              <Field
                key={f.key}
                label={f.label}
                value={values[f.key] ?? ''}
                onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
              />
            ))}
            <div className="flex justify-end pt-1">
              <Button type="submit" disabled={save.isPending}>
                {save.isPending ? 'جارٍ…' : 'حفظ'}
              </Button>
            </div>
          </form>
        </SectionCard>
      )}
    </div>
  )
}
