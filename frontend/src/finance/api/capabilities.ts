import { useQuery } from '@tanstack/react-query'
import { z } from 'zod'
import { api } from '@/core/api/client'

/**
 * قدرات المستخدم المالية (Phase 6 · PR-5). المصدر الوحيد لإظهار الأزرار في الواجهة —
 * الواجهة لا تعرف صلاحيات المستخدم من مكان آخر. الخادم يبقى الحكم النهائي على كل عملية.
 */
const financeCapabilitiesSchema = z.object({
  can_view: z.boolean(),
  can_create: z.boolean(),
  can_approve: z.boolean(),
  can_record_payment: z.boolean(),
  can_record_expense: z.boolean(),
  can_view_reports: z.boolean(),
})

export interface FinanceCapabilities {
  canView: boolean
  canCreate: boolean
  canApprove: boolean
  canRecordPayment: boolean
  canRecordExpense: boolean
  canViewReports: boolean
  isPending: boolean
}

const NONE = { canView: false, canCreate: false, canApprove: false, canRecordPayment: false, canRecordExpense: false, canViewReports: false }

export function fetchFinanceCapabilities(): Promise<typeof NONE> {
  return api.get<unknown>('finance/capabilities').then((d) => {
    const caps = financeCapabilitiesSchema.parse(d)
    return {
      canView: caps.can_view,
      canCreate: caps.can_create,
      canApprove: caps.can_approve,
      canRecordPayment: caps.can_record_payment,
      canRecordExpense: caps.can_record_expense,
      canViewReports: caps.can_view_reports,
    }
  })
}

/**
 * يُكتشَف مرّة ويُخزَّن طوال الجلسة (staleTime Infinity). عند غياب الوصول (401/غيره)
 * تُحسم القدرات إلى false بدل حبس الواجهة. مفتاح موحّد ⇒ طلب واحد مشترك عبر المكوّنات.
 */
export function useFinanceCapabilities(): FinanceCapabilities {
  const query = useQuery({
    queryKey: ['finance', 'capabilities'],
    queryFn: fetchFinanceCapabilities,
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
    refetchOnWindowFocus: false,
  })

  return { ...(query.data ?? NONE), isPending: query.isPending }
}
