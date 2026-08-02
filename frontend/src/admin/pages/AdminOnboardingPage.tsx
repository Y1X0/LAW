import { useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Button, Card, Field, SelectField } from '@/core/ui/primitives'
import { PageHeader, SectionCard } from '@/core/ui/section'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { fetchBranches, fetchDepartments } from '@/core/api/org'
import { createEmployee } from '@/hr/api/employees'
import { createUser, linkEmployee } from '@/admin/api/users'
import { fetchRoles } from '@/admin/api/roles'

/**
 * معالج تهيئة موظف جديد (M1/PR-4) — يُنهي رحلة العميل في تدفّق واحد من المتصفّح:
 * موظف ← حساب ← دور ← ربط ← جاهز للدخول. يعيد استخدام نقاط النهاية القائمة فقط
 * (لا API جديد). في وحدة تحكّم المالك لأن إنشاء الحسابات يتطلّب users.manage.
 */
type Step = 1 | 2 | 3 | 4

export function AdminOnboardingPage() {
  const { show } = useToast()
  const [step, setStep] = useState<Step>(1)
  const [done, setDone] = useState<{ employeeNo: string; email: string; role: string } | null>(null)
  const [busy, setBusy] = useState(false)
  const [progress, setProgress] = useState('')

  // بيانات الموظف
  const [branchId, setBranchId] = useState<number | ''>('')
  const [departmentId, setDepartmentId] = useState<number | ''>('')
  const [emp, setEmp] = useState({ full_name_ar: '', national_id: '', employee_no: '', job_title: '' })
  // بيانات الحساب
  const [account, setAccount] = useState({ email: '', password: '' })
  // الدور
  const [roleId, setRoleId] = useState<number | ''>('')

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })
  const departments = useQuery({
    queryKey: ['org', 'departments', branchId || 'none'],
    queryFn: () => fetchDepartments(branchId || undefined),
    enabled: branchId !== '',
  })
  const roles = useQuery({ queryKey: ['admin', 'roles'], queryFn: fetchRoles })

  const setEmpField = (k: keyof typeof emp) => (e: { target: { value: string } }) => setEmp((s) => ({ ...s, [k]: e.target.value }))

  function validateStep(s: Step): string | null {
    if (s === 1) {
      if (branchId === '' || departmentId === '') return 'اختر الفرع والقسم'
      if (!emp.full_name_ar.trim()) return 'أدخل اسم الموظف'
      if (!emp.national_id.trim()) return 'أدخل الرقم الوطني'
    }
    if (s === 2) {
      if (!account.email.trim()) return 'أدخل البريد الإلكتروني'
      if (account.password.length < 8) return 'كلمة المرور 8 أحرف على الأقل'
    }
    if (s === 3 && roleId === '') return 'اختر دور الموظف'
    return null
  }

  function next() {
    const err = validateStep(step)
    if (err) { show(err, 'error'); return }
    setStep((s) => (s + 1) as Step)
  }

  async function run() {
    setBusy(true)
    try {
      setProgress('جارٍ إنشاء سجلّ الموظف…')
      const employee = await createEmployee({
        branch_id: Number(branchId),
        department_id: Number(departmentId),
        employee_no: emp.employee_no.trim() || undefined,
        full_name_ar: emp.full_name_ar.trim(),
        national_id: emp.national_id.trim(),
        job_title: emp.job_title.trim() || null,
        status: 'active',
      })

      setProgress('جارٍ إنشاء حساب الدخول وإسناد الدور…')
      const user = await createUser({
        name: emp.full_name_ar.trim(),
        email: account.email.trim(),
        password: account.password,
        role_ids: [Number(roleId)],
      })

      setProgress('جارٍ ربط الحساب بالموظف…')
      await linkEmployee(user.id, employee.id)

      const roleName = roles.data?.find((r) => r.id === roleId)?.display_name || ''
      setDone({ employeeNo: String(employee.employee_no ?? ''), email: account.email.trim(), role: roleName })
      show('اكتملت تهيئة الموظف — يمكنه تسجيل الدخول الآن')
    } catch (e) {
      show(e instanceof ApiError ? e.message : 'تعذّرت التهيئة — راجع البيانات وحاول مجدداً', 'error')
    } finally {
      setBusy(false)
      setProgress('')
    }
  }

  function reset() {
    setStep(1); setDone(null)
    setBranchId(''); setDepartmentId('')
    setEmp({ full_name_ar: '', national_id: '', employee_no: '', job_title: '' })
    setAccount({ email: '', password: '' }); setRoleId('')
  }

  if (done) {
    return (
      <div className="space-y-5">
        <PageHeader title="تهيئة موظف جديد" subtitle="اكتملت الرحلة" />
        <SectionCard title="✓ الموظف جاهز للعمل">
          <p className="text-sm text-slate-600">أُنشئ الموظف وحسابه، ورُبطا، وأُسند الدور. يمكن للموظف تسجيل الدخول الآن.</p>
          <div className="mt-3 space-y-1 text-sm">
            <div><span className="text-slate-400">الرقم الوظيفي:</span> <span className="font-medium tabular-nums">{done.employeeNo}</span></div>
            <div><span className="text-slate-400">بريد الدخول:</span> <span className="font-medium">{done.email}</span></div>
            <div><span className="text-slate-400">الدور:</span> <span className="font-medium">{done.role}</span></div>
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Button onClick={reset}>تهيئة موظف آخر</Button>
            <Link to="/admin/users" className="lp-press rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">إدارة المستخدمين</Link>
          </div>
        </SectionCard>
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <PageHeader title="تهيئة موظف جديد" subtitle="موظف ← حساب ← دور ← ربط، في تدفّق واحد" />

      <Stepper step={step} />

      <Card>
        {step === 1 && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <SelectField label="الفرع *" value={branchId === '' ? '' : String(branchId)} onChange={(e) => { setBranchId(e.target.value ? Number(e.target.value) : ''); setDepartmentId('') }}>
              <option value="">— اختر الفرع —</option>
              {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </SelectField>
            <SelectField label="القسم *" value={departmentId === '' ? '' : String(departmentId)} onChange={(e) => setDepartmentId(e.target.value ? Number(e.target.value) : '')} disabled={branchId === ''}>
              <option value="">{branchId === '' ? 'اختر الفرع أولاً' : '— اختر القسم —'}</option>
              {departments.data?.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </SelectField>
            <Field label="الاسم بالعربية *" value={emp.full_name_ar} onChange={setEmpField('full_name_ar')} required />
            <Field label="الرقم الوطني *" value={emp.national_id} onChange={setEmpField('national_id')} required />
            <Field label="الرقم الوظيفي" value={emp.employee_no} onChange={setEmpField('employee_no')} placeholder="يُولّد تلقائياً إن تُرك فارغاً" />
            <Field label="المسمّى الوظيفي" value={emp.job_title} onChange={setEmpField('job_title')} />
          </div>
        )}

        {step === 2 && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="البريد الإلكتروني (للدخول) *" type="email" value={account.email} onChange={(e) => setAccount((a) => ({ ...a, email: e.target.value }))} required />
            <Field label="كلمة المرور المبدئية *" type="password" value={account.password} onChange={(e) => setAccount((a) => ({ ...a, password: e.target.value }))} minLength={8} required />
            <p className="text-xs text-slate-400 sm:col-span-2">سيستخدم الموظف هذا البريد وكلمة المرور لتسجيل الدخول، ويمكنه تغييرها لاحقاً.</p>
          </div>
        )}

        {step === 3 && (
          <div className="max-w-sm">
            <SelectField label="دور الموظف *" value={roleId === '' ? '' : String(roleId)} onChange={(e) => setRoleId(e.target.value ? Number(e.target.value) : '')}>
              <option value="">— اختر الدور —</option>
              {roles.data?.map((r) => <option key={r.id} value={r.id}>{r.display_name || r.name}</option>)}
            </SelectField>
            <p className="mt-2 text-xs text-slate-400">الدور يحدّد البوابة والصلاحيات (موظف = خدمة ذاتية، موارد بشرية = بوابة HR، محامٍ = بوابة القضايا…).</p>
          </div>
        )}

        {step === 4 && (
          <div className="space-y-3">
            <SectionCard title="مراجعة قبل التنفيذ">
              <Review label="الفرع" value={branches.data?.find((b) => b.id === branchId)?.name ?? '—'} />
              <Review label="القسم" value={departments.data?.find((d) => d.id === departmentId)?.name ?? '—'} />
              <Review label="الاسم" value={emp.full_name_ar} />
              <Review label="الرقم الوطني" value={emp.national_id} />
              <Review label="البريد" value={account.email} />
              <Review label="الدور" value={roles.data?.find((r) => r.id === roleId)?.display_name ?? '—'} />
            </SectionCard>
            {busy && <p className="text-sm text-brand-700">{progress}</p>}
          </div>
        )}

        <div className="mt-4 flex items-center justify-between">
          <Button variant="ghost" onClick={() => setStep((s) => Math.max(1, s - 1) as Step)} disabled={step === 1 || busy}>السابق</Button>
          {step < 4 ? (
            <Button onClick={next}>التالي</Button>
          ) : (
            <Button onClick={run} disabled={busy}>{busy ? 'جارٍ التهيئة…' : 'تنفيذ التهيئة'}</Button>
          )}
        </div>
      </Card>
    </div>
  )
}

function Stepper({ step }: { step: Step }) {
  const steps = ['الموظف', 'الحساب', 'الدور', 'المراجعة']
  return (
    <ol className="flex flex-wrap gap-2 text-sm">
      {steps.map((label, i) => {
        const n = (i + 1) as Step
        const state = n < step ? 'done' : n === step ? 'active' : 'todo'
        return (
          <li key={label} className={`flex items-center gap-2 rounded-full border px-3 py-1.5 ${
            state === 'active' ? 'border-brand-500 bg-brand-50 text-brand-800' :
            state === 'done' ? 'border-green-300 bg-green-50 text-green-700' : 'border-slate-200 text-slate-400'
          }`}>
            <span className="tabular-nums font-bold">{state === 'done' ? '✓' : n}</span>{label}
          </li>
        )
      })}
    </ol>
  )
}

function Review({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex justify-between gap-3 border-b border-slate-100 py-1.5 text-sm last:border-0">
      <span className="text-slate-400">{label}</span>
      <span className="font-medium text-slate-800">{value}</span>
    </div>
  )
}
