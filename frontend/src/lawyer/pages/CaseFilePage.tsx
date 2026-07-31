import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Badge } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { ErrorState, LoadingState } from '@/core/ui/states'
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
      <PageHeader
        title={c.title}
        subtitle={`رقم الملف: ${c.internal_number}`}
        action={
          <div className="flex items-center gap-3">
            <Badge tone={statusTone(c.status)}>{caseStatusLabel(c.status)}</Badge>
            {backLink}
          </div>
        }
      />

      {/* تنقّل التبويبات */}
      <div className="overflow-x-auto border-b border-slate-200">
        <div role="tablist" className="flex gap-1">
          {TABS.map((t) => (
            <button
              key={t.key}
              role="tab"
              aria-selected={tab === t.key}
              onClick={() => setTab(t.key)}
              className={`shrink-0 border-b-2 px-4 py-2 text-sm transition ${
                tab === t.key
                  ? 'border-brand-600 font-semibold text-brand-700'
                  : 'border-transparent text-slate-500 hover:text-brand-700'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>
      </div>

      <div>
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
