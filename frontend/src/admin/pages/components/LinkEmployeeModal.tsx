import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Field } from '@/core/ui/primitives'
import { EmptyState, ErrorState } from '@/core/ui/states'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { linkEmployee, unlinkEmployee, type AdminUser } from '@/admin/api/users'
import { fetchEmployees } from '@/hr/api/employees'
import { ApiError } from '@/core/api/types'

/**
 * ربط/فكّ ربط موظف بحساب المستخدم (يعيد استخدام خدمة الهوية 1:1 في الخادم).
 * البحث عن الموظفين يستخدم نقطة `GET /employees` القائمة.
 */
export function LinkEmployeeModal({ user, onClose }: { user: AdminUser; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  const invalidate = () => qc.invalidateQueries({ queryKey: ['admin', 'users'] })

  const results = useQuery({
    queryKey: ['admin', 'link-employee-search', search],
    queryFn: () => fetchEmployees({ search, perPage: 8 }),
    enabled: search.length > 0 && user.employee == null,
  })

  const link = useMutation({
    mutationFn: (employeeId: number) => linkEmployee(user.id, employeeId),
    onSuccess: () => {
      show('تم ربط الموظف بالحساب')
      void invalidate()
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر ربط الموظف', 'error'),
  })

  const unlink = useMutation({
    mutationFn: () => unlinkEmployee(user.id),
    onSuccess: () => {
      show('تم فكّ ربط الموظف')
      void invalidate()
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر فكّ الربط', 'error'),
  })

  function onSearch(e: FormEvent) {
    e.preventDefault()
    setSearch(searchInput.trim())
  }

  return (
    <Modal title={`ربط موظف — ${user.name}`} onClose={onClose}>
      {user.employee ? (
        <div className="space-y-4">
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
            <div className="font-medium text-slate-800">{user.employee.full_name_ar}</div>
            <div className="tabular-nums text-xs text-slate-400">{user.employee.employee_no}</div>
          </div>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" onClick={onClose} disabled={unlink.isPending}>
              إغلاق
            </Button>
            <Button type="button" onClick={() => unlink.mutate()} disabled={unlink.isPending}>
              {unlink.isPending ? 'جارٍ…' : 'فكّ الربط'}
            </Button>
          </div>
        </div>
      ) : (
        <div className="space-y-3">
          <form onSubmit={onSearch} className="flex items-end gap-2">
            <div className="flex-1">
              <Field
                label="ابحث عن موظف (الاسم أو الرقم الوظيفي)"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder="اسم الموظف أو رقمه…"
              />
            </div>
            <Button type="submit">بحث</Button>
          </form>

          {search.length > 0 && (
            <div className="max-h-64 overflow-y-auto rounded-lg border border-slate-100">
              {results.isError ? (
                <div className="p-3">
                  <ErrorState error={results.error} />
                </div>
              ) : results.data && results.data.items.length === 0 ? (
                <div className="p-3">
                  <EmptyState message="لا توجد نتائج مطابقة." />
                </div>
              ) : (
                <ul className="divide-y divide-slate-100">
                  {results.data?.items.map((emp) => (
                    <li key={emp.id} className="flex items-center justify-between gap-2 px-3 py-2">
                      <span>
                        <span className="text-sm font-medium text-slate-800">{emp.full_name_ar}</span>
                        <span className="block tabular-nums text-xs text-slate-400">{emp.employee_no}</span>
                      </span>
                      <Button
                        type="button"
                        variant="ghost"
                        onClick={() => link.mutate(emp.id)}
                        disabled={link.isPending}
                      >
                        ربط
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}

          <div className="flex justify-end">
            <Button type="button" variant="ghost" onClick={onClose}>
              إغلاق
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
