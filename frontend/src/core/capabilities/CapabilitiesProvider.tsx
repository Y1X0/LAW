import { useQuery } from '@tanstack/react-query'
import { Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { CapabilitiesContext, type CapabilitiesValue } from './capabilitiesContext'
import { probeIsLawyer } from './probe'

/**
 * يُركّب داخل المنطقة المصادَق عليها فقط (تحت ProtectedRoute): يكتشف قدرة المحامي مرّة
 * واحدة (مُخزّنة طوال الجلسة) ويوفّرها للتنقّل والحماية، مع بوّابة تحميل حتى تُحسم القدرة.
 */
export function CapabilitiesProvider() {
  const { data, isPending } = useQuery({
    queryKey: ['capabilities', 'lawyer'],
    queryFn: probeIsLawyer,
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
    refetchOnWindowFocus: false,
  })

  const value: CapabilitiesValue = {
    status: isPending ? 'loading' : 'ready',
    isLawyer: data ?? false,
  }

  return (
    <CapabilitiesContext.Provider value={value}>
      {isPending ? <FullPageLoader /> : <Outlet />}
    </CapabilitiesContext.Provider>
  )
}
