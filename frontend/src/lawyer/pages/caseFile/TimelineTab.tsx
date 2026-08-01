import { useQuery } from '@tanstack/react-query'
import { Card } from '@/core/ui/primitives'
import { formatDate } from '@/core/lib/format'
import { fetchCaseTimeline } from '@/lawyer/api/caseFile'
import { AsyncTab } from './AsyncTab'

/** التبويب 4 — السجل الزمني (append-only، عرض تسلسلي). */
export function TimelineTab({ caseId }: { caseId: string }) {
  const q = useQuery({ queryKey: ['case', caseId, 'timeline'], queryFn: () => fetchCaseTimeline(caseId) })

  return (
    <AsyncTab
      q={q}
      emptyMessage="لا توجد أحداث في السجل الزمني."
      render={(events) => (
        <Card>
          <ol className="relative space-y-5 border-r-2 border-brand-100 pr-6">
            {events.map((ev, i) => {
              const delay = `${i * 80}ms`
              return (
                <li
                  key={ev.id}
                  className="lp-reveal relative"
                  style={{ animationDelay: delay }}
                >
                  <span className="lp-timeline-dot" style={{ animationDelay: delay }} aria-hidden="true" />
                  <div className="flex items-center gap-2">
                    <span className="tabular-nums text-xs font-medium text-brand-700">{formatDate(ev.event_date)}</span>
                    {ev.event_type ? <span className="text-xs text-slate-400">· {ev.event_type}</span> : null}
                  </div>
                  <p className="mt-0.5 text-sm font-medium text-slate-800">{ev.title}</p>
                  {ev.description ? <p className="mt-0.5 text-sm text-slate-600">{ev.description}</p> : null}
                </li>
              )
            })}
          </ol>
        </Card>
      )}
    />
  )
}
