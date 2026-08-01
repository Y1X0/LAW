import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from './useCapabilities'

/**
 * حارس مسارات الموارد البشرية — يُركّب *فوق* حارس المصادقة (ProtectedRoute)، فلا يكرّر منطقها.
 * من لا يملك الوصول الإداري (canManageHr = صلاحية employees.view) يُعاد إلى جذره
 * (يوجّهه IndexRedirect إلى بوابته). الباك-إند يبقى الحكم النهائي على كل عملية.
 */
export function RequireHr() {
  const { canManageHr } = useCapabilities()
  return canManageHr ? <Outlet /> : <Navigate to="/" replace />
}
