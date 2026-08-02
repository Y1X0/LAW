import { useQuery } from '@tanstack/react-query'
import { Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { CapabilitiesContext, type CapabilitiesValue } from './capabilitiesContext'
import { probeCanManageHr, probeCanManagePayroll, probeIsAdmin, probeIsLawyer } from './probe'

const probeOptions = {
  staleTime: Infinity,
  gcTime: Infinity,
  // 401/403 تُحسم إلى false داخل الـ probe (لا تُرمى)، فإعادة المحاولة هنا تخصّ
  // أخطاء الشبكة/5xx فقط: تعافٍ من انقطاع عابر بدل حبس المستخدم في البوابة الخطأ.
  retry: 2,
  refetchOnWindowFocus: false,
} as const

/**
 * يُركّب داخل المنطقة المصادَق عليها فقط (تحت ProtectedRoute): يكتشف قدرات المستخدم مرّة
 * واحدة (مُخزّنة طوال الجلسة) ويوفّرها للتنقّل والحماية، مع بوّابة تحميل حتى تُحسم القدرات.
 * الكشفان مستقلّان ويعملان بالتوازي (كلٌّ إشارة صلاحية خفيفة).
 */
export function CapabilitiesProvider() {
  const lawyer = useQuery({ queryKey: ['capabilities', 'lawyer'], queryFn: probeIsLawyer, ...probeOptions })
  const hr = useQuery({ queryKey: ['capabilities', 'hr'], queryFn: probeCanManageHr, ...probeOptions })
  const admin = useQuery({ queryKey: ['capabilities', 'admin'], queryFn: probeIsAdmin, ...probeOptions })
  const payroll = useQuery({ queryKey: ['capabilities', 'payroll'], queryFn: probeCanManagePayroll, ...probeOptions })

  const isPending = lawyer.isPending || hr.isPending || admin.isPending || payroll.isPending
  const value: CapabilitiesValue = {
    status: isPending ? 'loading' : 'ready',
    isLawyer: lawyer.data ?? false,
    canManageHr: hr.data ?? false,
    isAdmin: admin.data ?? false,
    canManagePayroll: payroll.data ?? false,
  }

  return (
    <CapabilitiesContext.Provider value={value}>
      {isPending ? <FullPageLoader /> : <Outlet />}
    </CapabilitiesContext.Provider>
  )
}
