import { createContext } from 'react'

export type CapabilitiesStatus = 'loading' | 'ready'

/** قدرات المستخدم المكتشَفة بعد المصادقة (تقود التنقّل والحماية). */
export interface CapabilitiesValue {
  status: CapabilitiesStatus
  /** هل المستخدم محامٍ (له وصول لملخّصه القانوني)؟ */
  isLawyer: boolean
}

export const CapabilitiesContext = createContext<CapabilitiesValue | null>(null)
