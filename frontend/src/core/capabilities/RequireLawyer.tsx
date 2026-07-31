import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from './useCapabilities'

/**
 * حارس مسارات المحامي — يُركّب *فوق* حارس المصادقة (ProtectedRoute)، فلا يكرّر منطقها.
 * غير المحامي يُعاد إلى جذره (يوجّهه IndexRedirect إلى بوابته). الباك-إند يبقى الحكم النهائي.
 */
export function RequireLawyer() {
  const { isLawyer } = useCapabilities()
  return isLawyer ? <Outlet /> : <Navigate to="/" replace />
}
