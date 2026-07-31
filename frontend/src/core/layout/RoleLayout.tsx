import { useCapabilities } from '@/core/capabilities/useCapabilities'
import { EmployeeLayout } from '@/employee/EmployeeLayout'
import { LawyerLayout } from '@/lawyer/LawyerLayout'

/** يختار هيكل البوابة حسب الدور المكتشَف: محامٍ → LawyerLayout · موظف → EmployeeLayout. */
export function RoleLayout() {
  const { isLawyer } = useCapabilities()
  return isLawyer ? <LawyerLayout /> : <EmployeeLayout />
}
