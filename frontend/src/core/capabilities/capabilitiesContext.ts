import { createContext } from 'react'

export type CapabilitiesStatus = 'loading' | 'ready'

/** قدرات المستخدم المكتشَفة بعد المصادقة (تقود التنقّل والحماية). */
export interface CapabilitiesValue {
  status: CapabilitiesStatus
  /** هل المستخدم محامٍ (له وصول لملخّصه القانوني)؟ */
  isLawyer: boolean
  /** هل يملك المستخدم وصولاً إدارياً للموارد البشرية (صلاحية employees.view)؟ */
  canManageHr: boolean
  /** هل المستخدم مالك المنصة / Super Admin (صلاحية roles.manage)؟ */
  isAdmin: boolean
  /** هل يملك المستخدم وصولاً لإدارة الرواتب (صلاحية payroll.view)؟ */
  canManagePayroll: boolean
  /** هل يملك المستخدم وصولاً للإدارة القانونية (صلاحية clients.view — إدارية)؟ */
  canManageLegal: boolean
  /** هل يملك المستخدم لوحة المؤشّرات الإدارية الشاملة (صلاحية dashboard.view_management)؟ */
  canViewManagementDashboard: boolean
}

export const CapabilitiesContext = createContext<CapabilitiesValue | null>(null)
