import { useCapabilities } from '@/core/capabilities/useCapabilities'
import { AdminLayout } from '@/admin/layout/AdminLayout'
import { EmployeeLayout } from '@/employee/EmployeeLayout'
import { HrLayout } from '@/hr/layout/HrLayout'
import { LawyerLayout } from '@/lawyer/LawyerLayout'

/**
 * يختار هيكل البوابة حسب القدرة المكتشَفة (Admin أعلى أولوية؛ ثم المحامي بلا تغيير سلوكه):
 * Admin → AdminLayout · محامٍ → LawyerLayout · إدارة HR → HrLayout · وإلا موظف → EmployeeLayout.
 */
export function RoleLayout() {
  const { isAdmin, isLawyer, canManageHr } = useCapabilities()
  if (isAdmin) return <AdminLayout />
  if (isLawyer) return <LawyerLayout />
  if (canManageHr) return <HrLayout />
  return <EmployeeLayout />
}
