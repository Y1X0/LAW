import { Navigate, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { useFinanceCapabilities } from './api/capabilities'

/**
 * حارس مسارات المالية (Phase 6 · PR-5) — فوق حارس المصادقة. من لا يملك invoices.view
 * يُعاد إلى جذره. بوّابة تحميل حتى تُحسم القدرة. الخادم يبقى الحكم النهائي على كل عملية.
 */
export function RequireFinance() {
  const { canView, isPending } = useFinanceCapabilities()
  if (isPending) return <FullPageLoader />
  return canView ? <Outlet /> : <Navigate to="/" replace />
}
