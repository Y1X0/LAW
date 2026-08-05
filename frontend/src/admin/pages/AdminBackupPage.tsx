import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  type Backup,
  backupKindLabel,
  backupStatusLabel,
  createBackup,
  downloadBackup,
  fetchBackups,
  formatBytes,
} from '@/admin/api/backups'

/**
 * النسخ الاحتياطي — واجهة Owner (Phase 13 · PR-3). آخر نسخة ناجحة + «إنشاء نسخة الآن» + جدول
 * النسخ مع تنزيل. لا استعادة (CLI + runbook فقط) ولا حذف يدوي ولا إعدادات — بساطة مقصودة.
 */
export function AdminBackupPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const backups = useQuery({ queryKey: ['admin', 'backups'], queryFn: fetchBackups })

  const lastSuccess = backups.data?.find((b) => b.status === 'completed')

  const create = useMutation({
    mutationFn: createBackup,
    onSuccess: (b) => {
      show(b.status === 'completed' ? 'تمّ إنشاء نسخة احتياطية' : 'بدأ إنشاء النسخة')
      void qc.invalidateQueries({ queryKey: ['admin', 'backups'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إنشاء النسخة', 'error'),
  })

  const download = useMutation({
    mutationFn: (b: Backup) => downloadBackup(b),
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر التنزيل', 'error'),
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="النسخ الاحتياطي"
        subtitle="نسخ قاعدة البيانات إلى تخزين خارجي آمن. الاستعادة تتم عبر إجراء مُوثَّق (CLI) لا من الواجهة."
      />

      <SectionCard
        title="الحالة"
        action={
          <Button disabled={create.isPending} onClick={() => create.mutate()}>
            {create.isPending ? 'جارٍ الإنشاء…' : 'إنشاء نسخة الآن'}
          </Button>
        }
      >
        {backups.isPending ? (
          <Skeleton className="h-8 w-64" />
        ) : lastSuccess ? (
          <p className="text-sm text-slate-600">
            آخر نسخة ناجحة: <b className="text-emerald-700">{lastSuccess.created_at?.slice(0, 16).replace('T', ' ')}</b>
            {' · '}الحجم: <b>{formatBytes(lastSuccess.size_bytes)}</b>
            {' · '}النوع: {backupKindLabel(lastSuccess.kind)}
          </p>
        ) : (
          <p className="text-sm text-slate-500">لا توجد نسخة ناجحة بعد. أنشئ أوّل نسخة الآن.</p>
        )}
      </SectionCard>

      <SectionCard title="النسخ">
        {backups.isPending ? (
          <div className="space-y-2">
            {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
          </div>
        ) : backups.isError ? (
          <ErrorState error={backups.error}>
            <div className="mt-3"><Button onClick={() => void backups.refetch()}>إعادة المحاولة</Button></div>
          </ErrorState>
        ) : backups.data && backups.data.length === 0 ? (
          <EmptyState message="لا توجد نسخ احتياطية بعد." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-slate-500">
                  <th className="p-2 text-right font-medium">التاريخ</th>
                  <th className="p-2 text-right font-medium">النوع</th>
                  <th className="p-2 text-right font-medium">الحجم</th>
                  <th className="p-2 text-right font-medium">الحالة</th>
                  <th className="p-2 text-right font-medium">إجراء</th>
                </tr>
              </thead>
              <tbody>
                {backups.data?.map((b) => (
                  <tr key={b.id} className="border-t border-slate-100">
                    <td className="p-2 text-slate-700">{b.created_at?.slice(0, 16).replace('T', ' ') ?? '—'}</td>
                    <td className="p-2 text-slate-600">{backupKindLabel(b.kind)}</td>
                    <td className="p-2 text-slate-600">{formatBytes(b.size_bytes)}</td>
                    <td className="p-2">
                      <Badge tone={b.status === 'completed' ? 'green' : b.status === 'failed' ? 'amber' : 'slate'}>
                        {backupStatusLabel(b.status)}
                      </Badge>
                    </td>
                    <td className="p-2">
                      {b.status === 'completed' && (
                        <Button
                          variant="ghost"
                          disabled={download.isPending}
                          onClick={() => download.mutate(b)}
                        >
                          تنزيل
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </SectionCard>

      <Card className="p-4 text-xs text-slate-500">
        استراتيجية النسخ تشمل <b>قاعدة البيانات</b> (قضايا/عملاء/مالية/رواتب/تدقيق…). ملفات الوثائق محميّة
        عبر التخزين الكائني (Object Storage Versioning). الاحتفاظ متدرّج: يومي/أسبوعي/شهري.
      </Card>
    </div>
  )
}
