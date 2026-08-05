import { useEffect, useRef, useState, type ChangeEvent } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Button, SelectField } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { type ImportPreview, commitImport, fetchImportManifest, previewImport } from '@/admin/api/data'

/**
 * منصّة هجرة البيانات (Phase 11 · PR-2) — مركز موحّد لاستيراد أي كيان بنفس الخطوات:
 * اختيار النوع → رفع → معاينة → مطابقة الأعمدة + مفتاح المطابقة → تأكيد → نتيجة.
 * قائمة الأنواع تأتي من سِجلّ الخادم (مصفّى بالصلاحيات)؛ إضافة مستورد جديد تظهر تلقائياً.
 * لا منطق Excel في الواجهة: الخادم يحلّل ويقرّر ويعيد detected_headers/fields للعرض فقط.
 */
export function ImportCenter() {
  const { show } = useToast()
  const [entityKey, setEntityKey] = useState<string>('')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [mapping, setMapping] = useState<Record<string, string>>({})
  const [matchKey, setMatchKey] = useState<string>('')
  const fileRef = useRef<HTMLInputElement>(null)

  const manifestQuery = useQuery({ queryKey: ['import-manifest'], queryFn: fetchImportManifest })
  const entities = manifestQuery.data ?? []

  // أوّل نوع متاح يصبح الافتراضي بمجرّد وصول السِجلّ (دون تثبيت نوع في الواجهة).
  useEffect(() => {
    const first = manifestQuery.data?.[0]?.key
    if (entityKey === '' && first) setEntityKey(first)
  }, [manifestQuery.data, entityKey])

  const entity = entities.find((e) => e.key === entityKey)
  const supportsMapping = entity?.mapping ?? false

  function resetImport() {
    setFile(null)
    setPreview(null)
    setMapping({})
    setMatchKey('')
    if (fileRef.current) fileRef.current.value = ''
  }

  const options = () => (supportsMapping ? { mapping, matchKey: matchKey || undefined } : undefined)

  const previewMut = useMutation({
    mutationFn: () => previewImport(entityKey, file!, options()),
    onSuccess: (data) => {
      setPreview(data)
      // تهيئة المطابقة أوّل مرّة: يُختار الرأس المطابق تماماً لاسم الحقل إن وُجد.
      if (data.fields && Object.keys(mapping).length === 0) {
        const init: Record<string, string> = {}
        for (const f of data.fields) init[f.key] = data.detected_headers?.includes(f.key) ? f.key : ''
        setMapping(init)
      }
      if (data.match_keys && matchKey === '') setMatchKey(data.match_keys[0] ?? '')
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت المعاينة', 'error'),
  })

  const commitMut = useMutation({
    mutationFn: () => commitImport(entityKey, file!, options()),
    onSuccess: (r) => {
      show(`تمّ الاستيراد: ${r.created} جديد، ${r.updated} محدّث`)
      resetImport()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الاستيراد', 'error'),
  })

  function onEntity(e: ChangeEvent<HTMLSelectElement>) {
    setEntityKey(e.target.value)
    resetImport()
  }

  function onPick(e: ChangeEvent<HTMLInputElement>) {
    setFile(e.target.files?.[0] ?? null)
    setPreview(null)
    setMapping({})
    setMatchKey('')
  }

  return (
    <SectionCard title="منصّة هجرة البيانات">
      <p className="mb-4 text-sm text-slate-500">
        مركز موحّد لهجرة بيانات المكتب: اختر نوع البيانات، ارفع ملف Excel، عايِن، طابِق الأعمدة، ثم أكّد.
        الاستيراد ذرّي — صفّ واحد غير صحيح يمنع الحفظ كاملاً. التحليل والتحقّق يتمّان في الخادم؛ قاعدة
        البيانات هي المرجع الرسمي.
      </p>

      {manifestQuery.isPending ? (
        <p className="text-sm text-slate-500">جارٍ تحميل الأنواع المتاحة…</p>
      ) : entities.length === 0 ? (
        <p className="text-sm text-slate-500">لا تملك صلاحية استيراد أيّ نوع من البيانات.</p>
      ) : (
        <>
      <div className="sm:max-w-xs">
        <SelectField label="نوع البيانات" value={entityKey} onChange={onEntity}>
          {entities.map((e) => (
            <option key={e.key} value={e.key}>{e.label}</option>
          ))}
        </SelectField>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-3">
        <input
          ref={fileRef}
          type="file"
          accept=".xlsx,.xls"
          onChange={onPick}
          aria-label="ملف الاستيراد"
          className="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-brand-700"
        />
        <Button disabled={!file || previewMut.isPending} onClick={() => previewMut.mutate()}>
          معاينة
        </Button>
        <Button
          variant="ghost"
          disabled={!file || !preview || preview.invalid > 0 || commitMut.isPending}
          onClick={() => commitMut.mutate()}
        >
          تأكيد الاستيراد
        </Button>
      </div>

      {preview && supportsMapping && preview.fields && (
        <div className="mt-5 rounded-xl border border-slate-200 p-4">
          <p className="mb-3 text-sm font-medium text-slate-700">مطابقة الأعمدة (حقل النظام ← عمود ملفك):</p>
          <div className="grid gap-3 sm:grid-cols-2">
            {preview.fields.map((f) => (
              <SelectField
                key={f.key}
                label={f.required ? `${f.key} *` : f.key}
                value={mapping[f.key] ?? ''}
                onChange={(e) => setMapping((m) => ({ ...m, [f.key]: e.target.value }))}
              >
                <option value="">— تجاهل —</option>
                {preview.detected_headers?.map((h) => (
                  <option key={h} value={h}>{h}</option>
                ))}
              </SelectField>
            ))}
          </div>

          {preview.match_keys && preview.match_keys.length > 0 && (
            <div className="mt-3 sm:max-w-xs">
              <SelectField
                label="مفتاح المطابقة (لتحديث السجلّات المطابقة)"
                value={matchKey}
                onChange={(e) => setMatchKey(e.target.value)}
              >
                {preview.match_keys.map((k) => (
                  <option key={k} value={k}>{k}</option>
                ))}
              </SelectField>
            </div>
          )}

          <div className="mt-3">
            <Button variant="ghost" disabled={previewMut.isPending} onClick={() => previewMut.mutate()}>
              إعادة المعاينة بعد المطابقة
            </Button>
          </div>
        </div>
      )}

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
              <p className="mb-1 font-medium text-red-700">صفوف تحتاج تصحيحاً قبل الاستيراد:</p>
              <ul className="max-h-48 space-y-1 overflow-y-auto">
                {preview.errors.map((err) => (
                  <li key={err.row} className="text-red-700">صف {err.row}: {err.message}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
        </>
      )}
    </SectionCard>
  )
}
