import { Badge } from '@/core/ui/primitives'
import { InfoRow, SectionCard } from '@/core/ui/section'
import { formatDate } from '@/core/lib/format'
import { caseStatusLabel } from '@/lawyer/api/cases'
import type { CaseDetail } from '@/lawyer/api/caseFile'

function statusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'open') return 'green'
  if (status === 'pending') return 'amber'
  if (status === 'closed') return 'slate'
  return 'navy'
}

/** التبويب 1 — بيانات القضية الأساسية (من حمولة GET /api/cases/{id} المحمّلة مسبقاً). */
export function OverviewTab({ c }: { c: CaseDetail }) {
  const assignees = (c.assignments ?? [])
    .map((a) => a.employee?.full_name_ar)
    .filter((n): n is string => Boolean(n))

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <SectionCard title="بيانات القضية">
        <InfoRow label="رقم الملف الداخلي" value={<span className="tabular-nums">{c.internal_number}</span>} />
        <InfoRow label="رقم المحكمة" value={c.court_case_number ?? '—'} />
        <InfoRow label="العنوان" value={c.title} />
        <InfoRow label="النوع" value={c.case_type ?? '—'} />
        <InfoRow label="المحكمة" value={c.court_name ?? '—'} />
        <InfoRow label="الحالة" value={<Badge tone={statusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>} />
        <InfoRow
          label="التقدّم"
          value={
            <span className="flex items-center gap-2">
              <span className="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200">
                <span className="block h-full rounded-full bg-brand-500" style={{ width: `${c.progress}%` }} />
              </span>
              <span className="tabular-nums text-xs text-slate-500">{c.progress}%</span>
            </span>
          }
        />
        <InfoRow label="تاريخ الفتح" value={<span className="tabular-nums">{formatDate(c.opened_date ?? null)}</span>} />
        <InfoRow label="القيمة" value={c.value != null ? String(c.value) : '—'} />
      </SectionCard>

      <div className="space-y-4">
        <SectionCard title="العميل">
          <InfoRow label="الاسم" value={c.client?.name ?? '—'} />
          <InfoRow label="الهاتف" value={c.client?.phone ?? '—'} />
          <InfoRow label="البريد" value={c.client?.email ?? '—'} />
        </SectionCard>

        <SectionCard title="المحامون">
          <InfoRow label="المسؤول" value={c.responsibleLawyer?.full_name_ar ?? '—'} />
          <InfoRow label="المسندون" value={assignees.length > 0 ? assignees.join('، ') : '—'} />
        </SectionCard>

        {c.description ? (
          <SectionCard title="الوصف">
            <p className="whitespace-pre-line text-sm text-slate-700">{c.description}</p>
          </SectionCard>
        ) : null}
      </div>
    </div>
  )
}
