import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  type CustomField,
  deleteCustomField,
  fetchCustomFieldMeta,
  fetchCustomFields,
} from '@/admin/api/customFields'
import { CustomFieldFormModal } from '@/admin/pages/components/CustomFieldFormModal'

/**
 * بنّاء الحقول المخصّصة (Phase 12 · PR-2) — محرّك تخصيص: يعرّف المدير حقولاً إضافية لكل كيان
 * دون تعديل كود. الأنواع/السياقات/الأدوار/الكيانات كلها من meta الخادم. تعريف فقط — لا قيم بعد.
 */
export function AdminCustomFieldsPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [entity, setEntity] = useState<string>('')
  const [editing, setEditing] = useState<CustomField | null | 'new'>(null)

  const meta = useQuery({ queryKey: ['admin', 'custom-fields', 'meta'], queryFn: fetchCustomFieldMeta })

  // أوّل كيان معروض يصبح الافتراضي بمجرّد وصول meta.
  useEffect(() => {
    const first = meta.data?.entities?.[0]?.key
    if (entity === '' && first) setEntity(first)
  }, [meta.data, entity])

  const fields = useQuery({
    queryKey: ['admin', 'custom-fields', entity],
    queryFn: () => fetchCustomFields(entity),
    enabled: entity !== '',
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteCustomField(id),
    onSuccess: () => {
      show('تم حذف الحقل')
      void qc.invalidateQueries({ queryKey: ['admin', 'custom-fields'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحذف', 'error'),
  })

  const typeLabel = (key: string) => meta.data?.types.find((t) => t.key === key)?.label ?? key

  return (
    <div className="space-y-6">
      <PageHeader
        title="الحقول المخصّصة"
        subtitle="عرّف حقولاً إضافية لكل كيان دون تعديل برمجي. تعريف فقط — تظهر القيمة داخل الكيان لاحقاً."
      />

      {meta.isPending ? (
        <Skeleton className="h-24 w-full" />
      ) : meta.isError ? (
        <ErrorState error={meta.error}>
          <div className="mt-3"><Button onClick={() => void meta.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : (
        <SectionCard
          title="حقول الكيان"
          action={
            <Button disabled={entity === ''} onClick={() => setEditing('new')}>إضافة حقل</Button>
          }
        >
          <div className="mb-4 max-w-xs">
            <SelectField label="الكيان" value={entity} onChange={(e) => setEntity(e.target.value)}>
              {meta.data.entities.map((en) => (
                <option key={en.key} value={en.key}>{en.label}</option>
              ))}
            </SelectField>
          </div>

          {fields.isPending ? (
            <div className="space-y-2" data-testid="fields-skeleton">
              {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : fields.isError ? (
            <ErrorState error={fields.error}>
              <div className="mt-3"><Button onClick={() => void fields.refetch()}>إعادة المحاولة</Button></div>
            </ErrorState>
          ) : fields.data && fields.data.length === 0 ? (
            <EmptyState message="لا توجد حقول مخصّصة بعد. أضِف أوّل حقل." />
          ) : (
            <ul className="space-y-2.5">
              {fields.data?.map((f) => (
                <li key={f.id}>
                  <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-slate-800">{f.label}</span>
                        <Badge tone="slate">{typeLabel(f.type)}</Badge>
                        {f.required && <Badge tone="navy">إلزامي</Badge>}
                        {!f.is_active && <Badge tone="amber">معطّل</Badge>}
                      </div>
                      <div className="mt-0.5 text-xs text-slate-400">
                        {f.key}{f.description ? ` · ${f.description}` : ''}
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Button variant="ghost" onClick={() => setEditing(f)}>تعديل</Button>
                      <Button
                        variant="ghost"
                        disabled={remove.isPending}
                        onClick={() => { if (confirm(`حذف الحقل «${f.label}»؟`)) remove.mutate(f.id) }}
                      >
                        حذف
                      </Button>
                    </div>
                  </Card>
                </li>
              ))}
            </ul>
          )}
        </SectionCard>
      )}

      {editing !== null && meta.data && (
        <CustomFieldFormModal
          field={editing === 'new' ? null : editing}
          entity={entity}
          meta={meta.data}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  )
}
