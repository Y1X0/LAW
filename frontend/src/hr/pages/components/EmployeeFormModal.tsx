import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { fetchBranches, fetchDepartments } from '@/core/api/org'
import {
  EMPLOYEE_STATUSES,
  type EmployeeDetail,
  type EmployeeInput,
  createEmployee,
  employeeStatusLabel,
  fetchEmployees,
  updateEmployee,
} from '@/hr/api/employees'

/**
 * نموذج إنشاء/تعديل موظف (مكوّن واحد). الفرع أولاً، ثم الأقسام تُحمَّل حسب الفرع
 * المختار — فلا يظهر قسم من فرع آخر (قاعدة عمل يفرضها الخادم أيضاً). متجاوب.
 */
export function EmployeeFormModal({ employee, onClose }: { employee: EmployeeDetail | null; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const isEdit = employee !== null

  const [branchId, setBranchId] = useState<number | ''>(employee?.branch_id ?? '')
  const [departmentId, setDepartmentId] = useState<number | ''>(employee?.department_id ?? '')
  const [form, setForm] = useState({
    employee_no: employee?.employee_no ?? '',
    full_name_ar: employee?.full_name_ar ?? '',
    full_name_en: employee?.full_name_en ?? '',
    national_id: employee?.national_id ?? '',
    email: employee?.email ?? '',
    phone: employee?.phone ?? '',
    job_title: employee?.job_title ?? '',
    hire_date: employee?.hire_date ? String(employee.hire_date).slice(0, 10) : '',
    status: employee?.status ?? 'active',
    manager_id: employee?.manager_id ? String(employee.manager_id) : '',
    termination_date: employee?.termination_date ? String(employee.termination_date).slice(0, 10) : '',
    termination_reason: (employee?.termination_reason as string) ?? '',
  })
  const set = (k: keyof typeof form) => (e: { target: { value: string } }) => setForm((f) => ({ ...f, [k]: e.target.value }))

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })
  const departments = useQuery({
    queryKey: ['org', 'departments', branchId || 'none'],
    queryFn: () => fetchDepartments(branchId || undefined),
    enabled: branchId !== '',
  })
  const managers = useQuery({
    queryKey: ['hr', 'employees', 'managers'],
    queryFn: () => fetchEmployees({ perPage: 100, status: 'active' }),
  })

  // تغيير الفرع يصفّر القسم إن لم يعد تابعاً له.
  useEffect(() => {
    if (departmentId !== '' && departments.data && !departments.data.some((d) => d.id === departmentId)) {
      setDepartmentId('')
    }
  }, [departments.data, departmentId])

  const save = useMutation({
    mutationFn: () => {
      const input: EmployeeInput = {
        branch_id: Number(branchId),
        department_id: Number(departmentId),
        employee_no: form.employee_no.trim() || undefined,
        full_name_ar: form.full_name_ar.trim(),
        full_name_en: form.full_name_en.trim() || null,
        national_id: form.national_id.trim(),
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        job_title: form.job_title.trim() || null,
        hire_date: form.hire_date || null,
        status: form.status,
        manager_id: form.manager_id ? Number(form.manager_id) : null,
        termination_date: form.status === 'terminated' ? form.termination_date || null : null,
        termination_reason: form.status === 'terminated' ? form.termination_reason.trim() || null : null,
      }
      return isEdit ? updateEmployee(employee.id, input) : createEmployee(input)
    },
    onSuccess: () => {
      show(isEdit ? 'تم تحديث الموظف' : 'تم إنشاء الموظف')
      void qc.invalidateQueries({ queryKey: ['hr', 'employees'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (branchId === '' || departmentId === '') {
      show('اختر الفرع والقسم', 'error')
      return
    }
    save.mutate()
  }

  const activeBranches = (branches.data ?? []).filter((b) => b.is_active || b.id === branchId)
  const managerOptions = (managers.data?.items ?? []).filter((m) => m.id !== employee?.id)

  return (
    <Modal title={isEdit ? `تعديل الموظف — ${employee.full_name_ar}` : 'إضافة موظف'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="الفرع *" value={branchId === '' ? '' : String(branchId)} onChange={(e) => setBranchId(e.target.value ? Number(e.target.value) : '')} required>
            <option value="">— اختر الفرع —</option>
            {activeBranches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
          <SelectField
            label="القسم *"
            value={departmentId === '' ? '' : String(departmentId)}
            onChange={(e) => setDepartmentId(e.target.value ? Number(e.target.value) : '')}
            disabled={branchId === ''}
            required
          >
            <option value="">{branchId === '' ? 'اختر الفرع أولاً' : '— اختر القسم —'}</option>
            {(departments.data ?? []).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
          </SelectField>

          <Field
            label="الرقم الوظيفي"
            value={form.employee_no}
            onChange={set('employee_no')}
            placeholder="يُولّد تلقائياً إن تُرك فارغاً"
          />
          <Field label="الرقم الوطني *" value={form.national_id} onChange={set('national_id')} required />
          <Field label="الاسم بالعربية *" value={form.full_name_ar} onChange={set('full_name_ar')} required />
          <Field label="الاسم بالإنجليزية" value={form.full_name_en} onChange={set('full_name_en')} />
          <Field label="البريد الإلكتروني" type="email" value={form.email} onChange={set('email')} />
          <Field label="الهاتف" value={form.phone} onChange={set('phone')} />
          <Field label="المسمّى الوظيفي" value={form.job_title} onChange={set('job_title')} />
          <Field label="تاريخ التعيين" type="date" value={form.hire_date} onChange={set('hire_date')} />
          <SelectField label="الحالة" value={form.status} onChange={set('status')}>
            {EMPLOYEE_STATUSES.map((s) => <option key={s} value={s}>{employeeStatusLabel(s)}</option>)}
          </SelectField>
          <SelectField label="المدير المباشر" value={form.manager_id} onChange={set('manager_id')}>
            <option value="">— بدون —</option>
            {managerOptions.map((m) => <option key={m.id} value={m.id}>{m.full_name_ar}</option>)}
          </SelectField>

          {form.status === 'terminated' && (
            <>
              <Field label="تاريخ إنهاء الخدمة" type="date" value={form.termination_date} onChange={set('termination_date')} />
              <Field label="سبب إنهاء الخدمة" value={form.termination_reason} onChange={set('termination_reason')} />
            </>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}
