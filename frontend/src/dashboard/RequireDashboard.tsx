import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from '@/core/capabilities/useCapabilities'

/**
 * حارس مسار لوحة المؤشّرات الإدارية — يُركّب فوق حارس المصادقة (ProtectedRoute).
 * من لا يملك صلاحية dashboard.view_management (canViewManagementDashboard) يُعاد إلى
 * جذره. القدرة تُكتشف مرّة عبر CapabilitiesProvider؛ الخادم يبقى الحكم النهائي.
 */
export function RequireDashboard() {
  const { canViewManagementDashboard } = useCapabilities()
  return canViewManagementDashboard ? <Outlet /> : <Navigate to="/" replace />
}
