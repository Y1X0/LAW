import { useCapabilities } from '@/core/capabilities/useCapabilities'
import { EmployeeLayout } from '@/employee/EmployeeLayout'
import { HrLayout } from '@/hr/layout/HrLayout'
import { LawyerLayout } from '@/lawyer/LawyerLayout'

/**
 * يختار هيكل البوابة حسب القدرة المكتشَفة (المحامي أولاً للحفاظ على سلوكه دون تغيير):
 * محامٍ → LawyerLayout · إدارة HR → HrLayout · وإلا موظف → EmployeeLayout.
 */
export function RoleLayout() {
  const { isLawyer, canManageHr } = useCapabilities()
  if (isLawyer) return <LawyerLayout />
  if (canManageHr) return <HrLayout />
  return <EmployeeLayout />
}
