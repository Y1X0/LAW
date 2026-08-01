import { Link } from 'react-router-dom'
import { Card } from '@/core/ui/primitives'
import { PageHeader } from '@/core/ui/section'

/**
 * ملف الموظف (عنصر نائب مؤقّت — HR-3).
 * يجعل زرّ «فتح» في القائمة عاملاً؛ التبويبات والبيانات الحقيقية تُبنى في HR-4.
 */
export function HrEmployeeProfilePage() {
  return (
    <div className="space-y-5">
      <PageHeader
        title="ملف الموظف"
        action={
          <Link to="/hr/employees" className="text-sm font-medium text-brand-700 hover:underline">
            ← كل الموظفين
          </Link>
        }
      />
      <Card className="lp-reveal text-sm text-slate-500">ملف الموظف قيد الإنشاء.</Card>
    </div>
  )
}
