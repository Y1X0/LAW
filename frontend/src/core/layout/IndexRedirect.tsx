import { Navigate } from 'react-router-dom'
import { useCapabilities } from '@/core/capabilities/useCapabilities'

/** جذر «/»: يوجّه حسب الدور — محامٍ → رئيسية المحامي · موظف → لوحته. */
export function IndexRedirect() {
  const { isLawyer } = useCapabilities()
  return <Navigate to={isLawyer ? '/home' : '/dashboard'} replace />
}
