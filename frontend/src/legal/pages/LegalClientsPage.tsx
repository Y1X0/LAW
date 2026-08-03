import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { Pagination } from './components/Pagination'
import {
  CLIENT_STATUSES,
  CLIENT_TYPES,
  clientStatusLabel,
  clientStatusTone,
  clientTypeLabel,
  fetchClientsPage,
} from '@/legal/api/clients'
import { ClientFormModal } from './components/ClientFormModal'

/**
 * قائمة إدارة العملاء (Phase 4 / PR-1) — `GET /clients` الموجود، مع بحث وفلاتر
 * (نوع/حالة) وإظهار المعطّلين وترقيم، وإنشاء عميل بنموذج كامل. التفاصيل/التعديل
 * في PR-2. صفر تغيير خلفي.
 */
export function LegalClientsPage() {
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const query = useQuery({
    queryKey: ['legal', 'clients', { search, type, status, page }],
    queryFn: () => fetchClientsPage({
      search: search.trim() || undefined,
      type: type || undefined,
      status: status || undefined,
      includeInactive: true, // الفلتر الصريح يحكم؛ الافتراض هنا إظهار الكل ليتحكّم المستخدم بالحالة.
      page,
    }),
  })

  const reset = (fn: () => void) => { fn(); setPage(1) }

  return (
    <div className="space-y-5">
      <PageHeader title="العملاء" subtitle="إدارة عملاء المكتب" action={<Button onClick={() => setCreating(true)}>إنشاء عميل</Button>} />

      <Card className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Field label="بحث" value={search} onChange={(e) => reset(() => setSearch(e.target.value))} placeholder="اسم · هاتف · بريد · هوية" />
        <SelectField label="النوع" value={type} onChange={(e) => reset(() => setType(e.target.value))}>
          <option value="">كل الأنواع</option>
          {CLIENT_TYPES.map((t) => <option key={t} value={t}>{clientTypeLabel(t)}</option>)}
        </SelectField>
        <SelectField label="الحالة" value={status} onChange={(e) => reset(() => setStatus(e.target.value))}>
          <option value="">الكل (نشط ومعطّل)</option>
          {CLIENT_STATUSES.map((s) => <option key={s} value={s}>{clientStatusLabel(s)}</option>)}
        </SelectField>
      </Card>

      {query.isPending ? (
        <div className="space-y-2.5" data-testid="clients-skeleton">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><Skeleton className="h-5 w-48" /><Skeleton className="mt-2 h-4 w-32" /></Card>
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا يوجد عملاء مطابقون." />
      ) : (
        <>
          <ul className="space-y-2.5" aria-busy={query.isFetching}>
            {query.data.items.map((c) => (
              <li key={c.id}>
                <Card className="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <Link to={`/legal/clients/${c.id}`} className="font-bold text-brand-700 hover:underline">{c.name}</Link>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                      <span>{clientTypeLabel(c.type)}</span>
                      {c.phone && <span className="tabular-nums text-slate-400">{c.phone}</span>}
                      {c.email && <span className="text-slate-400">{c.email}</span>}
                    </div>
                  </div>
                  <Badge tone={clientStatusTone(c.status)}>{clientStatusLabel(c.status)}</Badge>
                </Card>
              </li>
            ))}
          </ul>

          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <ClientFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
