import { InfoRow, SectionCard } from '@/core/ui/section'
import { formatDate } from '@/core/lib/format'
import type { EmployeeDetail } from '@/hr/api/employeeProfile'

/** تبويب المعلومات — من حمولة GET /employees/{id} المحمّلة مسبقاً في الرأس. */
export function InfoTab({ emp }: { emp: EmployeeDetail }) {
  const gender = emp.gender === 'male' ? 'ذكر' : emp.gender === 'female' ? 'أنثى' : '—'
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <SectionCard title="بيانات الموظف">
        <InfoRow label="الرقم الوظيفي" value={<span className="tabular-nums">{emp.employee_no}</span>} />
        <InfoRow label="الاسم (عربي)" value={emp.full_name_ar} />
        <InfoRow label="الاسم (إنجليزي)" value={emp.full_name_en ?? '—'} />
        <InfoRow label="الهوية" value={<span className="tabular-nums">{emp.national_id ?? '—'}</span>} />
        <InfoRow label="الجنس" value={gender} />
        <InfoRow label="تاريخ الميلاد" value={<span className="tabular-nums">{formatDate(emp.birth_date ?? null)}</span>} />
        <InfoRow label="المسمّى الوظيفي" value={emp.job_title ?? '—'} />
        <InfoRow label="القسم" value={emp.department?.name ?? '—'} />
        <InfoRow label="الفرع" value={emp.branch?.name ?? '—'} />
        <InfoRow label="المدير" value={emp.manager?.full_name_ar ?? '—'} />
        <InfoRow label="تاريخ التعيين" value={<span className="tabular-nums">{formatDate(emp.hire_date ?? null)}</span>} />
      </SectionCard>

      <div className="space-y-4">
        <SectionCard title="التواصل">
          <InfoRow label="الهاتف" value={<span className="tabular-nums">{emp.phone ?? '—'}</span>} />
          <InfoRow label="البريد" value={emp.email ?? '—'} />
          <InfoRow label="العنوان" value={emp.address ?? '—'} />
        </SectionCard>

        <SectionCard title="جهة الطوارئ">
          <InfoRow label="الاسم" value={emp.emergency_contact_name ?? '—'} />
          <InfoRow label="الهاتف" value={<span className="tabular-nums">{emp.emergency_contact_phone ?? '—'}</span>} />
        </SectionCard>
      </div>
    </div>
  )
}
