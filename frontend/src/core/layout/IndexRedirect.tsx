import { Navigate } from 'react-router-dom'
import { useCapabilities } from '@/core/capabilities/useCapabilities'

/** جذر «/»: يوجّه حسب القدرة — محامٍ → رئيسيته · إدارة HR → لوحة HR · وإلا موظف → لوحته. */
export function IndexRedirect() {
  const { isLawyer, canManageHr } = useCapabilities()
  const to = isLawyer ? '/home' : canManageHr ? '/hr' : '/dashboard'
  return <Navigate to={to} replace />
}
