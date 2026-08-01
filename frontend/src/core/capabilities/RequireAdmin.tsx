import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from './useCapabilities'

/**
 * حارس مسارات مالك المنصة (Super Admin) — يُركّب *فوق* حارس المصادقة (ProtectedRoute).
 * من لا يملك التحكّم الإداري (isAdmin = صلاحية roles.manage) يُعاد إلى جذره
 * (يوجّهه IndexRedirect إلى بوابته). الباك-إند يبقى الحكم النهائي على كل عملية.
 */
export function RequireAdmin() {
  const { isAdmin } = useCapabilities()
  return isAdmin ? <Outlet /> : <Navigate to="/" replace />
}
