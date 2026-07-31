import { Navigate, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { useAuth } from './useAuth'

/** يحمي المسارات: يعيد توجيه غير المصادق إلى /login، ويُظهر تحميلاً أثناء التحقّق. */
export function ProtectedRoute() {
  const { status } = useAuth()

  if (status === 'loading') return <FullPageLoader />
  if (status === 'unauthenticated') return <Navigate to="/login" replace />
  return <Outlet />
}
