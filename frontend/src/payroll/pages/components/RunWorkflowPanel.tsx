import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card } from '@/core/ui/primitives'
import { SectionCard } from '@/core/ui/section'
import { ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  approveRun,
  calculateRun,
  fetchAttendanceCount,
  fetchItemsCount,
  fetchLeaveCount,
  fetchRun,
  isRunMutable,
  lockRun,
  runStatusLabel,
  runStatusTone,
  snapshotAttendance,
  snapshotLeave,
} from '@/payroll/api/payrollRuns'
import { ConfirmDialog } from './ConfirmDialog'

type ConfirmState = { title: string; message: React.ReactNode; label: string; run: () => Promise<unknown> } | null

/**
 * لوحة دورة المسير (Phase 2 / PR-4) — تشغيل الخطوات الموجودة خلفياً بالترتيب الصحيح.
 * حالة كل زر تعكس حرّاس الخادم الفعلية (الحالة القابلة للتعديل + وجود السجلّات
 * المحسوبة). الاحتساب لا يُحجب على غياب اللقطات (الخادم يسمح به) بل يُنبَّه المستخدم.
 */
export function RunWorkflowPanel({ runId, onBack }: { runId: number; onBack: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [confirm, setConfirm] = useState<ConfirmState>(null)

  const run = useQuery({ queryKey: ['payroll', 'run', runId], queryFn: () => fetchRun(runId) })
  const att = useQuery({ queryKey: ['payroll', 'run-att', runId], queryFn: () => fetchAttendanceCount(runId) })
  const leave = useQuery({ queryKey: ['payroll', 'run-leave', runId], queryFn: () => fetchLeaveCount(runId) })
  const items = useQuery({ queryKey: ['payroll', 'run-items', runId], queryFn: () => fetchItemsCount(runId) })

  const action = useMutation({
    mutationFn: (fn: () => Promise<unknown>) => fn(),
    onSuccess: () => {
      show('تمّت العملية بنجاح')
      void qc.invalidateQueries({ queryKey: ['payroll'] })
      setConfirm(null)
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  if (run.isPending || att.isPending || leave.isPending || items.isPending) {
    return <Card><Skeleton className="h-40 w-full" /></Card>
  }
  if (run.isError) {
    return <ErrorState error={run.error}><div className="mt-3"><Button onClick={() => void run.refetch()}>إعادة المحاولة</Button></div></ErrorState>
  }

  const status = run.data.status
  const mutable = isRunMutable(status)
  const attDone = (att.data ?? 0) > 0
  const leaveDone = (leave.data ?? 0) > 0
  const calculated = (items.data ?? 0) > 0
  const approved = status === 'approved' || status === 'locked'
  const locked = status === 'locked'
  const busy = action.isPending

  const ask = (title: string, message: React.ReactNode, label: string, run: () => Promise<unknown>) => () => setConfirm({ title, message, label, run })

  return (
    <div className="space-y-4">
      <Card className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="font-bold text-brand-700 tabular-nums">مسير #{run.data.id}</div>
          <div className="mt-0.5 text-xs text-slate-500">الحالة الحالية للمسير</div>
        </div>
        <div className="flex items-center gap-2">
          <Badge tone={runStatusTone(status)}>{runStatusLabel(status)}</Badge>
          <Button variant="ghost" onClick={onBack}>رجوع للقائمة</Button>
        </div>
      </Card>

      <SectionCard title="دورة تشغيل المسير">
        <p className="mb-3 text-xs text-slate-400">
          الترتيب الموصى به: لقطة الحضور والإجازات أولاً، ثم الاحتساب، ثم الاعتماد، ثم القفل. تُعطَّل كل خطوة إذا لم تسمح بها حالة المسير.
        </p>
        <ol className="space-y-2.5">
          <Step
            n={1} title="لقطة الحضور" done={attDone} doneLabel={`${att.data} سجل`}
            action={
              <Button variant="ghost" disabled={!mutable || busy} onClick={() => action.mutate(() => snapshotAttendance(runId))}>
                {attDone ? 'إعادة بناء اللقطة' : 'بناء لقطة الحضور'}
              </Button>
            }
          />
          <Step
            n={2} title="لقطة الإجازات" done={leaveDone} doneLabel={`${leave.data} سجل`}
            action={
              <Button variant="ghost" disabled={!mutable || busy} onClick={() => action.mutate(() => snapshotLeave(runId))}>
                {leaveDone ? 'إعادة بناء اللقطة' : 'بناء لقطة الإجازات'}
              </Button>
            }
          />
          <Step
            n={3} title="احتساب الرواتب" done={calculated} doneLabel={`${items.data} محسوب`}
            action={
              <Button
                variant="ghost"
                disabled={!mutable || busy}
                onClick={ask(
                  'تأكيد الاحتساب',
                  (!attDone || !leaveDone)
                    ? 'لم تُبْنَ لقطات الحضور/الإجازات بالكامل — سيُحتسب الراتب الأساسي فقط دون خصم غياب/تأخير/إجازة غير مدفوعة. هل تريد المتابعة؟'
                    : 'سيُحتسب المسير لكل الموظفين أصحاب ملف راتب نشط. يمكن إعادة الاحتساب طالما المسير غير معتمد.',
                  'احتساب',
                  () => calculateRun(runId),
                )}
              >
                {calculated ? 'إعادة الاحتساب' : 'احتساب الرواتب'}
              </Button>
            }
          />
          <Step
            n={4} title="اعتماد المسير" done={approved} doneLabel="معتمد"
            action={
              <Button
                variant="ghost"
                disabled={!mutable || !calculated || busy}
                onClick={ask('تأكيد الاعتماد', 'بعد الاعتماد لا يمكن إعادة احتساب المسير أو تعديل لقطاته. متابعة؟', 'اعتماد', () => approveRun(runId))}
              >
                اعتماد
              </Button>
            }
          />
          <Step
            n={5} title="قفل المسير" done={locked} doneLabel="مقفل"
            action={
              <Button
                variant="ghost"
                disabled={status !== 'approved' || busy}
                onClick={ask('تأكيد القفل', 'القفل نهائي — تُجمَّد أرقام المسير ولا يمكن التراجع. متابعة؟', 'قفل', () => lockRun(runId))}
              >
                قفل
              </Button>
            }
          />
        </ol>
      </SectionCard>

      {confirm && (
        <ConfirmDialog
          title={confirm.title}
          message={confirm.message}
          confirmLabel={confirm.label}
          busy={busy}
          onConfirm={() => action.mutate(confirm.run)}
          onClose={() => setConfirm(null)}
        />
      )}
    </div>
  )
}

function Step({ n, title, done, doneLabel, action }: { n: number; title: string; done: boolean; doneLabel: string; action: React.ReactNode }) {
  return (
    <li>
      <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold ${done ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}`}>
            {done ? '✓' : n}
          </span>
          <div>
            <div className="font-medium text-slate-800">{title}</div>
            <div className="text-xs text-slate-400">{done ? doneLabel : 'بانتظار'}</div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {done && <Badge tone="green">تمّت</Badge>}
          {action}
        </div>
      </Card>
    </li>
  )
}
