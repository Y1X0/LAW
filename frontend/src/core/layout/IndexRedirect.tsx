import { Navigate } from 'react-router-dom'
import { useCapabilities } from '@/core/capabilities/useCapabilities'

/** جذر «/»: يوجّه حسب القدرة — Admin → لوحة المسؤول · محامٍ → رئيسيته · HR → لوحته · وإلا موظف. */
export function IndexRedirect() {
  const { isAdmin, isLawyer, canManageHr } = useCapabilities()
  const to = isAdmin ? '/admin' : isLawyer ? '/home' : canManageHr ? '/hr' : '/dashboard'
  return <Navigate to={to} replace />
}
