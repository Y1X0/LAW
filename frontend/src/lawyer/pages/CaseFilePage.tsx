import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Badge, Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { Tabs } from '@/core/ui/Tabs'
import { ErrorState, LoadingState } from '@/core/ui/states'
import { formatDate } from '@/core/lib/format'
import { caseStatusLabel } from '@/lawyer/api/cases'
import { fetchCaseDetail } from '@/lawyer/api/caseFile'
import { OverviewTab } from './caseFile/OverviewTab'
import { PartiesTab } from './caseFile/PartiesTab'
import { HearingsTab } from './caseFile/HearingsTab'
import { TimelineTab } from './caseFile/TimelineTab'
import { DocumentsTab } from './caseFile/DocumentsTab'
import { ArchiveTab } from './caseFile/ArchiveTab'
import { TasksTab } from './caseFile/TasksTab'

type TabKey = 'overview' | 'parties' | 'hearings' | 'timeline' | 'documents' | 'archive' | 'tasks'

const TABS: { key: TabKey; label: string }[] = [
  { key: 'overview', label: 'نظرة عامة' },
  { key: 'parties', label: 'الأطراف' },
  { key: 'hearings', label: 'الجلسات' },
  { key: 'timeline', label: 'السجل الزمني' },
  { key: 'documents', label: 'المستندات' },
  { key: 'archive', label: 'الأرشيف' },
  { key: 'tasks', label: 'المهام' },
]

function statusTone(status: string): 'green' | 'amber' | 'slate' | 'navy' {
  if (status === 'open') return 'green'
  if (status === 'pending') return 'amber'
  if (status === 'closed') return 'slate'
  return 'navy'
}

/**
 * ملف القضية (LP-4) — تبويبات قراءة فقط، كلٌّ يستهلك موردَه (يرث عزل القضية).
 * القضية غير المسندة → 403 على مستوى الشاشة.
 */
export function CaseFilePage() {
  const { id = '' } = useParams()
  const [tab, setTab] = useState<TabKey>('overview')

  const caseQuery = useQuery({ queryKey: ['case', id, 'detail'], queryFn: () => fetchCaseDetail(id) })

  const backLink = (
    <Link to="/cases" className="text-sm font-medium text-brand-700 hover:underline">
      ← كل القضايا
    </Link>
  )

  if (caseQuery.isPending) {
    return (
      <div className="space-y-5">
        <PageHeader title="ملف القضية" action={backLink} />
        <LoadingState />
      </div>
    )
  }

  if (caseQuery.isError) {
    return (
      <div className="space-y-5">
        <PageHeader title="ملف القضية" action={backLink} />
        <ErrorState error={caseQuery.error} />
      </div>
    )
  }

  const c = caseQuery.data

  return (
    <div className="space-y-5">
      {/* رأس القضية — دخول هادئ (lp-reveal عند التركيب، فوق الطيّة دائماً)، لمسة ذهبية */}
      <div className="lp-reveal">
        <Card className="relative overflow-hidden">
          <span className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-l from-transparent via-gold-400 to-transparent" />
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="space-y-1">
              <div className="flex flex-wrap items-center gap-2.5">
                <h1 className="text-[22px] font-extrabold tracking-tight text-brand-700">{c.title}</h1>
                <Badge tone={statusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>
              </div>
              <p className="text-sm text-slate-500">{`رقم الملف: ${c.internal_number}`}</p>
            </div>
            {backLink}
          </div>
          <div className="mt-4 flex flex-wrap gap-x-8 gap-y-2">
            <HeaderMeta label="العميل" value={c.client?.name ?? '—'} />
            <HeaderMeta
              label="تاريخ الفتح"
              value={<span className="tabular-nums">{formatDate(c.opened_date ?? null)}</span>}
            />
          </div>
        </Card>
      </div>

      {/* تنقّل التبويبات — مؤشّر ذهبي منزلق */}
      <Tabs tabs={TABS} active={tab} onChange={setTab} />

      {/* محتوى التبويب — انتقال تلاشٍ + انزلاق (يُعاد التركيب بمفتاح التبويب) */}
      <div key={tab} role="tabpanel" className="lp-tab-panel">
        {tab === 'overview' && <OverviewTab c={c} />}
        {tab === 'parties' && <PartiesTab caseId={id} />}
        {tab === 'hearings' && <HearingsTab caseId={id} />}
        {tab === 'timeline' && <TimelineTab caseId={id} />}
        {tab === 'documents' && <DocumentsTab caseId={id} />}
        {tab === 'archive' && <ArchiveTab caseId={id} />}
        {tab === 'tasks' && <TasksTab caseId={id} />}
      </div>
    </div>
  )
}

/** خانة بيانات صغيرة في رأس القضية. */
function HeaderMeta({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="text-sm">
      <span className="text-slate-500">{label}: </span>
      <span className="font-medium text-slate-800">{value}</span>
    </div>
  )
}
