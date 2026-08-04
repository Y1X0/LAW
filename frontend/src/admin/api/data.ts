import { z } from 'zod'
import { api } from '@/core/api/client'

/** كيانات التصدير المتاحة (تطابق مسارات /admin/data/export/*). */
export const EXPORT_ENTITIES = [
  { key: 'employees', label: 'الموظفون' },
  { key: 'attendance', label: 'الحضور' },
  { key: 'leave-requests', label: 'الإجازات' },
  { key: 'payroll-items', label: 'بنود الرواتب' },
  { key: 'clients', label: 'العملاء' },
  { key: 'cases', label: 'القضايا' },
] as const

export type ExportEntity = (typeof EXPORT_ENTITIES)[number]['key']

/** ينزّل ملف Excel للكيان المطلوب (بمصادقة) ويحفظه في المتصفّح. */
export async function downloadExport(entity: ExportEntity): Promise<void> {
  const blob = await api.blob(`admin/data/export/${entity}`)
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${entity}-${new Date().toISOString().slice(0, 10)}.xlsx`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/** كيانات مركز الاستيراد. mapping=true ⇒ يدعم مطابقة الأعمدة (يعيد الخادم fields/detected). */
export const IMPORT_ENTITIES = [
  { key: 'clients', label: 'العملاء', mapping: true },
  { key: 'employees', label: 'الموظفون', mapping: false },
] as const
export type ImportEntityKey = (typeof IMPORT_ENTITIES)[number]['key']

const fieldSchema = z.object({ key: z.string(), required: z.boolean() })
export type ImportField = z.infer<typeof fieldSchema>

/** ملخّص المعاينة + بيانات المطابقة الوصفية (اختيارية — للكيانات الداعمة). */
const importPreviewSchema = z.object({
  total: z.number(),
  create: z.number(),
  update: z.number(),
  invalid: z.number(),
  errors: z.array(z.object({ row: z.number(), message: z.string() })),
  fields: z.array(fieldSchema).optional(),
  match_keys: z.array(z.string()).optional(),
  detected_headers: z.array(z.string()).optional(),
})
export type ImportPreview = z.infer<typeof importPreviewSchema>

const resultSchema = z.object({ created: z.number(), updated: z.number() })
export type ImportResult = z.infer<typeof resultSchema>

export interface ImportOptions {
  /** field => sheetHeader (يُطبَّق في الخادم قبل التحقّق). */
  mapping?: Record<string, string>
  matchKey?: string
}

function importForm(file: File, opts?: ImportOptions): FormData {
  const form = new FormData()
  form.append('file', file)
  if (opts?.mapping) {
    for (const [field, header] of Object.entries(opts.mapping)) {
      if (header) form.append(`mapping[${field}]`, header)
    }
  }
  if (opts?.matchKey) form.append('match_key', opts.matchKey)
  return form
}

/** معاينة استيراد كيان (تحقّق بلا حفظ). الواجهة لا تحلّل الملف — الخادم يقرأ ويقرّر. */
export async function previewImport(entity: string, file: File, opts?: ImportOptions): Promise<ImportPreview> {
  return importPreviewSchema.parse(await api.upload<unknown>(`admin/data/import/${entity}/preview`, importForm(file, opts)))
}

/** تنفيذ استيراد كيان (حفظ ذرّي عبر خدمة النطاق في الخادم). */
export async function commitImport(entity: string, file: File, opts?: ImportOptions): Promise<ImportResult> {
  return resultSchema.parse(await api.upload<unknown>(`admin/data/import/${entity}/commit`, importForm(file, opts)))
}
