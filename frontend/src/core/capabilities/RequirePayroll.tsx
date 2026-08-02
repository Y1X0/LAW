import { Navigate, Outlet } from 'react-router-dom'
import { useCapabilities } from './useCapabilities'

/**
 * حارس مسارات الرواتب — يُركّب *فوق* حارس المصادقة (ProtectedRoute)، فلا يكرّر منطقها.
 * من لا يملك الوصول (canManagePayroll = صلاحية payroll.view) يُعاد إلى جذره
 * (يوجّهه IndexRedirect إلى بوابته). الباك-إند يبقى الحكم النهائي على كل عملية.
 */
export function RequirePayroll() {
  const { canManagePayroll } = useCapabilities()
  return canManagePayroll ? <Outlet /> : <Navigate to="/" replace />
}
