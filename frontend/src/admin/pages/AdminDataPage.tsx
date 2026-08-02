import { useRef, useState, type ChangeEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  EXPORT_ENTITIES,
  type ExportEntity,
  type ImportPreview,
  commitEmployeesImport,
  downloadExport,
  previewEmployeesImport,
} from '@/admin/api/data'

/**
 * إدارة البيانات (Excel) — تصدير أي كيان إلى Excel، واستيراد الموظفين (معاينة ثم تأكيد).
 * قاعدة البيانات هي المرجع؛ Excel للاستيراد الأوّلي والتقارير فقط.
 */
export function AdminDataPage() {
  const { show } = useToast()
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const exportMut = useMutation({
    mutationFn: (entity: ExportEntity) => downloadExport(entity),
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر التصدير', 'error'),
  })

  const previewMut = useMutation({
    mutationFn: (f: File) => previewEmployeesImport(f),
    onSuccess: (data) => setPreview(data),
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت المعاينة', 'error'),
  })

  const commitMut = useMutation({
    mutationFn: (f: File) => commitEmployeesImport(f),
    onSuccess: (r) => {
      show(`تمّ الاستيراد: ${r.created} جديد، ${r.updated} محدّث`)
      resetImport()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الاستيراد', 'error'),
  })

  function resetImport() {
    setFile(null)
    setPreview(null)
    if (fileRef.current) fileRef.current.value = ''
  }

  function onPick(e: ChangeEvent<HTMLInputElement>) {
    setFile(e.target.files?.[0] ?? null)
    setPreview(null)
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="إدارة البيانات (Excel)"
        subtitle="التصدير إلى Excel لأي كيان، واستيراد الموظفين من ملف. قاعدة البيانات هي المرجع الرسمي."
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

      <SectionCard title="استيراد الموظفين">
        <p className="mb-4 text-sm text-slate-500">
          ارفع ملف Excel (نفس أعمدة تصدير الموظفين). عايِن أولاً، ثم أكّد. الاستيراد ذرّي — صفّ واحد
          غير صحيح يمنع الحفظ كاملاً. المطابقة على <code>employee_no</code> (تحديث أو إضافة).
        </p>

        <div className="flex flex-wrap items-center gap-3">
          <input
            ref={fileRef}
            type="file"
            accept=".xlsx,.xls"
            onChange={onPick}
            aria-label="ملف الموظفين"
            className="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-brand-700"
          />
          <Button
            disabled={!file || previewMut.isPending}
            onClick={() => file && previewMut.mutate(file)}
          >
            معاينة
          </Button>
          <Button
            variant="ghost"
            disabled={!file || !preview || preview.invalid > 0 || commitMut.isPending}
            onClick={() => file && commitMut.mutate(file)}
          >
            تأكيد الاستيراد
          </Button>
        </div>

        {preview && (
          <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <div className="flex flex-wrap gap-x-6 gap-y-1">
              <span>الإجمالي: <b>{preview.total}</b></span>
              <span className="text-emerald-700">إضافة: <b>{preview.create}</b></span>
              <span className="text-brand-700">تحديث: <b>{preview.update}</b></span>
              <span className={preview.invalid > 0 ? 'text-red-700' : 'text-slate-500'}>
                غير صحيح: <b>{preview.invalid}</b>
              </span>
            </div>

            {preview.invalid > 0 && (
              <div className="mt-3">
                <p className="mb-1 font-medium text-red-700">صفوف تحتاج تصحيحًا قبل الاستيراد:</p>
                <ul className="max-h-48 space-y-1 overflow-y-auto">
                  {preview.errors.map((err) => (
                    <li key={err.row} className="text-red-700">
                      صف {err.row}: {err.message}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}
      </SectionCard>
    </div>
  )
}
