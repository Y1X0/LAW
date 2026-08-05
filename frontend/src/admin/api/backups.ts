import { z } from 'zod'
import { api } from '@/core/api/client'

/** نسخة احتياطية كما يعرضها الخادم (Phase 13). القراءة/الإنشاء/التنزيل تحت backup.manage. */
const backupSchema = z.object({
  id: z.number(),
  filename: z.string().nullable(),
  kind: z.string(),
  status: z.string(),
  trigger: z.string(),
  size_bytes: z.number().nullable(),
  created_by: z.string().nullable().optional(),
  created_at: z.string().nullable().optional(),
})
export type Backup = z.infer<typeof backupSchema>

const KIND_LABEL: Record<string, string> = { manual: 'يدوية', daily: 'يومية', weekly: 'أسبوعية', monthly: 'شهرية' }
export const backupKindLabel = (k: string): string => KIND_LABEL[k] ?? k

const STATUS_LABEL: Record<string, string> = { completed: 'ناجحة', failed: 'فاشلة', pending: 'قيد التنفيذ' }
export const backupStatusLabel = (s: string): string => STATUS_LABEL[s] ?? s

/** تنسيق الحجم بايت → وحدة مقروءة. */
export function formatBytes(bytes: number | null | undefined): string {
  if (bytes == null) return '—'
  if (bytes < 1024) return `${bytes} B`
  const units = ['KB', 'MB', 'GB', 'TB']
  let v = bytes / 1024
  let i = 0
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return `${v.toFixed(1)} ${units[i]}`
}

/** قائمة النسخ (الأحدث أولاً) — `GET /admin/backups`. */
export async function fetchBackups(): Promise<Backup[]> {
  return z.array(backupSchema).parse(await api.get<unknown>('admin/backups'))
}

/** إنشاء نسخة الآن — `POST /admin/backups` (تفريغ فعلي في الخادم). */
export async function createBackup(): Promise<Backup> {
  return backupSchema.parse(await api.post<unknown>('admin/backups'))
}

/** تنزيل ملف نسخة بمصادقة (مثل تنزيل Excel) — `GET /admin/backups/{id}/download`. */
export async function downloadBackup(backup: Backup): Promise<void> {
  const blob = await api.blob(`admin/backups/${backup.id}/download`)
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = backup.filename ?? `backup-${backup.id}.dump`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
