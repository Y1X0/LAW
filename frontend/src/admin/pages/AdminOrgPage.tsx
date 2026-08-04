import { useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import {
  type Branch,
  type Department,
  createBranch,
  createDepartment,
  deleteBranch,
  deleteDepartment,
  fetchBranches,
  fetchDepartments,
  updateBranch,
  updateDepartment,
} from '@/admin/api/org'
import {
  type Position,
  createPosition,
  deletePosition,
  fetchPositions,
  updatePosition,
} from '@/admin/api/positions'

/**
 * إدارة الهيكل التنظيمي (M1) — الفروع والأقسام والمناصب من الواجهة، بديلاً عن البذرة/Excel.
 * تصميم بطاقات متجاوب (بلا جداول عريضة) ليعمل بلا سحب أفقي على الجوّال.
 */
export function AdminOrgPage() {
  const [branchFilter, setBranchFilter] = useState<number | ''>('')

  return (
    <div className="space-y-6">
      <PageHeader title="الهيكل التنظيمي" subtitle="الفروع والأقسام والمناصب" />
      <BranchesSection onPickBranch={setBranchFilter} />
      <DepartmentsSection branchFilter={branchFilter} setBranchFilter={setBranchFilter} />
      <PositionsSection />
    </div>
  )
}

/* ───────────────────────── الفروع ───────────────────────── */

function BranchesSection({ onPickBranch }: { onPickBranch: (id: number) => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState<Branch | null | 'new'>(null)

  const branches = useQuery({ queryKey: ['admin', 'branches'], queryFn: fetchBranches })

  const remove = useMutation({
    mutationFn: (id: number) => deleteBranch(id),
    onSuccess: () => {
      show('تم حذف الفرع')
      void qc.invalidateQueries({ queryKey: ['admin', 'branches'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حذف الفرع', 'error'),
  })

  return (
    <SectionCard
      title="الفروع"
      action={<Button onClick={() => setEditing('new')}>إضافة فرع</Button>}
    >
      {branches.isPending ? (
        <div className="space-y-2" data-testid="branches-skeleton">
          {Array.from({ length: 2 }).map((_, i) => <Skeleton key={i} className="h-16 w-full" />)}
        </div>
      ) : branches.isError ? (
        <ErrorState error={branches.error}>
          <div className="mt-3"><Button onClick={() => void branches.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : branches.data && branches.data.length === 0 ? (
        <EmptyState message="لا توجد فروع بعد. أضِف أول فرع للبدء." />
      ) : (
        <ul className="space-y-2.5">
          {branches.data?.map((b) => (
            <li key={b.id}>
              <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-slate-800">{b.name}</span>
                    <Badge tone="slate">{b.code}</Badge>
                    {!b.is_active && <Badge tone="amber">معطّل</Badge>}
                  </div>
                  <div className="mt-0.5 text-xs text-slate-400">
                    {b.city || '—'} · {b.departments_count} قسم
                  </div>
                </div>
                <div className="flex items-center gap-1.5">
                  <Button variant="ghost" onClick={() => onPickBranch(b.id)}>الأقسام</Button>
                  <Button variant="ghost" onClick={() => setEditing(b)}>تعديل</Button>
                  <Button
                    variant="ghost"
                    onClick={() => { if (confirm(`حذف الفرع «${b.name}»؟`)) remove.mutate(b.id) }}
                    disabled={remove.isPending}
                  >
                    حذف
                  </Button>
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {editing !== null && (
        <BranchModal branch={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
    </SectionCard>
  )
}

function BranchModal({ branch, onClose }: { branch: Branch | null; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [name, setName] = useState(branch?.name ?? '')
  const [code, setCode] = useState(branch?.code ?? '')
  const [city, setCity] = useState(branch?.city ?? '')
  const [phone, setPhone] = useState(branch?.phone ?? '')
  const [active, setActive] = useState(branch?.is_active ?? true)

  const save = useMutation({
    mutationFn: () => {
      const input = { name: name.trim(), code: code.trim(), city: city.trim() || undefined, phone: phone.trim() || undefined, is_active: active }
      return branch ? updateBranch(branch.id, input) : createBranch(input)
    },
    onSuccess: () => {
      show(branch ? 'تم تحديث الفرع' : 'تم إنشاء الفرع')
      void qc.invalidateQueries({ queryKey: ['admin', 'branches'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={branch ? `تعديل الفرع — ${branch.name}` : 'إضافة فرع'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="اسم الفرع" value={name} onChange={(e) => setName(e.target.value)} required />
        <Field label="الرمز (فريد)" value={code} onChange={(e) => setCode(e.target.value)} required />
        <Field label="المدينة (اختياري)" value={city} onChange={(e) => setCity(e.target.value)} />
        <Field label="الهاتف (اختياري)" value={phone} onChange={(e) => setPhone(e.target.value)} />
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
          فرع نشط
        </label>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}

/* ───────────────────────── الأقسام ───────────────────────── */

function DepartmentsSection({ branchFilter, setBranchFilter }: { branchFilter: number | ''; setBranchFilter: (v: number | '') => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState<Department | null | 'new'>(null)

  const branches = useQuery({ queryKey: ['admin', 'branches'], queryFn: fetchBranches })
  const departments = useQuery({
    queryKey: ['admin', 'departments', branchFilter || 'all'],
    queryFn: () => fetchDepartments(branchFilter || undefined),
  })

  const branchName = useMemo(
    () => (id: number) => branches.data?.find((b) => b.id === id)?.name ?? '',
    [branches.data],
  )

  const remove = useMutation({
    mutationFn: (id: number) => deleteDepartment(id),
    onSuccess: () => {
      show('تم حذف القسم')
      void qc.invalidateQueries({ queryKey: ['admin', 'departments'] })
      void qc.invalidateQueries({ queryKey: ['admin', 'branches'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حذف القسم', 'error'),
  })

  const canAdd = (branches.data?.length ?? 0) > 0

  return (
    <SectionCard
      title="الأقسام"
      action={<Button onClick={() => setEditing('new')} disabled={!canAdd}>إضافة قسم</Button>}
    >
      <div className="mb-3 max-w-xs">
        <SelectField
          label="تصفية بالفرع"
          value={branchFilter === '' ? '' : String(branchFilter)}
          onChange={(e) => setBranchFilter(e.target.value === '' ? '' : Number(e.target.value))}
        >
          <option value="">كل الفروع</option>
          {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
      </div>

      {!canAdd && !branches.isPending ? (
        <EmptyState message="أضِف فرعاً أولاً، ثم أضِف أقسامه." />
      ) : departments.isPending ? (
        <div className="space-y-2" data-testid="departments-skeleton">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
        </div>
      ) : departments.isError ? (
        <ErrorState error={departments.error} />
      ) : departments.data && departments.data.length === 0 ? (
        <EmptyState message="لا توجد أقسام بعد." />
      ) : (
        <ul className="space-y-2.5">
          {departments.data?.map((d) => (
            <li key={d.id}>
              <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-slate-800">{d.name}</span>
                    {!d.is_active && <Badge tone="amber">معطّل</Badge>}
                  </div>
                  <div className="mt-0.5 text-xs text-slate-400">{d.branch?.name ?? branchName(d.branch_id)}</div>
                </div>
                <div className="flex items-center gap-1.5">
                  <Button variant="ghost" onClick={() => setEditing(d)}>تعديل</Button>
                  <Button
                    variant="ghost"
                    onClick={() => { if (confirm(`حذف القسم «${d.name}»؟`)) remove.mutate(d.id) }}
                    disabled={remove.isPending}
                  >
                    حذف
                  </Button>
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {editing !== null && (
        <DepartmentModal
          department={editing === 'new' ? null : editing}
          branches={branches.data ?? []}
          defaultBranchId={branchFilter || branches.data?.[0]?.id || 0}
          onClose={() => setEditing(null)}
        />
      )}
    </SectionCard>
  )
}

function DepartmentModal({
  department, branches, defaultBranchId, onClose,
}: {
  department: Department | null
  branches: Branch[]
  defaultBranchId: number
  onClose: () => void
}) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [branchId, setBranchId] = useState<number>(department?.branch_id ?? defaultBranchId)
  const [name, setName] = useState(department?.name ?? '')
  const [active, setActive] = useState(department?.is_active ?? true)

  const save = useMutation({
    mutationFn: () => {
      const input = { branch_id: branchId, name: name.trim(), is_active: active }
      return department ? updateDepartment(department.id, input) : createDepartment(input)
    },
    onSuccess: () => {
      show(department ? 'تم تحديث القسم' : 'تم إنشاء القسم')
      void qc.invalidateQueries({ queryKey: ['admin', 'departments'] })
      void qc.invalidateQueries({ queryKey: ['admin', 'branches'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={department ? `تعديل القسم — ${department.name}` : 'إضافة قسم'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <SelectField label="الفرع" value={String(branchId)} onChange={(e) => setBranchId(Number(e.target.value))}>
          {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
        <Field label="اسم القسم" value={name} onChange={(e) => setName(e.target.value)} required />
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
          قسم نشط
        </label>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}

/* ───────────────────────── المناصب ───────────────────────── */

function PositionsSection() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [editing, setEditing] = useState<Position | null | 'new'>(null)
  const [branchFilter, setBranchFilter] = useState<number | ''>('')

  const branches = useQuery({ queryKey: ['admin', 'branches'], queryFn: fetchBranches })
  const positions = useQuery({
    queryKey: ['admin', 'positions', branchFilter || 'all'],
    queryFn: () => fetchPositions(branchFilter || undefined),
  })

  const branchName = useMemo(
    () => (id: number | null | undefined) => (id == null ? 'عام (بلا فرع)' : branches.data?.find((b) => b.id === id)?.name ?? '—'),
    [branches.data],
  )

  const remove = useMutation({
    mutationFn: (id: number) => deletePosition(id),
    onSuccess: () => {
      show('تم حذف المنصب')
      void qc.invalidateQueries({ queryKey: ['admin', 'positions'] })
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر حذف المنصب', 'error'),
  })

  return (
    <SectionCard
      title="المناصب"
      action={<Button onClick={() => setEditing('new')}>إضافة منصب</Button>}
    >
      <div className="mb-3 max-w-xs">
        <SelectField
          label="تصفية بالفرع"
          value={branchFilter === '' ? '' : String(branchFilter)}
          onChange={(e) => setBranchFilter(e.target.value === '' ? '' : Number(e.target.value))}
        >
          <option value="">كل الفروع</option>
          {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
      </div>

      {positions.isPending ? (
        <div className="space-y-2" data-testid="positions-skeleton">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
        </div>
      ) : positions.isError ? (
        <ErrorState error={positions.error}>
          <div className="mt-3"><Button onClick={() => void positions.refetch()}>إعادة المحاولة</Button></div>
        </ErrorState>
      ) : positions.data && positions.data.length === 0 ? (
        <EmptyState message="لا توجد مناصب بعد. أضِف أول منصب." />
      ) : (
        <ul className="space-y-2.5">
          {positions.data?.map((p) => (
            <li key={p.id}>
              <Card className="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-slate-800">{p.title}</span>
                    {!p.is_active && <Badge tone="amber">معطّل</Badge>}
                  </div>
                  <div className="mt-0.5 text-xs text-slate-400">
                    {branchName(p.branch_id)}{p.description ? ` · ${p.description}` : ''}
                  </div>
                </div>
                <div className="flex items-center gap-1.5">
                  <Button variant="ghost" onClick={() => setEditing(p)}>تعديل</Button>
                  <Button
                    variant="ghost"
                    onClick={() => { if (confirm(`حذف المنصب «${p.title}»؟`)) remove.mutate(p.id) }}
                    disabled={remove.isPending}
                  >
                    حذف
                  </Button>
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {editing !== null && (
        <PositionModal
          position={editing === 'new' ? null : editing}
          branches={branches.data ?? []}
          onClose={() => setEditing(null)}
        />
      )}
    </SectionCard>
  )
}

function PositionModal({
  position, branches, onClose,
}: {
  position: Position | null
  branches: Branch[]
  onClose: () => void
}) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [branchId, setBranchId] = useState<number | ''>(position?.branch_id ?? '')
  const [title, setTitle] = useState(position?.title ?? '')
  const [description, setDescription] = useState(position?.description ?? '')
  const [active, setActive] = useState(position?.is_active ?? true)

  const save = useMutation({
    mutationFn: () => {
      const base = { title: title.trim(), description: description.trim() || undefined, is_active: active }
      // الفرع يُضبط عند الإنشاء فقط (الخادم لا يغيّره لاحقاً).
      return position
        ? updatePosition(position.id, base)
        : createPosition({ ...base, branch_id: branchId === '' ? null : branchId })
    },
    onSuccess: () => {
      show(position ? 'تم تحديث المنصب' : 'تم إنشاء المنصب')
      void qc.invalidateQueries({ queryKey: ['admin', 'positions'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={position ? `تعديل المنصب — ${position.title}` : 'إضافة منصب'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="المسمّى الوظيفي" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <Field label="الوصف (اختياري)" value={description} onChange={(e) => setDescription(e.target.value)} />
        {position === null && (
          <SelectField label="الفرع (اختياري)" value={branchId === '' ? '' : String(branchId)} onChange={(e) => setBranchId(e.target.value === '' ? '' : Number(e.target.value))}>
            <option value="">عام (بلا فرع)</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
        )}
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
          منصب نشط
        </label>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}
