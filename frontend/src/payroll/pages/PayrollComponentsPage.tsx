import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import {
  COMPONENT_TYPES,
  VALUE_TYPES,
  type SalaryComponent,
  componentTypeLabel,
  componentTypeTone,
  fetchSalaryComponents,
  valueTypeLabel,
} from '@/payroll/api/salaryComponents'
import { SalaryComponentModal } from './components/SalaryComponentModal'

/**
 * كتالوج مكوّنات الراتب (Phase 2 / PR-3) — بدلات/استقطاعات المكتب.
 * قراءة/إنشاء/تعديل من نقاط النهاية الموجودة فقط. لا حذف نهائي (لا endpoint):
 * التعطيل عبر «مفعّل» في التعديل — موثّق في docs/BACKLOG.md.
 */
export function PayrollComponentsPage() {
  const [type, setType] = useState('')
  const [valueType, setValueType] = useState('')
  const [editing, setEditing] = useState<SalaryComponent | null>(null)
  const [creating, setCreating] = useState(false)

  const query = useQuery({
    queryKey: ['payroll', 'components', { type, valueType }],
    queryFn: () => fetchSalaryComponents({ type: type || undefined, value_type: valueType || undefined }),
  })

  return (
    <div className="space-y-5">
      <PageHeader
        title="مكوّنات الراتب"
        subtitle="كتالوج البدلات والاستقطاعات"
        action={
          <div className="flex items-center gap-2">
            <Link to="/payroll" className="lp-press rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">اللوحة</Link>
            <Button onClick={() => setCreating(true)}>إنشاء مكوّن</Button>
          </div>
        }
      />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <SelectField label="النوع" value={type} onChange={(e) => setType(e.target.value)}>
          <option value="">كل الأنواع</option>
          {COMPONENT_TYPES.map((t) => <option key={t} value={t}>{componentTypeLabel(t)}</option>)}
        </SelectField>
        <SelectField label="نوع القيمة" value={valueType} onChange={(e) => setValueType(e.target.value)}>
          <option value="">كل القيم</option>
          {VALUE_TYPES.map((v) => <option key={v} value={v}>{valueTypeLabel(v)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="components-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-40" /><Skeleton className="mt-2 h-4 w-24" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}>
          <div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : query.data.length === 0 ? (
        <EmptyState message="لا توجد مكوّنات مطابقة. أنشئ مكوّناً جديداً للبدء." />
      ) : (
        <ul className="space-y-2.5" aria-busy={query.isFetching}>
          {query.data.map((c) => (
            <li key={c.id}>
              <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                  <div className="font-bold text-slate-800">{c.name}</div>
                  <div className="mt-0.5 text-xs tabular-nums text-slate-400">{c.code} · {valueTypeLabel(c.value_type)}</div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={componentTypeTone(c.type)}>{componentTypeLabel(c.type)}</Badge>
                  {!c.is_active && <Badge tone="slate">معطّل</Badge>}
                  <Button variant="ghost" onClick={() => setEditing(c)}>تعديل</Button>
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {creating && <SalaryComponentModal onClose={() => setCreating(false)} />}
      {editing && <SalaryComponentModal component={editing} onClose={() => setEditing(null)} />}
    </div>
  )
}
