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

const previewSchema = z.object({
  total: z.number(),
  create: z.number(),
  update: z.number(),
  invalid: z.number(),
  errors: z.array(z.object({ row: z.number(), message: z.string() })),
})
export type ImportPreview = z.infer<typeof previewSchema>

const resultSchema = z.object({ created: z.number(), updated: z.number() })
export type ImportResult = z.infer<typeof resultSchema>

function fileForm(file: File): FormData {
  const form = new FormData()
  form.append('file', file)
  return form
}

/** معاينة استيراد الموظفين (تحقّق بلا حفظ). */
export async function previewEmployeesImport(file: File): Promise<ImportPreview> {
  return previewSchema.parse(await api.upload<unknown>('admin/data/import/employees/preview', fileForm(file)))
}

/** تنفيذ استيراد الموظفين (حفظ ذرّي). */
export async function commitEmployeesImport(file: File): Promise<ImportResult> {
  return resultSchema.parse(await api.upload<unknown>('admin/data/import/employees/commit', fileForm(file)))
}
