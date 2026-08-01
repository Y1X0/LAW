import { useState, type FormEvent } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Badge, Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'
import { EmptyState, ErrorState, Skeleton } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import {
  USER_STATUSES,
  disableUser,
  enableUser,
  fetchUsers,
  userStatusLabel,
  userStatusTone,
  type AdminUser,
} from '@/admin/api/users'
import { ApiError } from '@/core/api/types'
import { CreateUserModal } from './components/CreateUserModal'
import { ResetPasswordModal } from './components/ResetPasswordModal'
import { LinkEmployeeModal } from './components/LinkEmployeeModal'
import { ManageUserRolesModal } from './components/ManageUserRolesModal'

const PER_PAGE = 15

/**
 * إدارة المستخدمين (ADMIN-2) — قائمة `GET /users` مع بحث وفلترة حالة وترقيم خادمي،
 * وإنشاء/تعديل الحالة (تعطيل/تفعيل) وإعادة كلمة المرور والربط بموظف.
 * كل النقاط محميّة بصلاحية users.manage. إضافة فقط، دون مساس بأي شيء قائم.
 */
export function AdminUsersPage() {
  const qc = useQueryClient()
  const { show } = useToast()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [resetting, setResetting] = useState<AdminUser | null>(null)
  const [linking, setLinking] = useState<AdminUser | null>(null)
  const [managingRoles, setManagingRoles] = useState<AdminUser | null>(null)

  const { data, isPending, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['admin', 'users', { search, status, page }],
    queryFn: () => fetchUsers({ search, status, page, perPage: PER_PAGE }),
    placeholderData: keepPreviousData,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ['admin', 'users'] })

  const toggle = useMutation({
    mutationFn: (user: AdminUser) =>
      user.status === 'active' ? disableUser(user.id) : enableUser(user.id),
    onSuccess: (updated) => {
      show(updated.status === 'active' ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم')
      void invalidate()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSearch(e: FormEvent) {
    e.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  function onStatusChange(value: string) {
    setPage(1)
    setStatus(value)
  }

  const hasFilters = search !== '' || status !== ''

  return (
    <div className="space-y-5">
      <PageHeader
        title="المستخدمون"
        subtitle="إدارة حسابات المنصّة"
        action={<Button onClick={() => setCreating(true)}>إنشاء مستخدم</Button>}
      />

      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-end">
          <form onSubmit={onSearch} className="flex flex-1 items-end gap-2">
            <div className="flex-1">
              <Field
                label="بحث (الاسم أو البريد أو اسم المستخدم)"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="اسم أو بريد المستخدم…"
              />
            </div>
            <Button type="submit">بحث</Button>
          </form>
          <div className="md:w-52">
            <SelectField label="الحالة" value={status} onChange={(e) => onStatusChange(e.target.value)}>
              <option value="">كل الحالات</option>
              {USER_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {userStatusLabel(s)}
                </option>
              ))}
            </SelectField>
          </div>
        </div>
      </Card>

      {isPending ? (
        <UsersSkeleton />
      ) : isError ? (
        <ErrorState error={error}>
          <div className="mt-3">
            <Button onClick={() => void refetch()}>إعادة المحاولة</Button>
          </div>
        </ErrorState>
      ) : data.items.length === 0 ? (
        <Card>
          <EmptyState message={hasFilters ? 'لا توجد نتائج مطابقة.' : 'لا يوجد مستخدمون بعد.'} />
        </Card>
      ) : (
        <>
          <Card className="lp-table-wrap p-0">
            <table
              className={`lp-table min-w-[820px] text-right text-sm transition-opacity ${
                isFetching ? 'opacity-60' : ''
              }`}
              aria-busy={isFetching}
            >
              <thead>
                <tr className="text-xs">
                  <th className="px-4 py-3 text-right">المستخدم</th>
                  <th className="px-4 py-3 text-right">الأدوار</th>
                  <th className="px-4 py-3 text-right">الموظف المرتبط</th>
                  <th className="px-4 py-3 text-right">الحالة</th>
                  <th className="px-4 py-3 text-right">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((user) => (
                  <UserRow
                    key={user.id}
                    user={user}
                    toggling={toggle.isPending && toggle.variables?.id === user.id}
                    onToggle={() => toggle.mutate(user)}
                    onReset={() => setResetting(user)}
                    onLink={() => setLinking(user)}
                    onRoles={() => setManagingRoles(user)}
                  />
                ))}
              </tbody>
            </table>
          </Card>

          <div className="flex items-center justify-between gap-3 text-sm text-slate-600">
            <span className="tabular-nums">
              صفحة {data.meta.page} من {data.meta.total_pages} · {data.meta.total} مستخدم
            </span>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1 || isFetching}>
                السابق
              </Button>
              <Button
                variant="ghost"
                onClick={() => setPage((p) => Math.min(data.meta.total_pages, p + 1))}
                disabled={page >= data.meta.total_pages || isFetching}
              >
                التالي
              </Button>
            </div>
          </div>
        </>
      )}

      {creating && <CreateUserModal onClose={() => setCreating(false)} />}
      {resetting && <ResetPasswordModal user={resetting} onClose={() => setResetting(null)} />}
      {linking && <LinkEmployeeModal user={linking} onClose={() => setLinking(null)} />}
      {managingRoles && <ManageUserRolesModal user={managingRoles} onClose={() => setManagingRoles(null)} />}
    </div>
  )
}

function UserRow({
  user,
  toggling,
  onToggle,
  onReset,
  onLink,
  onRoles,
}: {
  user: AdminUser
  toggling: boolean
  onToggle: () => void
  onReset: () => void
  onLink: () => void
  onRoles: () => void
}) {
  const isActive = user.status === 'active'
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <td className="px-4 py-3">
        <div className="font-medium text-slate-800">{user.name}</div>
        <div className="text-xs text-slate-400">{user.email}</div>
      </td>
      <td className="px-4 py-3 text-slate-700">
        {user.roles.length > 0 ? user.roles.map((r) => r.display_name || r.name).join('، ') : '—'}
      </td>
      <td className="px-4 py-3 text-slate-600">
        {user.employee ? user.employee.full_name_ar : '—'}
      </td>
      <td className="px-4 py-3">
        <Badge tone={userStatusTone(user.status)}>{userStatusLabel(user.status)}</Badge>
      </td>
      <td className="px-4 py-3">
        <div className="flex items-center gap-1.5">
          <Button variant="ghost" onClick={onToggle} disabled={toggling}>
            {toggling ? 'جارٍ…' : isActive ? 'تعطيل' : 'تفعيل'}
          </Button>
          <Button variant="ghost" onClick={onRoles}>
            الأدوار
          </Button>
          <Button variant="ghost" onClick={onReset}>
            كلمة المرور
          </Button>
          <Button variant="ghost" onClick={onLink}>
            {user.employee ? 'الموظف' : 'ربط موظف'}
          </Button>
        </div>
      </td>
    </tr>
  )
}

function UsersSkeleton() {
  return (
    <Card>
      <div data-testid="users-skeleton" className="space-y-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </Card>
  )
}
