import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { fetchAllWorklog } from '@/legal/api/worklog'
import { EmployeePicker } from './components/EmployeePicker'

/**
 * إشراف الإنجاز اليومي (Phase 3 / PR-6) — قراءة فقط من `GET /worklog`
 * (worklog.view_all). المدير يطّلع على سجلات الجميع؛ التسجيل ذاتي في بوابة المحامي.
 * فلتر موظف اختياري + ترقيم. صفر تغيير خلفي.
 */
export function LegalWorklogPage() {
  const [employee, setEmployee] = useState<{ id: number; name: string } | null>(null)
  const [page, setPage] = useState(1)

  const query = useQuery({
    queryKey: ['legal', 'worklog', { employeeId: employee?.id ?? null, page }],
    queryFn: () => fetchAllWorklog({ employeeId: employee?.id, page }),
  })

  return (
    <div className="space-y-5">
      <PageHeader title="الإنجاز اليومي" subtitle="اطّلاع على سجلات إنجاز الفريق" />

      <Card>
        <EmployeePicker
          label="تصفية بموظف"
          selected={employee}
          onSelect={(e) => { setEmployee(e); setPage(1) }}
          onClear={() => { setEmployee(null); setPage(1) }}
        />
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="worklog-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-40" /><Skeleton className="mt-2 h-4 w-full" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا توجد سجلّات إنجاز." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((w) => (
              <li key={w.id}>
                <Card className="p-3.5">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-medium text-slate-800">{w.employee?.full_name_ar ?? '—'}</span>
                    <span className="tabular-nums text-xs text-slate-400">{w.work_date}</span>
                  </div>
                  <div className="mt-2 space-y-1.5 text-sm">
                    <p className="text-slate-700"><span className="text-xs font-medium text-slate-400">أُنجز اليوم: </span>{w.done_today || '—'}</p>
                    {w.plan_tomorrow && <p className="text-slate-600"><span className="text-xs font-medium text-slate-400">خطة الغد: </span>{w.plan_tomorrow}</p>}
                  </div>
                </Card>
              </li>
            ))}
          </ul>

          {query.data.meta.total_pages > 1 && (
            <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={query.data.meta.page <= 1}>السابق</Button>
              <span className="tabular-nums">صفحة {query.data.meta.page} من {query.data.meta.total_pages}</span>
              <Button variant="ghost" onClick={() => setPage((p) => p + 1)} disabled={query.data.meta.page >= query.data.meta.total_pages}>التالي</Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
