import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل وحدة التحكّم (Super Admin) — ADMIN-1: الأساس فقط.
 * تُضاف عناصر «المستخدمون/الأدوار/النظام…» في المراحل التالية مع بناء شاشاتها،
 * لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
const NAV: NavItem[] = [{ to: '/admin', label: 'الرئيسية', icon: 'dashboard' }]

export function AdminLayout() {
  return <Shell nav={NAV} subtitle="وحدة التحكّم" />
}
