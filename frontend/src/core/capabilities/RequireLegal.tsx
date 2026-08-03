import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from './useCapabilities'

/**
 * حارس مسارات الإدارة القانونية — يُركّب *فوق* حارس المصادقة (ProtectedRoute).
 * من لا يملك الوصول (canManageLegal = صلاحية clients.view الإدارية) يُعاد إلى جذره.
 * المحامي (view_own) لا يملكها فيبقى في بوابته. الخادم يبقى الحكم النهائي على كل عملية.
 */
export function RequireLegal() {
  const { canManageLegal } = useCapabilities()
  return canManageLegal ? <Outlet /> : <Navigate to="/" replace />
}
