import { useMutation } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { EXPORT_ENTITIES, type ExportEntity, downloadExport } from '@/admin/api/data'
import { ImportCenter } from '@/admin/pages/components/ImportCenter'

/**
 * إدارة البيانات — تصدير أي كيان إلى Excel، ومركز استيراد عامّ (رفع → معاينة → مطابقة
 * أعمدة → تأكيد). قاعدة البيانات هي المرجع؛ Excel للهجرة الأوّلية والتقارير فقط.
 */
export function AdminDataPage() {
  const { show } = useToast()

  const exportMut = useMutation({
    mutationFn: (entity: ExportEntity) => downloadExport(entity),
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر التصدير', 'error'),
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="إدارة البيانات"
        subtitle="مركز استيراد البيانات من Excel، وتصدير أي كيان. قاعدة البيانات هي المرجع الرسمي."
      />

      <SectionCard title="تصدير Excel">
        <p className="mb-4 text-sm text-slate-500">
          ينزّل ملف Excel بآخر البيانات. الحقول المالية للموظفين تظهر فقط لمن يملك صلاحية عرض الرواتب.
        </p>
        <div className="flex flex-wrap gap-2">
          {EXPORT_ENTITIES.map((entity) => (
            <Button
              key={entity.key}
              variant="ghost"
              disabled={exportMut.isPending}
              onClick={() => exportMut.mutate(entity.key)}
            >
              تصدير: {entity.label}
            </Button>
          ))}
        </div>
      </SectionCard>

      <ImportCenter />
    </div>
  )
}
