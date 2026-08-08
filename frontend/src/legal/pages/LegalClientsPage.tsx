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
        <Card className="p-0" data-testid="clients-skeleton">
          <div className="space-y-px p-3">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="flex items-center gap-4 px-1 py-2.5">
                <Skeleton className="h-4 flex-1" /><Skeleton className="h-4 w-24" /><Skeleton className="h-4 w-16" />
              </div>
            ))}
          </div>
        </Card>
      ) : query.isError ? (
        <ErrorState error={query.error}><div className="mt-3"><Button onClick={() => void query.refetch()}>إعادة المحاولة</Button></div></ErrorState>
      ) : query.data.items.length === 0 ? (
        <EmptyState message="لا يوجد عملاء مطابقون." />
      ) : (
        <>
          {/* جدول مؤسسي كثيف — يتحوّل إلى بطاقات مكدّسة على الجوّال عبر .lp-table. */}
          <Card className="overflow-hidden p-0">
            <div className="lp-table-wrap">
              <table className={`lp-table text-right text-sm sm:min-w-[720px] ${query.isFetching ? 'opacity-60 transition-opacity' : ''}`} aria-busy={query.isFetching}>
                <thead>
                  <tr>
                    <th className="px-4 py-2.5 text-right">الموكّل</th>
                    <th className="px-4 py-2.5 text-right">الصفة</th>
                    <th className="px-4 py-2.5 text-right">الهاتف</th>
                    <th className="px-4 py-2.5 text-right">البريد</th>
                    <th className="px-4 py-2.5 text-right">الحالة</th>
                    <th className="px-4 py-2.5 text-right"><span className="sr-only">إجراءات</span></th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.items.map((c) => (
                    <tr key={c.id} className="lp-row">
                      <td data-label="الموكّل" className="px-4 py-2.5">
                        <Link to={`/legal/clients/${c.id}`} className="font-semibold text-brand-700 hover:underline">{c.name}</Link>
                      </td>
                      <td data-label="الصفة" className="px-4 py-2.5 text-slate-600">{clientTypeLabel(c.type)}</td>
                      <td data-label="الهاتف" className="whitespace-nowrap px-4 py-2.5 tabular-nums text-slate-600">{c.phone || '—'}</td>
                      <td data-label="البريد" className="px-4 py-2.5 text-slate-600">{c.email || '—'}</td>
                      <td data-label="الحالة" className="px-4 py-2.5"><Badge tone={clientStatusTone(c.status)}>{clientStatusLabel(c.status)}</Badge></td>
                      <td data-label="" className="px-4 py-2.5 text-left">
                        <Link to={`/legal/clients/${c.id}`} className="text-xs font-semibold text-brand-600 hover:underline">فتح ←</Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <Pagination page={query.data.meta.page} totalPages={query.data.meta.total_pages} onChange={setPage} />
        </>
      )}

      {creating && <ClientFormModal onClose={() => setCreating(false)} />}
    </div>
  )
}
