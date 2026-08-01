import { useQuery } from '@tanstack/react-query'
import { Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { CapabilitiesContext, type CapabilitiesValue } from './capabilitiesContext'
import { probeCanManageHr, probeIsLawyer } from './probe'

const probeOptions = {
  staleTime: Infinity,
  gcTime: Infinity,
  retry: false,
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

  const isPending = lawyer.isPending || hr.isPending
  const value: CapabilitiesValue = {
    status: isPending ? 'loading' : 'ready',
    isLawyer: lawyer.data ?? false,
    canManageHr: hr.data ?? false,
  }

  return (
    <CapabilitiesContext.Provider value={value}>
      {isPending ? <FullPageLoader /> : <Outlet />}
    </CapabilitiesContext.Provider>
  )
}
